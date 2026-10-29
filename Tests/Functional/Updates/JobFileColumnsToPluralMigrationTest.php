<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\JobFileColumnsToPluralMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobFileColumnsToPluralMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private JobFileColumnsToPluralMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobFileColumnsToPluralMigration.csv');

        $this->subject = GeneralUtility::makeInstance(
            JobFileColumnsToPluralMigration::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenLegacyFileReferencesExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenOnlyLegacyTenderFilesExist(): void
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->delete('sys_file_reference')
            ->where($queryBuilder->expr()->neq('fieldname', $queryBuilder->createNamedParameter('tender_file')))
            ->executeStatement();

        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseOnceMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function migratedFileReferenceDataProvider(): array
    {
        return [
            'header image' => [1, 'header_logos'],
            'deleted header image' => [2, 'header_logos'],
            'header image of deleted job' => [4, 'header_logos'],
            'hidden header image' => [6, 'header_logos'],
            'employer logo' => [7, 'employer_logos'],
            'deleted employer logo' => [8, 'employer_logos'],
            'employer logo of deleted job' => [9, 'employer_logos'],
            'tender file' => [11, 'tender_files'],
            'hidden tender file' => [12, 'tender_files'],
            'deleted tender file' => [14, 'tender_files'],
            'tender file of deleted job' => [15, 'tender_files'],
            'employer logo of partially migrated job' => [18, 'employer_logos'],
        ];
    }

    #[Test]
    #[DataProvider('migratedFileReferenceDataProvider')]
    public function executeUpdateMovesFileReferencesOfJobsToNewField(int $uid, string $expectedFieldName): void
    {
        $this->subject->executeUpdate();

        self::assertSame($expectedFieldName, $this->getFieldNameOfFileReference($uid));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function keptFileReferenceDataProvider(): array
    {
        return [
            'pdf file of job' => [3, 'pdf_files'],
            'header image of content element' => [5, 'header_logo'],
            'employer logo of content element' => [10, 'employer_logo'],
            'tender file of content element' => [16, 'tender_file'],
            'already migrated employer logo' => [17, 'employer_logos'],
        ];
    }

    #[Test]
    #[DataProvider('keptFileReferenceDataProvider')]
    public function executeUpdateKeepsOtherFileReferences(int $uid, string $expectedFieldName): void
    {
        $this->subject->executeUpdate();

        self::assertSame($expectedFieldName, $this->getFieldNameOfFileReference($uid));
    }

    /**
     * @return array<string, array{int, string, int}>
     */
    public static function numberOfFileReferencesDataProvider(): array
    {
        return [
            'header images of job' => [1, 'header_logos', 2],
            'employer logos of job' => [1, 'employer_logos', 1],
            'tender files of job' => [1, 'tender_files', 3],
            'untouched pdf files of job' => [1, 'pdf_files', 1],
            'deleted header images' => [2, 'header_logos', 0],
            'deleted employer logos' => [2, 'employer_logos', 0],
            'deleted tender files' => [2, 'tender_files', 0],
            'partially migrated employer logos' => [3, 'employer_logos', 2],
            'header images of deleted job' => [4, 'header_logos', 1],
            'employer logos of deleted job' => [4, 'employer_logos', 1],
            'tender files of deleted job' => [4, 'tender_files', 1],
        ];
    }

    #[Test]
    #[DataProvider('numberOfFileReferencesDataProvider')]
    public function executeUpdateStoresNumberOfNotDeletedFileReferencesIncludingHidden(
        int $jobUid,
        string $field,
        int $expectedNumber,
    ): void {
        $this->subject->executeUpdate();

        self::assertSame($expectedNumber, $this->getFieldOfJob($jobUid, $field));
    }

    #[Test]
    public function executeUpdateTwiceKeepsNumberOfFileReferences(): void
    {
        $this->subject->executeUpdate();
        $this->subject->executeUpdate();

        self::assertSame(2, $this->getFieldOfJob(1, 'header_logos'));
        self::assertSame(1, $this->getFieldOfJob(1, 'employer_logos'));
        self::assertSame(3, $this->getFieldOfJob(1, 'tender_files'));
    }

    private function getFieldNameOfFileReference(int $uid): string
    {
        // Without restrictions, as deleted file references are migrated, too
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();

        return (string)$queryBuilder
            ->select('fieldname')
            ->from('sys_file_reference')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function getFieldOfJob(int $jobUid, string $field): int
    {
        // Without restrictions, as deleted jobs are migrated, too
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->select($field)
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
