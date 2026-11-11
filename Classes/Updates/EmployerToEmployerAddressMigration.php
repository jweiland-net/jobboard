<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Updates;

use JWeiland\Jobboard\Database\Query\TcaControlFields;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Moves the name and the logos of the employer of a job (legacy columns
 * "employer" and "employer_logos") to the employer address (tt_address,
 * relation "employer_address"): the name becomes "tt_address.company", the
 * logos become "tt_address.image".
 *
 * Rules, applied to every not deleted job of the default language (or
 * language "all"), hidden jobs included:
 * - The job has an employer address (not deleted): the name is copied into
 *   its company only if the company is empty. An existing company wins.
 * - The job has no (or a deleted) employer address, but a name: an address
 *   on the page of the job with exactly this company (default language, not
 *   deleted, not hidden, live workspace, lowest uid) is related, otherwise a
 *   new address with this company is created on the page of the job.
 *   Translations of the job without own employer address get the same
 *   address, so the translated detail view shows the employer, too.
 * - The not deleted, live file references of the job in the field
 *   "employer_logos" (or "employer_logo" of EXT:jobfair2, if
 *   JobFileColumnsToPluralMigration has not run yet) are moved to the field
 *   "image" of the employer address, but only if the address has no image
 *   yet. The image count of the address is recalculated, the logo count of
 *   the job is reset (if the column still exists).
 *
 * Not migrated: translated jobs (their own employer names are not
 * transferred, their logos stay), logos of jobs without employer name and
 * address and logos of jobs whose address already has an image. These file
 * references stay at the job, where they are no longer used.
 *
 * The name is read from the legacy column "employer" of the job table. If
 * the jobs were copied from EXT:jobfair2 by JobfairToJobboardMigration, the
 * job table no longer has this column, so the name is read from the column
 * "employer" of "tx_jobfair2_domain_model_job" (same uid) instead. This
 * wizard must run before the database compare drops the legacy column. A
 * migrated job needs no migration anymore, so the wizard is idempotent.
 */
#[UpgradeWizard('jweilandJobboardEmployerToEmployerAddressMigration')]
final readonly class EmployerToEmployerAddressMigration implements UpgradeWizardInterface
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';
    private const TABLE_JOBFAIR_JOB = 'tx_jobfair2_domain_model_job';
    private const TABLE_ADDRESS = 'tt_address';
    private const TABLE_FILE_REFERENCE = 'sys_file_reference';
    private const LEGACY_NAME_COLUMN = 'employer';
    private const LEGACY_LOGO_COLUMN = 'employer_logos';
    private const LEGACY_LOGO_FIELDS = ['employer_logo', 'employer_logos'];
    private const ADDRESS_RELATION_COLUMN = 'employer_address';
    private const ADDRESS_IMAGE_FIELD = 'image';

    public function __construct(
        private ConnectionPool $connectionPool,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Migrate the employer name and logo of jobs to the employer address.';
    }

    public function getDescription(): string
    {
        return 'The name and the logo of the employer are taken from the employer address (tt_address: '
            . 'company and image). This wizard copies the employer name of every job into the company of '
            . 'its employer address (only if empty), relates or creates an address with this company for '
            . 'jobs without employer address, and moves the employer logos to the image of the address '
            . '(only if the address has no image yet). Run it before the database compare removes the '
            . 'column "employer".';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        $legacyNames = $this->fetchLegacyNames();
        foreach ($this->fetchDefaultLanguageJobs() as $job) {
            if ($this->needsMigration($job, $legacyNames[(int)$job['uid']] ?? '')) {
                return true;
            }
        }

        return false;
    }

    public function executeUpdate(): bool
    {
        $legacyNames = $this->fetchLegacyNames();
        foreach ($this->fetchDefaultLanguageJobs() as $job) {
            $name = $legacyNames[(int)$job['uid']] ?? '';
            if ($this->needsMigration($job, $name)) {
                $this->migrateJob($job, $name);
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function needsMigration(array $job, string $name): bool
    {
        $address = $this->fetchAddress($this->getAddressUid($job));
        if ($address === null) {
            return $name !== '';
        }

        if ($name !== '' && trim((string)$address['company']) === '') {
            return true;
        }

        return $this->fetchLogoReferenceUids((int)$job['uid']) !== []
            && $this->countAddressImages((int)$address['uid']) === 0;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function migrateJob(array $job, string $name): void
    {
        $jobUid = (int)$job['uid'];
        $address = $this->fetchAddress($this->getAddressUid($job));
        if ($address === null) {
            $address = $this->findAddressByCompany((int)$job['pid'], $name)
                ?? $this->createAddress((int)$job['pid'], $name);
            $this->assignEmployerAddress($jobUid, (int)$address['uid']);
        } elseif ($name !== '' && trim((string)$address['company']) === '') {
            $this->updateAddress((int)$address['uid'], ['company' => $name]);
        }

        if ($this->countAddressImages((int)$address['uid']) === 0) {
            $this->moveLogos($jobUid, $address);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDefaultLanguageJobs(): array
    {
        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB);
        $queryBuilder
            ->select('uid', 'pid', self::ADDRESS_RELATION_COLUMN)
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid');
        $this->addDefaultLanguageConstraint($queryBuilder, self::TABLE_JOB);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Not empty, trimmed employer names by job uid
     *
     * @return array<int, string>
     */
    private function fetchLegacyNames(): array
    {
        $table = $this->getLegacyNameTable();
        if ($table === null) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder($table);
        $rows = $queryBuilder
            ->select('uid', self::LEGACY_NAME_COLUMN)
            ->from($table)
            ->executeQuery()
            ->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int)$row['uid']] = trim((string)$row[self::LEGACY_NAME_COLUMN]);
        }

        return array_filter($names, static fn(string $name): bool => $name !== '');
    }

    private function getLegacyNameTable(): ?string
    {
        foreach ([self::TABLE_JOB, self::TABLE_JOBFAIR_JOB] as $table) {
            if ($this->hasColumn($table, self::LEGACY_NAME_COLUMN)) {
                return $table;
            }
        }

        return null;
    }

    /**
     * The group field stores the plain uid ("12") or the table-prefixed uid
     * ("tt_address_12").
     *
     * @param array<string, mixed> $job
     */
    private function getAddressUid(array $job): int
    {
        $value = trim((string)($job[self::ADDRESS_RELATION_COLUMN] ?? ''));

        return preg_match('/(\d+)$/', $value, $matches) === 1 ? (int)$matches[1] : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAddress(int $addressUid): ?array
    {
        if ($addressUid <= 0) {
            return null;
        }

        $queryBuilder = $this->getQueryBuilder(self::TABLE_ADDRESS);
        $address = $queryBuilder
            ->select('uid', 'pid', 'company')
            ->from(self::TABLE_ADDRESS)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($addressUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $address === false ? null : $address;
    }

    /**
     * The database may compare case-insensitively, so the exact company is
     * compared in PHP.
     *
     * @return array<string, mixed>|null
     */
    private function findAddressByCompany(int $pid, string $company): ?array
    {
        $queryBuilder = $this->getQueryBuilder(self::TABLE_ADDRESS);
        $queryBuilder
            ->select('uid', 'pid', 'company')
            ->from(self::TABLE_ADDRESS)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('company', $queryBuilder->createNamedParameter($company)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('uid');
        $this->addDefaultLanguageConstraint($queryBuilder, self::TABLE_ADDRESS);

        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $address) {
            if ($address['company'] === $company) {
                return $address;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createAddress(int $pid, string $company): array
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_ADDRESS);
        $connection->insert(self::TABLE_ADDRESS, [
            'pid' => $pid,
            'company' => $company,
            'tstamp' => time(),
            'crdate' => time(),
        ]);

        return ['uid' => (int)$connection->lastInsertId(), 'pid' => $pid, 'company' => $company];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateAddress(int $addressUid, array $values): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_ADDRESS)
            ->update(self::TABLE_ADDRESS, $values, ['uid' => $addressUid]);
    }

    /**
     * Relates the address to the job and to its translations without own
     * employer address.
     */
    private function assignEmployerAddress(int $jobUid, int $addressUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_JOB);
        $connection->update(self::TABLE_JOB, [self::ADDRESS_RELATION_COLUMN => (string)$addressUid], ['uid' => $jobUid]);

        $translationParentField = $this->getControlFields(self::TABLE_JOB)->translationParentField;
        if ($translationParentField === null) {
            return;
        }

        $queryBuilder = $this->getQueryBuilder(self::TABLE_JOB);
        $queryBuilder
            ->update(self::TABLE_JOB)
            ->set(self::ADDRESS_RELATION_COLUMN, (string)$addressUid)
            ->where(
                $queryBuilder->expr()->eq(
                    $translationParentField,
                    $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull(self::ADDRESS_RELATION_COLUMN),
                    $queryBuilder->expr()->in(
                        self::ADDRESS_RELATION_COLUMN,
                        $queryBuilder->createNamedParameter(['', '0'], Connection::PARAM_STR_ARRAY),
                    ),
                ),
            )
            ->executeStatement();
    }

    /**
     * @param array<string, mixed> $address
     */
    private function moveLogos(int $jobUid, array $address): void
    {
        $referenceUids = $this->fetchLogoReferenceUids($jobUid);
        if ($referenceUids === []) {
            return;
        }

        $addressUid = (int)$address['uid'];
        $queryBuilder = $this->getQueryBuilder(self::TABLE_FILE_REFERENCE);
        $queryBuilder
            ->update(self::TABLE_FILE_REFERENCE)
            ->set('tablenames', self::TABLE_ADDRESS)
            ->set('fieldname', self::ADDRESS_IMAGE_FIELD)
            ->set('uid_foreign', $addressUid, true, Connection::PARAM_INT)
            ->set('pid', (int)$address['pid'], true, Connection::PARAM_INT)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($referenceUids, Connection::PARAM_INT_ARRAY),
                ),
            )
            ->executeStatement();

        $this->updateAddress($addressUid, [self::ADDRESS_IMAGE_FIELD => $this->countAddressImages($addressUid)]);
        $this->resetJobLogoCount($jobUid);
    }

    /**
     * Not deleted, live logo references of the job (hidden ones included)
     *
     * @return list<int>
     */
    private function fetchLogoReferenceUids(int $jobUid): array
    {
        $queryBuilder = $this->getQueryBuilder(self::TABLE_FILE_REFERENCE);
        $referenceUids = $queryBuilder
            ->select('uid')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE_JOB)),
                $queryBuilder->expr()->in(
                    'fieldname',
                    $queryBuilder->createNamedParameter(self::LEGACY_LOGO_FIELDS, Connection::PARAM_STR_ARRAY),
                ),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(intval(...), $referenceUids);
    }

    /**
     * Number of not deleted, live image references of the address (hidden
     * ones included), as DataHandler stores it in the local column.
     */
    private function countAddressImages(int $addressUid): int
    {
        $queryBuilder = $this->getQueryBuilder(self::TABLE_FILE_REFERENCE);

        return (int)$queryBuilder
            ->count('*')
            ->from(self::TABLE_FILE_REFERENCE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::TABLE_ADDRESS)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::ADDRESS_IMAGE_FIELD)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($addressUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function resetJobLogoCount(int $jobUid): void
    {
        if (!$this->hasColumn(self::TABLE_JOB, self::LEGACY_LOGO_COLUMN)) {
            return;
        }

        $this->connectionPool
            ->getConnectionForTable(self::TABLE_JOB)
            ->update(self::TABLE_JOB, [self::LEGACY_LOGO_COLUMN => 0], ['uid' => $jobUid]);
    }

    /**
     * Default language and language "all", if the table is language aware
     */
    private function addDefaultLanguageConstraint(QueryBuilder $queryBuilder, string $table): void
    {
        $languageField = $this->getControlFields($table)->languageField;
        if ($languageField === null) {
            return;
        }

        $queryBuilder->andWhere(
            $queryBuilder->expr()->in($languageField, $queryBuilder->createNamedParameter([0, -1], Connection::PARAM_INT_ARRAY)),
        );
    }

    private function getControlFields(string $table): TcaControlFields
    {
        return TcaControlFields::fromSchema($this->tcaSchemaFactory->get($table));
    }

    private function getQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $schemaManager = $this->connectionPool->getConnectionForTable($table)->createSchemaManager();

        return $schemaManager->tablesExist([$table])
            && $schemaManager->introspectTable($table)->hasColumn($column);
    }
}
