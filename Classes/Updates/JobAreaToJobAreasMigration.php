<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Updates;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Moves the former single job area of a job (legacy TCA "selectSingle"
 * column "job_area", storing the job area uid) into the MM relation
 * "job_areas" (table "tx_jobboard_job_jobarea_mm"), which allows selecting
 * multiple job areas per job.
 *
 * Jobs which already have MM relations (e.g. edited after the database
 * update, but before this wizard was executed) keep them. In every case the
 * legacy column is reset to 0 afterwards, so each job is only migrated once.
 *
 * The legacy column is no longer declared in TCA or ext_tables.sql. This
 * wizard migrates existing (test) installations and must run before the
 * database compare drops the old column. Without that column it is silently
 * not necessary.
 */
#[UpgradeWizard('jweilandJobboardJobAreaToJobAreasMigration')]
final readonly class JobAreaToJobAreasMigration implements UpgradeWizardInterface
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';
    private const TABLE_MM = 'tx_jobboard_job_jobarea_mm';
    private const LEGACY_COLUMN = 'job_area';
    private const MM_COUNT_COLUMN = 'job_areas';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Migrate the single job area of jobs to the new multi-select relation.';
    }

    public function getDescription(): string
    {
        return 'Jobs can now reference multiple job areas. This wizard moves the formerly selected '
            . 'single job area (column "job_area") of every job record into the new relation '
            . '"job_areas" (MM table "' . self::TABLE_MM . '") and resets the old column afterwards.';
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
                $this->insertMmRelation($jobUid, (int)$job[self::LEGACY_COLUMN]);
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
                $queryBuilder->expr()->gt(self::LEGACY_COLUMN, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function hasMmRelations(int $jobUid): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_MM);

        return $connection->count('*', self::TABLE_MM, ['uid_local' => $jobUid]) > 0;
    }

    private function insertMmRelation(int $jobUid, int $jobAreaUid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_MM)->insert(self::TABLE_MM, [
            'uid_local' => $jobUid,
            'uid_foreign' => $jobAreaUid,
            'sorting' => 1,
            'sorting_foreign' => 0,
        ]);
    }

    /**
     * Stores the relation count in the local MM column (as DataHandler does)
     * and resets the legacy column.
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
                self::LEGACY_COLUMN => 0,
            ],
            ['uid' => $jobUid],
        );
    }
}
