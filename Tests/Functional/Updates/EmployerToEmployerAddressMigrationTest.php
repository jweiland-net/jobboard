<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\EmployerToEmployerAddressMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class EmployerToEmployerAddressMigrationTest extends FunctionalTestCase
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';
    private const TABLE_JOBFAIR_JOB = 'tx_jobfair2_domain_model_job';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private EmployerToEmployerAddressMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // The legacy columns are neither part of TCA nor of ext_tables.sql
        // anymore, so they have to be created manually, as in a not yet
        // updated database. Non-sqlite DBMS keep manually created tables
        // between test methods, so the jobfair2 table is dropped first.
        $this->getJobConnection()->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE_JOBFAIR_JOB);
        $this->addLegacyColumn('employer', 'VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addLegacyColumn('employer_logos', 'INTEGER DEFAULT 0 NOT NULL');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EmployerToEmployerAddressMigration.csv');

        $this->subject = GeneralUtility::makeInstance(
            EmployerToEmployerAddressMigration::class,
            $this->get(ConnectionPool::class),
            $this->get(TcaSchemaFactory::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenLegacyEmployersExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWithoutLegacyColumnAndLogos(): void
    {
        $this->dropLegacyColumn('employer');
        $this->deleteLogoReferences();

        self::assertFalse($this->subject->updateNecessary());
        self::assertTrue($this->subject->executeUpdate());
    }

    #[Test]
    public function updateNecessaryReturnsTrueWithoutLegacyColumnForLogosOfEmployerAddress(): void
    {
        $this->dropLegacyColumn('employer');

        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseOnceMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateTwiceCreatesNoFurtherAddresses(): void
    {
        $this->subject->executeUpdate();
        $numberOfAddresses = $this->countAddresses();
        $this->subject->executeUpdate();

        self::assertSame($numberOfAddresses, $this->countAddresses());
    }

    #[Test]
    public function executeUpdateCopiesNameIntoEmptyCompanyOfEmployerAddress(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(1, $this->getEmployerAddressUid(1));
        self::assertSame('Copied Name', $this->getAddress(1)['company']);
    }

    #[Test]
    public function executeUpdateKeepsExistingCompanyOfEmployerAddress(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(2, $this->getEmployerAddressUid(2));
        self::assertSame('Existing Company', $this->getAddress(2)['company']);
    }

    #[Test]
    public function executeUpdateRelatesVisibleAddressWithExactlyThisCompanyAndLowestUid(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(3, $this->getEmployerAddressUid(3));
        self::assertSame('Reused Employer', $this->getAddress(3)['company']);
        self::assertSame(2, $this->countAddresses('Reused Employer'));
    }

    /**
     * @return array<string, array{int, string, int}>
     */
    public static function jobWithNameOfNotReusableAddressDataProvider(): array
    {
        return [
            'company in other case' => [4, 'Case Employer', 5],
            'hidden address' => [5, 'Hidden Employer', 6],
            'deleted address' => [6, 'Deleted Employer', 7],
            'address on another page' => [7, 'Other Page Employer', 8],
            'translated address' => [8, 'Translated Employer', 9],
            'workspace address' => [9, 'Workspace Employer', 10],
            'deleted employer address' => [10, 'Deleted Address Employer', 12],
        ];
    }

    #[Test]
    #[DataProvider('jobWithNameOfNotReusableAddressDataProvider')]
    public function executeUpdateCreatesAddressOnPageOfJobWithoutReusableAddress(
        int $jobUid,
        string $name,
        int $notReusableAddressUid,
    ): void {
        $this->subject->executeUpdate();

        $addressUid = $this->getEmployerAddressUid($jobUid);
        $address = $this->getAddress($addressUid);

        self::assertGreaterThan(13, $addressUid);
        self::assertNotSame($notReusableAddressUid, $addressUid);
        self::assertSame($name, $address['company']);
        self::assertSame(2, (int)$address['pid']);
        self::assertSame(0, (int)$address['deleted']);
        self::assertSame(0, (int)$address['hidden']);
        self::assertSame(0, (int)$address['sys_language_uid']);
    }

    #[Test]
    public function executeUpdateCreatesAddressWithTrimmedName(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('Trimmed Employer', $this->getAddress($this->getEmployerAddressUid(11))['company']);
    }

    #[Test]
    public function executeUpdateMigratesHiddenJobsAndJobsOfAllLanguages(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('Hidden Job Employer', $this->getAddress($this->getEmployerAddressUid(16))['company']);
        self::assertSame('All Languages Employer', $this->getAddress($this->getEmployerAddressUid(20))['company']);
    }

    #[Test]
    public function executeUpdateIgnoresDeletedJobs(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(0, $this->getEmployerAddressUid(15));
        self::assertSame(0, $this->countAddresses('Deleted Job Employer'));
    }

    #[Test]
    public function executeUpdateIgnoresJobsWithoutNameAndAddress(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(0, $this->getEmployerAddressUid(12));
        self::assertSame(0, $this->getEmployerAddressUid(21));
        self::assertSame([self::TABLE_JOB, 'employer_logos', 12], $this->getFileReference(1));
    }

    #[Test]
    public function executeUpdateRelatesAddressToTranslationsWithoutOwnEmployerAddress(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(3, $this->getEmployerAddressUid(17));
        self::assertSame(2, $this->getEmployerAddressUid(18));
    }

    #[Test]
    public function executeUpdateIgnoresNameAndLogosOfTranslations(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(0, $this->countAddresses('Translated Name'));
        self::assertSame('Reused Employer', $this->getAddress(3)['company']);
        self::assertSame([self::TABLE_JOB, 'employer_logos', 17], $this->getFileReference(10));
        self::assertSame(0, (int)$this->getAddress(3)['image']);
    }

    #[Test]
    public function executeUpdateMovesLiveLogosToImageOfEmployerAddress(): void
    {
        $this->subject->executeUpdate();

        $addressUid = $this->getEmployerAddressUid(14);
        self::assertSame('New Logo Employer', $this->getAddress($addressUid)['company']);
        self::assertSame(['tt_address', 'image', $addressUid], $this->getFileReference(4));
        self::assertSame(['tt_address', 'image', $addressUid], $this->getFileReference(5));
        self::assertSame(['tt_address', 'image', $addressUid], $this->getFileReference(6));
        self::assertSame(3, (int)$this->getAddress($addressUid)['image']);
        self::assertSame(0, $this->getJobColumn(14, 'employer_logos'));
    }

    #[Test]
    public function executeUpdateKeepsDeletedWorkspaceAndOtherFileReferencesOfJob(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([self::TABLE_JOB, 'employer_logos', 14], $this->getFileReference(7));
        self::assertSame([self::TABLE_JOB, 'employer_logos', 14], $this->getFileReference(8));
        self::assertSame([self::TABLE_JOB, 'header_logos', 14], $this->getFileReference(9));
    }

    #[Test]
    public function executeUpdateMovesLogoToEmployerAddressWithoutCompanyAndName(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(['tt_address', 'image', 13], $this->getFileReference(11));
        self::assertSame(3, $this->getFileReferencePid(11));
        self::assertSame(1, (int)$this->getAddress(13)['image']);
        self::assertSame('', $this->getAddress(13)['company']);
        self::assertSame(0, $this->getJobColumn(19, 'employer_logos'));
    }

    #[Test]
    public function executeUpdateKeepsLogoIfEmployerAddressHasImage(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([self::TABLE_JOB, 'employer_logos', 13], $this->getFileReference(2));
        self::assertSame(['tt_address', 'image', 11], $this->getFileReference(3));
        self::assertSame(1, (int)$this->getAddress(11)['image']);
        self::assertSame(1, $this->getJobColumn(13, 'employer_logos'));
    }

    #[Test]
    public function executeUpdateMovesLogosWithoutLogoCountColumn(): void
    {
        $this->dropLegacyColumn('employer_logos');

        self::assertTrue($this->subject->executeUpdate());

        $addressUid = $this->getEmployerAddressUid(14);
        self::assertSame(['tt_address', 'image', $addressUid], $this->getFileReference(4));
        self::assertSame(3, (int)$this->getAddress($addressUid)['image']);
    }

    /**
     * After JobfairToJobboardMigration, the job table has no column
     * "employer", the name is still in the jobfair2 table.
     */
    #[Test]
    public function executeUpdateReadsNameFromJobfairTableWithoutLegacyColumn(): void
    {
        $this->dropLegacyColumn('employer');
        $connection = $this->getJobConnection();
        $connection->executeStatement(
            'CREATE TABLE ' . self::TABLE_JOBFAIR_JOB . ' (uid INTEGER PRIMARY KEY, employer VARCHAR(255))',
        );
        $connection->insert(self::TABLE_JOBFAIR_JOB, ['uid' => 1, 'employer' => 'Jobfair Employer']);
        $connection->insert(self::TABLE_JOBFAIR_JOB, ['uid' => 3, 'employer' => 'Jobfair Reused Employer']);

        self::assertTrue($this->subject->updateNecessary());
        $this->subject->executeUpdate();

        self::assertSame('Jobfair Employer', $this->getAddress(1)['company']);
        self::assertSame('Jobfair Reused Employer', $this->getAddress($this->getEmployerAddressUid(3))['company']);
        self::assertSame(0, $this->countAddresses('Copied Name'));
        self::assertFalse($this->subject->updateNecessary());
    }

    private function getJobConnection(): Connection
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE_JOB);
    }

    private function hasLegacyColumn(string $column): bool
    {
        return $this->getJobConnection()
            ->createSchemaManager()
            ->introspectTable(self::TABLE_JOB)
            ->hasColumn($column);
    }

    /**
     * Non-sqlite DBMS keep the schema between test methods, so the column
     * may already exist.
     */
    private function addLegacyColumn(string $column, string $definition): void
    {
        if (!$this->hasLegacyColumn($column)) {
            $this->getJobConnection()->executeStatement(
                'ALTER TABLE ' . self::TABLE_JOB . ' ADD COLUMN ' . $column . ' ' . $definition,
            );
        }
    }

    private function dropLegacyColumn(string $column): void
    {
        if ($this->hasLegacyColumn($column)) {
            $this->getJobConnection()->executeStatement(
                'ALTER TABLE ' . self::TABLE_JOB . ' DROP COLUMN ' . $column,
            );
        }
    }

    private function deleteLogoReferences(): void
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->delete('sys_file_reference')
            ->where(
                $queryBuilder->expr()->in(
                    'fieldname',
                    $queryBuilder->createNamedParameter(['employer_logo', 'employer_logos'], Connection::PARAM_STR_ARRAY),
                ),
            )
            ->executeStatement();
    }

    private function getJobColumn(int $jobUid, string $column): int
    {
        // Without restrictions, as hidden and deleted jobs are checked, too
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_JOB);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->select($column)
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function getEmployerAddressUid(int $jobUid): int
    {
        return $this->getJobColumn($jobUid, 'employer_address');
    }

    /**
     * @return array<string, mixed>
     */
    private function getAddress(int $addressUid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tt_address');
        $queryBuilder->getRestrictions()->removeAll();
        $address = $queryBuilder
            ->select('uid', 'pid', 'company', 'deleted', 'hidden', 'sys_language_uid', 'image')
            ->from('tt_address')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($addressUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($address, 'Address ' . $addressUid . ' does not exist');

        return $address;
    }

    private function countAddresses(?string $company = null): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tt_address');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->count('*')->from('tt_address');
        if ($company !== null) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq('company', $queryBuilder->createNamedParameter($company)),
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * @return array{string, string, int}
     */
    private function getFileReference(int $uid): array
    {
        $row = $this->fetchFileReference($uid);

        return [(string)$row['tablenames'], (string)$row['fieldname'], (int)$row['uid_foreign']];
    }

    private function getFileReferencePid(int $uid): int
    {
        return (int)$this->fetchFileReference($uid)['pid'];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFileReference(int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('pid', 'tablenames', 'fieldname', 'uid_foreign')
            ->from('sys_file_reference')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($row, 'File reference ' . $uid . ' does not exist');

        return $row;
    }
}
