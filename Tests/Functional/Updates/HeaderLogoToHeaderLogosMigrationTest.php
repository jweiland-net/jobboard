<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\HeaderLogoToHeaderLogosMigration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class HeaderLogoToHeaderLogosMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private HeaderLogoToHeaderLogosMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/HeaderLogoToHeaderLogosMigration.csv');

        $this->subject = GeneralUtility::makeInstance(
            HeaderLogoToHeaderLogosMigration::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenLegacyFileReferencesExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseOnceMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMovesFileReferencesOfJobsToNewField(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('header_logos', $this->getFieldNameOfFileReference(1));
        self::assertSame('header_logos', $this->getFieldNameOfFileReference(2));
        self::assertSame('header_logos', $this->getFieldNameOfFileReference(4));
        self::assertSame('header_logos', $this->getFieldNameOfFileReference(6));
    }

    #[Test]
    public function executeUpdateKeepsOtherFileReferences(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('employer_logo', $this->getFieldNameOfFileReference(3));
        self::assertSame('header_logo', $this->getFieldNameOfFileReference(5));
    }

    #[Test]
    public function executeUpdateStoresNumberOfNotDeletedFileReferencesIncludingHidden(): void
    {
        $this->subject->executeUpdate();

        self::assertSame(2, $this->getHeaderLogosOfJob(1));
        self::assertSame(0, $this->getHeaderLogosOfJob(2));
        self::assertSame(0, $this->getHeaderLogosOfJob(3));
        self::assertSame(1, $this->getHeaderLogosOfJob(4));
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

    private function getHeaderLogosOfJob(int $jobUid): int
    {
        // Without restrictions, as deleted jobs are migrated, too
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->select('header_logos')
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
