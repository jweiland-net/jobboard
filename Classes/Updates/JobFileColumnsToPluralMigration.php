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
 * Moves the file references of the former job columns "header_logo",
 * "employer_logo" and "tender_file" to the renamed plural columns (field
 * name in "sys_file_reference") and stores the number of references in the
 * new local columns, as DataHandler does.
 *
 * All file references are moved, also deleted ones and those of deleted
 * jobs, so no reference keeps pointing to an old column. The legacy local
 * columns are not read: the reference count is recalculated.
 */
#[UpgradeWizard('jweilandJobboardJobFileColumnsToPluralMigration')]
final readonly class JobFileColumnsToPluralMigration implements UpgradeWizardInterface
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';
    private const TABLE_FILE_REFERENCE = 'sys_file_reference';

    /**
     * Renamed file columns of the job (old name => new name)
     */
    private const RENAMED_FIELDS = [
        'header_logo' => 'header_logos',
        'employer_logo' => 'employer_logos',
        'tender_file' => 'tender_files',
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Migrate the files of jobs to the renamed plural columns.';
    }

    public function getDescription(): string
    {
        return 'The job columns "header_logo", "employer_logo" and "tender_file" were renamed to '
            . '"header_logos", "employer_logos" and "tender_files". This wizard moves all file references '
            . '(header images, employer logos, tender documents) of jobs to the new columns and updates the '
            . 'number of files stored in the job records.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        foreach (self::RENAMED_FIELDS as $legacyField => $_) {
            if ($this->fetchJobUidsToMigrate($legacyField) !== []) {
                return true;
            }
        }

        return false;
    }

    public function executeUpdate(): bool
    {
        foreach (self::RENAMED_FIELDS as $legacyField => $field) {
            $this->migrateField($legacyField, $field);
        }

        return true;
    }

    private function migrateField(string $legacyField, string $field): void
    {
        $jobUids = $this->fetchJobUidsToMigrate($legacyField);
        if ($jobUids === []) {
            return;
        }

        $this->connectionPool->getConnectionForTable(self::TABLE_FILE_REFERENCE)->update(
            self::TABLE_FILE_REFERENCE,
            ['fieldname' => $field],
            ['tablenames' => self::TABLE_JOB, 'fieldname' => $legacyField],
        );

        foreach ($jobUids as $jobUid) {
            $this->updateJob($jobUid, $field);
        }
    }

    /**
     * @return list<int>
     */
    private function fetchJobUidsToMigrate(string $legacyField): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FILE_REFERENCE);
        $queryBuilder->getRestrictions()->removeAll();

        $jobUids = $queryBuilder
            ->select('uid_foreign')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE_JOB)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($legacyField)),
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
    private function updateJob(int $jobUid, string $field): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_JOB)->update(
            self::TABLE_JOB,
            [$field => $this->countFileReferences($jobUid, $field)],
            ['uid' => $jobUid],
        );
    }

    private function countFileReferences(int $jobUid, string $field): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FILE_REFERENCE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('*')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE_JOB)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($field)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
    }
}
