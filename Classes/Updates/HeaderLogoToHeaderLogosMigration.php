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
 * Moves the file references of the former job column "header_logo" to the
 * renamed column "header_logos" (field name in "sys_file_reference") and
 * stores the number of references in the new local column, as DataHandler
 * does.
 *
 * All file references are moved, also deleted ones and those of deleted
 * jobs, so no reference keeps pointing to the old column. The legacy local
 * column is not read: the reference count is recalculated.
 */
#[UpgradeWizard('jweilandJobboardHeaderLogoToHeaderLogosMigration')]
final readonly class HeaderLogoToHeaderLogosMigration implements UpgradeWizardInterface
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';
    private const TABLE_FILE_REFERENCE = 'sys_file_reference';
    private const LEGACY_FIELD = 'header_logo';
    private const FIELD = 'header_logos';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Migrate the header images of jobs to the renamed column "header_logos".';
    }

    public function getDescription(): string
    {
        return 'The column "' . self::LEGACY_FIELD . '" of the jobs was renamed to "' . self::FIELD . '". '
            . 'This wizard moves all file references (header images) of jobs to the new column and '
            . 'updates the number of header images stored in the job records.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->fetchJobUidsToMigrate() !== [];
    }

    public function executeUpdate(): bool
    {
        $jobUids = $this->fetchJobUidsToMigrate();
        if ($jobUids === []) {
            return true;
        }

        $this->connectionPool->getConnectionForTable(self::TABLE_FILE_REFERENCE)->update(
            self::TABLE_FILE_REFERENCE,
            ['fieldname' => self::FIELD],
            ['tablenames' => self::TABLE_JOB, 'fieldname' => self::LEGACY_FIELD],
        );

        foreach ($jobUids as $jobUid) {
            $this->updateJob($jobUid);
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private function fetchJobUidsToMigrate(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FILE_REFERENCE);
        $queryBuilder->getRestrictions()->removeAll();

        $jobUids = $queryBuilder
            ->select('uid_foreign')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE_JOB)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::LEGACY_FIELD)),
            )
            ->groupBy('uid_foreign')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(intval(...), $jobUids);
    }

    /**
     * Stores the number of live, not deleted file references (hidden ones
     * included) in the local column of the job, as DataHandler does.
     */
    private function updateJob(int $jobUid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_JOB)->update(
            self::TABLE_JOB,
            [self::FIELD => $this->countFileReferences($jobUid)],
            ['uid' => $jobUid],
        );
    }

    private function countFileReferences(int $jobUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FILE_REFERENCE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('*')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE_JOB)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::FIELD)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
    }
}
