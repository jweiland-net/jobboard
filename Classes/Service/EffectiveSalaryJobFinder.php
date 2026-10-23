<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Finds the jobs, whose effective salary has to be recalculated. Plain
 * QueryBuilder (no Extbase), as it is used by a DataHandler hook and a
 * command. Deleted records are ignored, hidden ones are not: their values
 * have to be correct as soon as they are visible again.
 */
readonly class EffectiveSalaryJobFinder
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    private const TABLE_SALARY_GRADE = 'tx_jobboard_domain_model_salarygrade';

    private const TABLE_SALARY_STEP = 'tx_jobboard_domain_model_salarystep';

    private const TABLE_JOB_SALARY_GRADE_MM = 'tx_jobboard_job_salarygrade_mm';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Jobs of the default language (or "all languages"), whose effective
     * salary may change until $now plus the look-ahead: an endtime within
     * the look-ahead is applied already, a starttime is applied as soon as it
     * has passed (the job stays due until then).
     *
     * @return int[]
     */
    public function findDueJobUids(int $now): array
    {
        $now += EffectiveSalaryCalculatorInterface::LOOK_AHEAD;
        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB);
        $queryBuilder
            ->select('uid')
            ->from(self::TABLE_JOB)
            ->where(
                $queryBuilder->expr()->gt(EffectiveSalary::FIELD_VALID_UNTIL, 0),
                $queryBuilder->expr()->lte(
                    EffectiveSalary::FIELD_VALID_UNTIL,
                    $queryBuilder->createNamedParameter($now, Connection::PARAM_INT),
                ),
                $this->getDefaultLanguageConstraint($queryBuilder),
            );

        return $this->fetchUids($queryBuilder);
    }

    /**
     * @return int[]
     */
    public function findAllJobUids(): array
    {
        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB);
        $queryBuilder
            ->select('uid')
            ->from(self::TABLE_JOB)
            ->where($this->getDefaultLanguageConstraint($queryBuilder));

        return $this->fetchUids($queryBuilder);
    }

    /**
     * Maps translated jobs to their job in the default language. The
     * effective salary is always calculated for the default language and
     * copied into the translations by DataHandler (l10n_mode "exclude").
     *
     * @param int[] $jobUids
     * @return int[]
     */
    public function findDefaultLanguageJobUids(array $jobUids): array
    {
        if ($jobUids === []) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB);
        $result = $queryBuilder
            ->select('uid', 'l10n_parent')
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->in('uid', $this->createIntArrayParameter($queryBuilder, $jobUids)))
            ->executeQuery();

        $defaultLanguageJobUids = [];
        while ($row = $result->fetchAssociative()) {
            $defaultLanguageJobUids[] = (int)$row['l10n_parent'] > 0 ? (int)$row['l10n_parent'] : (int)$row['uid'];
        }

        return array_values(array_unique($defaultLanguageJobUids));
    }

    /**
     * Ignores the deleted state of the salary grade itself: the jobs of a
     * deleted salary grade have to be recalculated, too.
     *
     * @param int[] $salaryGradeUids
     * @return int[]
     */
    public function findJobUidsBySalaryGrades(array $salaryGradeUids): array
    {
        if ($salaryGradeUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_JOB_SALARY_GRADE_MM);
        $queryBuilder
            ->select('uid_local')
            ->from(self::TABLE_JOB_SALARY_GRADE_MM)
            ->where($queryBuilder->expr()->in(
                'uid_foreign',
                $this->createIntArrayParameter($queryBuilder, $salaryGradeUids),
            ));

        return $this->fetchUids($queryBuilder);
    }

    /**
     * Includes deleted steps, as deleting a step changes the salary grade.
     *
     * @param int[] $salaryStepUids
     * @return int[]
     */
    public function findSalaryGradeUidsBySalarySteps(array $salaryStepUids): array
    {
        return $this->findParentUids(self::TABLE_SALARY_STEP, 'salary_grade', 'uid', $salaryStepUids);
    }

    /**
     * Deleting a salary table also deletes its salary grades (inline
     * children), without having them in the command map.
     *
     * @param int[] $salaryTableUids
     * @return int[]
     */
    public function findSalaryGradeUidsBySalaryTables(array $salaryTableUids): array
    {
        return $this->findParentUids(self::TABLE_SALARY_GRADE, 'uid', 'salary_table', $salaryTableUids);
    }

    /**
     * Stored values of the jobs and of all their translations, grouped by
     * the job UID of the default language.
     *
     * @param int[] $jobUids UIDs of jobs in the default language
     * @return array<int, EffectiveSalary[]>
     */
    public function findStoredEffectiveSalaries(array $jobUids): array
    {
        if ($jobUids === []) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB);
        $jobUidsParameter = $this->createIntArrayParameter($queryBuilder, $jobUids);
        $result = $queryBuilder
            ->select('uid', 'l10n_parent', ...EffectiveSalary::FIELDS)
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->or(
                $queryBuilder->expr()->in('uid', $jobUidsParameter),
                $queryBuilder->expr()->in('l10n_parent', $jobUidsParameter),
            ))
            ->executeQuery();

        $storedEffectiveSalaries = [];
        while ($row = $result->fetchAssociative()) {
            $jobUid = (int)$row['l10n_parent'] > 0 ? (int)$row['l10n_parent'] : (int)$row['uid'];
            $storedEffectiveSalaries[$jobUid][] = EffectiveSalary::fromRow($row);
        }

        return $storedEffectiveSalaries;
    }

    /**
     * Selects $selectField of all rows of $table, whose $whereField is one
     * of $uids. Includes deleted rows.
     *
     * @param int[] $uids
     * @return int[]
     */
    private function findParentUids(string $table, string $selectField, string $whereField, array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select($selectField)
            ->from($table)
            ->where($queryBuilder->expr()->in($whereField, $this->createIntArrayParameter($queryBuilder, $uids)));

        return $this->fetchUids($queryBuilder);
    }

    private function getDefaultLanguageConstraint(QueryBuilder $queryBuilder): string
    {
        return (string)$queryBuilder->expr()->in(
            'sys_language_uid',
            $this->createIntArrayParameter($queryBuilder, [0, -1]),
        );
    }

    private function getQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

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
     * @return int[]
     */
    private function fetchUids(QueryBuilder $queryBuilder): array
    {
        $uids = array_map(intval(...), $queryBuilder->executeQuery()->fetchFirstColumn());

        return array_values(array_unique(array_filter($uids, static fn(int $uid): bool => $uid > 0)));
    }
}
