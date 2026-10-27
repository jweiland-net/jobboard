<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\TitleRecordService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class TitleRecordServiceTest extends FunctionalTestCase
{
    private const TABLE = 'tx_jobboard_domain_model_jobarea';

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private TitleRecordService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TitleRecordService.csv');

        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $GLOBALS['BE_USER']->backendCheckLogin();

        $this->subject = $this->get(TitleRecordService::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    #[Test]
    public function findUidByTitleReturnsUidOfDefaultLanguageRecord(): void
    {
        self::assertSame(1, $this->subject->findUidByTitle(self::TABLE, 'General administration'));
    }

    #[Test]
    public function findUidByTitleOfTranslationReturnsUidOfDefaultLanguageRecord(): void
    {
        self::assertSame(1, $this->subject->findUidByTitle(self::TABLE, 'Allgemeine Verwaltung'));
    }

    #[Test]
    public function findUidByTitleFindsHiddenRecord(): void
    {
        self::assertSame(3, $this->subject->findUidByTitle(self::TABLE, 'Hidden area'));
    }

    #[Test]
    public function findUidByTitleIgnoresDeletedRecord(): void
    {
        self::assertSame(0, $this->subject->findUidByTitle(self::TABLE, 'Deleted area'));
    }

    #[Test]
    public function findUidByTitleWithEmptyTitleReturnsZero(): void
    {
        self::assertSame(0, $this->subject->findUidByTitle(self::TABLE, ''));
    }

    #[Test]
    public function getOrCreateUidByTitleReturnsExistingRecord(): void
    {
        self::assertSame(1, $this->subject->getOrCreateUidByTitle(self::TABLE, 'Allgemeine Verwaltung', 2));
        self::assertSame(4, $this->countRecords());
    }

    #[Test]
    public function getOrCreateUidByTitleCreatesRecordOnStoragePage(): void
    {
        $uid = $this->subject->getOrCreateUidByTitle(self::TABLE, 'Bildung/Erziehung', 2);

        $record = $this->get(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE)
            ->select(['pid', 'title', 'sys_language_uid'], self::TABLE, ['uid' => $uid])
            ->fetchAssociative();

        self::assertGreaterThan(4, $uid);
        self::assertSame(['pid' => 2, 'title' => 'Bildung/Erziehung', 'sys_language_uid' => 0], [
            'pid' => (int)$record['pid'],
            'title' => $record['title'],
            'sys_language_uid' => (int)$record['sys_language_uid'],
        ]);
        self::assertSame($uid, $this->subject->getOrCreateUidByTitle(self::TABLE, 'Bildung/Erziehung', 2));
    }

    #[Test]
    public function getOrCreateUidByTitleWithEmptyTitleCreatesNothing(): void
    {
        self::assertSame(0, $this->subject->getOrCreateUidByTitle(self::TABLE, '', 2));
        self::assertSame(4, $this->countRecords());
    }

    private function countRecords(): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder->count('uid')->from(self::TABLE)->executeQuery()->fetchOne();
    }
}
