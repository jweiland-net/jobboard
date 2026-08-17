<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Updates;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Copies existing rows from the old "tx_jobfair2_domain_model_*" tables (as they existed before the
 * internal rename to "Jobboard") into the new "tx_jobboard_domain_model_*" tables, preserving the
 * original uid of every row so that foreign key relations between these tables (e.g. job -> jobarea)
 * and client-side stored job uids (e.g. "remembered jobs" in localStorage) stay valid.
 *
 * This wizard does not remove the old tables. Once the migration was verified, they can be removed
 * manually via "Admin Tools > Maintenance > Analyze Database Structure > Remove".
 */
#[UpgradeWizard('jweilandJobboardJobfairToJobboardMigration')]
final class JobfairToJobboardMigration implements UpgradeWizardInterface
{
    private const OLD_PREFIX = 'tx_jobfair2_domain_model_';
    private const NEW_PREFIX = 'tx_jobboard_domain_model_';

    /**
     * Order matters only for readability/logging: the job table is migrated last, because it
     * references all other tables. Since uids are copied explicitly (not auto-generated), there is
     * no technical dependency on this order.
     *
     * @var string[]
     */
    private const TABLE_SUFFIXES_IN_ORDER = [
        'jobarea',
        'jobtype',
        'contracttype',
        'benefit',
        'jobrole',
        'tendertype',
        'salarystep',
        'salarygrade',
        'salarytable',
        'job',
    ];

    private const JOB_FAL_FIELDS = ['tender_file', 'pdf_files'];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function getTitle(): string
    {
        return 'Migrate Jobfair2 job data to the renamed Jobboard tables.';
    }

    public function getDescription(): string
    {
        return 'The domain tables of this extension were renamed from "tx_jobfair2_domain_model_*" to '
            . '"tx_jobboard_domain_model_*". This wizard copies all existing rows (including job area, '
            . 'job type, salary and lookup tables, plus attached PDF files) into the new tables, keeping '
            . 'the original uid of every record. Legacy job records that predate the salary feature are '
            . 'set to "free entry" salary mode with an empty salary range, since no salary grade can be '
            . 'derived for them automatically. The old "tx_jobfair2_domain_model_*" tables are not removed '
            . 'by this wizard - once the migration was verified, they can be removed manually via '
            . '"Admin Tools > Maintenance > Analyze Database Structure > Remove".';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        $oldTable = self::OLD_PREFIX . 'job';
        $newTable = self::NEW_PREFIX . 'job';

        if (!$this->tableExists($oldTable) || !$this->tableExists($newTable)) {
            return false;
        }

        return $this->getPendingUids($oldTable, $newTable) !== [];
    }

    public function executeUpdate(): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::NEW_PREFIX . 'job');
        $connection->beginTransaction();

        try {
            foreach (self::TABLE_SUFFIXES_IN_ORDER as $suffix) {
                $oldTable = self::OLD_PREFIX . $suffix;
                $newTable = self::NEW_PREFIX . $suffix;

                if (!$this->tableExists($oldTable) || !$this->tableExists($newTable)) {
                    continue;
                }

                if ($suffix === 'job') {
                    $this->migrateJobTable($oldTable, $newTable);
                } else {
                    $this->migrateTable($oldTable, $newTable);
                }
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return true;
    }

    private function migrateTable(string $oldTable, string $newTable): void
    {
        $pendingUids = $this->getPendingUids($oldTable, $newTable);

        if ($pendingUids === []) {
            return;
        }

        $commonColumns = $this->getCommonColumns($oldTable, $newTable);
        $rows = $this->fetchRows($oldTable, $commonColumns, $pendingUids);

        $connection = $this->connectionPool->getConnectionForTable($newTable);
        foreach ($rows as $row) {
            $connection->insert($newTable, $row);
        }

        $this->fixAutoIncrement($newTable);
    }

    private function migrateJobTable(string $oldTable, string $newTable): void
    {
        $pendingUids = $this->getPendingUids($oldTable, $newTable);

        if ($pendingUids === []) {
            return;
        }

        $oldColumns = $this->getColumns($oldTable);
        $commonColumns = $this->getCommonColumns($oldTable, $newTable);
        $legacyRowsNeedSalaryModeDefault = !isset($oldColumns['salary_mode']);

        if ($legacyRowsNeedSalaryModeDefault) {
            $commonColumns = array_values(array_diff($commonColumns, ['salary_mode', 'salary_min', 'salary_max']));
        }

        $rows = $this->fetchRows($oldTable, $commonColumns, $pendingUids);

        $connection = $this->connectionPool->getConnectionForTable($newTable);
        foreach ($rows as $row) {
            if ($legacyRowsNeedSalaryModeDefault) {
                // Legacy job records predate the salary feature. "1" is "freeEntry", which does not
                // require a salary_grade relation (unlike "0" = "grade"), so migrated records do not
                // end up with an invalid, required-but-empty relation.
                $row['salary_mode'] = 1;
                $row['salary_min'] = '0.00';
                $row['salary_max'] = '0.00';
            }

            $connection->insert($newTable, $row);
        }

        $this->fixAutoIncrement($newTable);
        $this->migrateFileReferences($oldTable, $newTable, self::JOB_FAL_FIELDS);
    }

    private function migrateFileReferences(string $oldTable, string $newTable, array $fieldNames): void
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->update('sys_file_reference')
            ->set('tablenames', $newTable)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($oldTable)),
                $queryBuilder->expr()->in(
                    'fieldname',
                    $queryBuilder->createNamedParameter($fieldNames, Connection::PARAM_STR_ARRAY),
                ),
            )
            ->executeStatement();
    }

    /**
     * @return list<int>
     */
    private function getPendingUids(string $oldTable, string $newTable): array
    {
        $oldQueryBuilder = $this->connectionPool->getQueryBuilderForTable($oldTable);
        $oldQueryBuilder->getRestrictions()->removeAll();
        $oldUids = array_map(
            'intval',
            $oldQueryBuilder->select('uid')->from($oldTable)->executeQuery()->fetchFirstColumn(),
        );

        if ($oldUids === []) {
            return [];
        }

        $newQueryBuilder = $this->connectionPool->getQueryBuilderForTable($newTable);
        $newQueryBuilder->getRestrictions()->removeAll();
        $existingUids = array_map(
            'intval',
            $newQueryBuilder
                ->select('uid')
                ->from($newTable)
                ->where(
                    $newQueryBuilder->expr()->in('uid', $newQueryBuilder->createNamedParameter(
                        $oldUids,
                        Connection::PARAM_INT_ARRAY,
                    )),
                )
                ->executeQuery()
                ->fetchFirstColumn(),
        );

        return array_values(array_diff($oldUids, $existingUids));
    }

    /**
     * @param list<int> $uids
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $table, array $columns, array $uids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select(...$columns)
            ->from($table)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter(
                    $uids,
                    Connection::PARAM_INT_ARRAY,
                )),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<string>
     */
    private function getCommonColumns(string $oldTable, string $newTable): array
    {
        return array_values(array_intersect(
            array_keys($this->getColumns($oldTable)),
            array_keys($this->getColumns($newTable)),
        ));
    }

    /**
     * @return array<string, Column>
     */
    private function getColumns(string $table): array
    {
        $columns = [];
        foreach ($this->connectionPool->getConnectionForTable($table)->createSchemaManager()->listTableColumns($table) as $column) {
            $columns[$column->getName()] = $column;
        }

        return $columns;
    }

    private function tableExists(string $table): bool
    {
        return in_array(
            $table,
            $this->connectionPool->getConnectionForTable($table)->createSchemaManager()->listTableNames(),
            true,
        );
    }

    private function fixAutoIncrement(string $table): void
    {
        $connection = $this->connectionPool->getConnectionForTable($table);

        // Only MySQL/MariaDB use AUTO_INCREMENT this way. Other platforms (e.g. PostgreSQL sequences,
        // SQLite rowid) do not require this manual correction after inserting explicit primary keys.
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $maxUid = (int)$connection->createQueryBuilder()
            ->selectLiteral('MAX(uid) AS maxUid')
            ->from($table)
            ->executeQuery()
            ->fetchOne();

        if ($maxUid > 0) {
            $connection->executeStatement(
                'ALTER TABLE ' . $connection->quoteIdentifier($table) . ' AUTO_INCREMENT = ' . ($maxUid + 1),
            );
        }
    }
}
