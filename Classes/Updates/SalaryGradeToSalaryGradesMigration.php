<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Updates;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Moves the former single salary grade of a job (legacy TCA "group" column
 * "salary_grade", storing the grade uid as text) into the MM relation
 * "salary_grades" (table "tx_jobboard_job_salarygrade_mm"), which allows
 * selecting multiple salary grades per job.
 *
 * Jobs which already have MM relations (e.g. edited after the database
 * update, but before this wizard was executed) keep them. In every case the
 * legacy column is emptied afterwards, so each job is only migrated once.
 */
#[UpgradeWizard('jweilandJobboardSalaryGradeToSalaryGradesMigration')]
final readonly class SalaryGradeToSalaryGradesMigration implements UpgradeWizardInterface
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';
    private const TABLE_MM = 'tx_jobboard_job_salarygrade_mm';
    private const LEGACY_COLUMN = 'salary_grade';
    private const MM_COUNT_COLUMN = 'salary_grades';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Migrate the single salary grade of jobs to the new multi-select relation.';
    }

    public function getDescription(): string
    {
        return 'Jobs can now reference multiple salary grades. This wizard moves the formerly selected '
            . 'single salary grade (column "salary_grade") of every job record into the new relation '
            . '"salary_grades" (MM table "' . self::TABLE_MM . '") and empties the old column afterwards.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->hasLegacyColumn() && $this->fetchJobsToMigrate() !== [];
    }

    public function executeUpdate(): bool
    {
        if (!$this->hasLegacyColumn()) {
            return true;
        }

        foreach ($this->fetchJobsToMigrate() as $job) {
            $jobUid = (int)$job['uid'];
            if (!$this->hasMmRelations($jobUid)) {
                $this->insertMmRelations($jobUid, $this->extractSalaryGradeUids((string)$job[self::LEGACY_COLUMN]));
            }

            $this->updateJob($jobUid);
        }

        return true;
    }

    private function hasLegacyColumn(): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_JOB);

        return $connection->createSchemaManager()->introspectTable(self::TABLE_JOB)->hasColumn(self::LEGACY_COLUMN);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchJobsToMigrate(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_JOB);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', self::LEGACY_COLUMN)
            ->from(self::TABLE_JOB)
            ->where(
                $queryBuilder->expr()->isNotNull(self::LEGACY_COLUMN),
                $queryBuilder->expr()->neq(self::LEGACY_COLUMN, $queryBuilder->createNamedParameter('')),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function hasMmRelations(int $jobUid): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_MM);

        return $connection->count('*', self::TABLE_MM, ['uid_local' => $jobUid]) > 0;
    }

    /**
     * The legacy group field stored a comma-separated list of either plain
     * uids ("12") or table-prefixed uids ("tx_jobboard_domain_model_salarygrade_12").
     *
     * @return int[]
     */
    private function extractSalaryGradeUids(string $legacyValue): array
    {
        $uids = [];
        foreach (GeneralUtility::trimExplode(',', $legacyValue, true) as $item) {
            if (preg_match('/(\d+)$/', $item, $matches) === 1 && (int)$matches[1] > 0) {
                $uids[] = (int)$matches[1];
            }
        }

        return array_values(array_unique($uids));
    }

    /**
     * @param int[] $salaryGradeUids
     */
    private function insertMmRelations(int $jobUid, array $salaryGradeUids): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_MM);
        foreach ($salaryGradeUids as $index => $salaryGradeUid) {
            $connection->insert(self::TABLE_MM, [
                'uid_local' => $jobUid,
                'uid_foreign' => $salaryGradeUid,
                'sorting' => $index + 1,
                'sorting_foreign' => 0,
            ]);
        }
    }

    /**
     * Stores the relation count in the local MM column (as DataHandler does)
     * and empties the legacy column.
     */
    private function updateJob(int $jobUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_JOB);
        $connection->update(
            self::TABLE_JOB,
            [
                self::MM_COUNT_COLUMN => $this->connectionPool
                    ->getConnectionForTable(self::TABLE_MM)
                    ->count('*', self::TABLE_MM, ['uid_local' => $jobUid]),
                self::LEGACY_COLUMN => null,
            ],
            ['uid' => $jobUid],
        );
    }
}
