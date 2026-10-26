<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Database\Query\TcaControlFields;
use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Calculates the effective salary with plain QueryBuilder queries (no
 * Extbase, usable in DataHandler hooks and commands). Same rules as the
 * frontend today (Job::getSalaryRangeMin()/getSalaryRangeMax()):
 *
 * - salary_mode 1: salary_min - salary_max (salary_min, if salary_max is 0)
 * - salary_mode 0: lowest/highest positive amount of all currently valid
 *   selected salary grades: flat_amount (has_steps = 0) or the amount of all
 *   valid steps. Valid = not deleted, not hidden, starttime <= now and
 *   endtime = 0 or endtime > now + LOOK_AHEAD (expires within the look-ahead
 *   = invalid already).
 *
 * Needs 4 queries per call, independent of the number of jobs.
 */
final readonly class EffectiveSalaryCalculator implements EffectiveSalaryCalculatorInterface
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    private const TABLE_SALARY_GRADE = 'tx_jobboard_domain_model_salarygrade';

    private const TABLE_SALARY_STEP = 'tx_jobboard_domain_model_salarystep';

    private const TABLE_JOB_SALARY_GRADE_MM = 'tx_jobboard_job_salarygrade_mm';

    private const SALARY_MODE_FREE_ENTRY = 1;

    public function __construct(
        private ConnectionPool $connectionPool,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    public function calculateForJobs(array $jobUids, int $now): array
    {
        $jobs = $this->fetchJobs($jobUids);
        $salaryGradeUidsByJob = $this->fetchSalaryGradeUidsByJob(array_keys($jobs));
        $salaryGradeUids = array_values(array_unique(array_merge([], ...array_values($salaryGradeUidsByJob))));
        $salaryGrades = $this->fetchSalaryGrades($salaryGradeUids);
        $salaryStepsByGrade = $this->fetchSalaryStepsByGrade(array_keys($salaryGrades));

        $effectiveSalaries = [];
        foreach ($jobs as $jobUid => $job) {
            $effectiveSalaries[$jobUid] = (int)$job['salary_mode'] === self::SALARY_MODE_FREE_ENTRY
                ? EffectiveSalary::fromFreeEntry((float)$job['salary_min'], (float)$job['salary_max'])
                : $this->calculateForSalaryGrades(
                    array_intersect_key($salaryGrades, array_flip($salaryGradeUidsByJob[$jobUid] ?? [])),
                    $salaryStepsByGrade,
                    $now,
                );
        }

        return $effectiveSalaries;
    }

    /**
     * @param array<int, array<string, mixed>> $salaryGrades
     * @param array<int, array<int, array<string, mixed>>> $salaryStepsByGrade
     */
    private function calculateForSalaryGrades(array $salaryGrades, array $salaryStepsByGrade, int $now): EffectiveSalary
    {
        $gradeFields = $this->getControlFields(self::TABLE_SALARY_GRADE);
        $stepFields = $this->getControlFields(self::TABLE_SALARY_STEP);
        $amounts = [];
        $pointsInTime = [];
        foreach ($salaryGrades as $salaryGradeUid => $salaryGrade) {
            $salarySteps = (bool)$salaryGrade['has_steps'] ? ($salaryStepsByGrade[$salaryGradeUid] ?? []) : [];
            $pointsInTime = [
                ...$pointsInTime,
                ...$this->getPointsInTime([$salaryGrade], $gradeFields, $now),
                ...$this->getPointsInTime($salarySteps, $stepFields, $now),
            ];
            $visibleSteps = array_filter(
                $salarySteps,
                fn(array $salaryStep): bool => $this->isVisible($salaryStep, $stepFields, $now),
            );
            $amounts = [...$amounts, ...$this->getVisibleAmounts($salaryGrade, $visibleSteps, $gradeFields, $now)];
        }

        return EffectiveSalary::fromAmounts($amounts, $this->getNextPointInTime($pointsInTime));
    }

    /**
     * The flat amount of a grade without steps, otherwise the amounts of its
     * visible steps. Nothing, if the grade itself is not visible.
     *
     * @param array<string, mixed> $salaryGrade
     * @param array<int, array<string, mixed>> $visibleSalarySteps
     * @return float[]
     */
    private function getVisibleAmounts(
        array $salaryGrade,
        array $visibleSalarySteps,
        TcaControlFields $gradeFields,
        int $now,
    ): array {
        if (!$this->isVisible($salaryGrade, $gradeFields, $now)) {
            return [];
        }

        if (!(bool)$salaryGrade['has_steps']) {
            return [(float)$salaryGrade['flat_amount']];
        }

        return array_values(array_map(
            static fn(array $salaryStep): float => (float)$salaryStep['amount'],
            $visibleSalarySteps,
        ));
    }

    /**
     * Points in time, at which the result will change: a starttime in the
     * future and an endtime after the look-ahead (an endtime within the
     * look-ahead has been applied already). Considers all (also currently
     * invalid) selected grades and all their steps: a step of a grade, that
     * starts tomorrow, may start or expire before or after that. A table
     * without start or end time field (TCA ctrl "enablecolumns") has no such
     * points in time.
     *
     * @param array<int, array<string, mixed>> $records of one table
     * @return int[]
     */
    private function getPointsInTime(array $records, TcaControlFields $fields, int $now): array
    {
        $pointsInTime = [];
        foreach ($records as $record) {
            $startTime = $fields->getStartTime($record);
            if ($startTime > $now) {
                $pointsInTime[] = $startTime;
            }
            $endTime = $fields->getEndTime($record);
            if ($endTime > $now + self::LOOK_AHEAD) {
                $pointsInTime[] = $endTime;
            }
        }

        return $pointsInTime;
    }

    /**
     * Hidden and deleted records are not fetched at all. Same comparison as
     * StartTimeRestriction and EndTimeRestriction of the frontend, but an
     * endtime within the look-ahead counts as expired already. Without start
     * time field, a record is always started, without end time field, it
     * never ends.
     *
     * @param array<string, mixed> $record
     */
    private function isVisible(array $record, TcaControlFields $fields, int $now): bool
    {
        $endTime = $fields->getEndTime($record);

        return $fields->getStartTime($record) <= $now && ($endTime === 0 || $endTime > $now + self::LOOK_AHEAD);
    }

    /**
     * @param int[] $pointsInTime
     */
    private function getNextPointInTime(array $pointsInTime): int
    {
        return $pointsInTime === [] ? 0 : min($pointsInTime);
    }

    /**
     * Also hidden jobs and jobs with start/endtime are calculated, so their
     * values are correct as soon as they become visible.
     *
     * @param int[] $jobUids
     * @return array<int, array<string, mixed>>
     */
    private function fetchJobs(array $jobUids): array
    {
        if ($jobUids === []) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB, false);
        $result = $queryBuilder
            ->select('uid', 'salary_mode', 'salary_min', 'salary_max')
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->in('uid', $this->createIntArrayParameter($queryBuilder, $jobUids)))
            ->executeQuery();

        return $this->indexByUid($result->fetchAllAssociative());
    }

    /**
     * @param int[] $jobUids
     * @return array<int, int[]> Salary grade UIDs with the job UID as key
     */
    private function fetchSalaryGradeUidsByJob(array $jobUids): array
    {
        if ($jobUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_JOB_SALARY_GRADE_MM);
        $result = $queryBuilder
            ->select('uid_local', 'uid_foreign')
            ->from(self::TABLE_JOB_SALARY_GRADE_MM)
            ->where($queryBuilder->expr()->in('uid_local', $this->createIntArrayParameter($queryBuilder, $jobUids)))
            ->executeQuery();

        $salaryGradeUidsByJob = [];
        while ($row = $result->fetchAssociative()) {
            $salaryGradeUidsByJob[(int)$row['uid_local']][] = (int)$row['uid_foreign'];
        }

        return $salaryGradeUidsByJob;
    }

    /**
     * @param int[] $salaryGradeUids
     * @return array<int, array<string, mixed>>
     */
    private function fetchSalaryGrades(array $salaryGradeUids): array
    {
        if ($salaryGradeUids === []) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder(self::TABLE_SALARY_GRADE, true);
        $result = $queryBuilder
            ->select('uid', 'has_steps', 'flat_amount', ...$this->getControlFields(self::TABLE_SALARY_GRADE)->getTimeFields())
            ->from(self::TABLE_SALARY_GRADE)
            ->where($queryBuilder->expr()->in('uid', $this->createIntArrayParameter($queryBuilder, $salaryGradeUids)))
            ->executeQuery();

        return $this->indexByUid($result->fetchAllAssociative());
    }

    /**
     * Steps of the default language only, like the selected salary grades.
     * All steps, if the table is not language aware.
     *
     * @param int[] $salaryGradeUids
     * @return array<int, array<int, array<string, mixed>>> Salary grade UID as key
     */
    private function fetchSalaryStepsByGrade(array $salaryGradeUids): array
    {
        if ($salaryGradeUids === []) {
            return [];
        }

        $stepFields = $this->getControlFields(self::TABLE_SALARY_STEP);
        $queryBuilder = $this->getQueryBuilder(self::TABLE_SALARY_STEP, true);
        $result = $queryBuilder
            ->select('uid', 'salary_grade', 'amount', ...$stepFields->getTimeFields())
            ->from(self::TABLE_SALARY_STEP)
            ->where(
                $queryBuilder->expr()->in('salary_grade', $this->createIntArrayParameter($queryBuilder, $salaryGradeUids)),
                $this->buildDefaultLanguageConstraint($queryBuilder, $stepFields),
            )
            ->executeQuery();

        $salaryStepsByGrade = [];
        while ($row = $result->fetchAssociative()) {
            $salaryStepsByGrade[(int)$row['salary_grade']][(int)$row['uid']] = $row;
        }

        return $salaryStepsByGrade;
    }

    /**
     * Optional start/end time and language fields of the table, see
     * TcaControlFields
     */
    private function getControlFields(string $table): TcaControlFields
    {
        return TcaControlFields::fromSchema($this->tcaSchemaFactory->get($table));
    }

    /**
     * No constraint (empty string, ignored by QueryBuilder::where()), if the
     * table is not language aware
     */
    private function buildDefaultLanguageConstraint(QueryBuilder $queryBuilder, TcaControlFields $fields): string
    {
        if ($fields->languageField === null) {
            return '';
        }

        return $queryBuilder->expr()->in($fields->languageField, $this->createIntArrayParameter($queryBuilder, [0, -1]));
    }

    /**
     * Start- and endtime are evaluated in PHP (see isVisible()), as their
     * future values are needed for "effective_salary_valid_until".
     */
    private function getQueryBuilder(string $table, bool $excludeHidden): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        if ($excludeHidden) {
            $queryBuilder->getRestrictions()->add(new HiddenRestriction());
        }

        return $queryBuilder;
    }

    /**
     * @param int[] $values
     */
    private function createIntArrayParameter(QueryBuilder $queryBuilder, array $values): string
    {
        return $queryBuilder->createNamedParameter($values, Connection::PARAM_INT_ARRAY);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function indexByUid(array $rows): array
    {
        $indexedRows = [];
        foreach ($rows as $row) {
            $indexedRows[(int)$row['uid']] = $row;
        }

        return $indexedRows;
    }
}
