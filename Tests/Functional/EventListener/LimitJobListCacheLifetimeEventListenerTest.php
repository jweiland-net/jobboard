<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\EventListener;

use JWeiland\Jobboard\EventListener\LimitJobListCacheLifetimeEventListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * "now" is 2026-01-15 10:00:00 in the default time zone (the same time zone
 * JobRepository uses for "today"). The page cache lifetime passed in is
 * 3 days, so every relevant change is earlier.
 */
final class LimitJobListCacheLifetimeEventListenerTest extends FunctionalTestCase
{
    private const NOW = '2026-01-15 10:00:00';

    private const INITIAL_LIFETIME = 259200;

    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-core',
        'typo3/cms-frontend',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private int $jobUid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LimitJobListCacheLifetime.csv');

        $siteWriter = $this->get(SiteWriter::class);
        $siteWriter->write('main', [
            'rootPageId' => 1,
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'navigationTitle' => 'English',
                    'base' => '/',
                    'locale' => 'en_US.UTF-8',
                    'flag' => 'global',
                ],
            ],
        ]);
        $siteWriter->writeSettings('main', ['jobboard' => ['storagePid' => '2']]);
    }

    private function getTimestamp(string $dateTime): int
    {
        return (new \DateTimeImmutable($dateTime))->getTimestamp();
    }

    /**
     * @param array<string, int|string> $values
     */
    private function insertJob(array $values): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE_JOB)->insert(
            self::TABLE_JOB,
            [
                'uid' => ++$this->jobUid,
                'pid' => 2,
                'title' => 'Job ' . $this->jobUid,
                ...$values,
            ],
        );
    }

    private function getCacheLifetime(int $pageId, int $cacheLifetime = self::INITIAL_LIFETIME): int
    {
        $context = new Context();
        $context->setAspect('date', new DateTimeAspect(new \DateTimeImmutable(self::NOW)));
        $event = new ModifyCacheLifetimeForPageEvent($cacheLifetime, $pageId, [], [], $context);

        $this->get(LimitJobListCacheLifetimeEventListener::class)($event);

        return $event->getCacheLifetime();
    }

    #[Test]
    public function listenerIsRegisteredForEvent(): void
    {
        $listeners = $this->get(ListenerProvider::class)->getAllListenerDefinitions();

        self::assertSame(
            LimitJobListCacheLifetimeEventListener::class,
            $listeners[ModifyCacheLifetimeForPageEvent::class]['jobboard/limitJobListCacheLifetime']['service'] ?? null,
        );
    }

    #[Test]
    public function pageWithoutPluginKeepsCacheLifetime(): void
    {
        $this->insertJob(['application_deadline' => $this->getTimestamp('2026-01-15 00:00:00')]);

        self::assertSame(self::INITIAL_LIFETIME, $this->getCacheLifetime(4));
    }

    #[Test]
    public function pageWithHiddenPluginKeepsCacheLifetime(): void
    {
        $this->insertJob(['application_deadline' => $this->getTimestamp('2026-01-15 00:00:00')]);

        self::assertSame(self::INITIAL_LIFETIME, $this->getCacheLifetime(6));
    }

    #[Test]
    public function pageWithoutJobsKeepsCacheLifetime(): void
    {
        self::assertSame(self::INITIAL_LIFETIME, $this->getCacheLifetime(1));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function nextChangeDataProvider(): array
    {
        return [
            'application deadline today: listed until midnight' => [
                ['application_deadline' => '2026-01-15 00:00:00'],
                '2026-01-16 00:00:00',
            ],
            'application deadline tomorrow: listed until midnight after tomorrow' => [
                ['application_deadline' => '2026-01-16 00:00:00'],
                '2026-01-17 00:00:00',
            ],
            'ending date today: listed until midnight' => [
                ['ending_date' => '2026-01-15 00:00:00'],
                '2026-01-16 00:00:00',
            ],
            'starttime in one hour: job appears' => [
                ['starttime' => '2026-01-15 11:00:00'],
                '2026-01-15 11:00:00',
            ],
            'endtime in two hours: job disappears' => [
                ['endtime' => '2026-01-15 12:00:00'],
                '2026-01-15 12:00:00',
            ],
            'earliest of all points in time' => [
                [
                    'application_deadline' => '2026-01-15 00:00:00',
                    'starttime' => '2026-01-15 18:00:00',
                    'endtime' => '2026-01-15 20:00:00',
                ],
                '2026-01-15 18:00:00',
            ],
        ];
    }

    /**
     * @param array<string, string> $dates
     */
    #[Test]
    #[DataProvider('nextChangeDataProvider')]
    public function cacheLifetimeIsLimitedToNextChangeOfJobs(array $dates, string $expectedEnd): void
    {
        $this->insertJob(array_map($this->getTimestamp(...), $dates));

        self::assertSame(
            $this->getTimestamp($expectedEnd) - $this->getTimestamp(self::NOW),
            $this->getCacheLifetime(1),
        );
    }

    #[Test]
    public function cacheLifetimeUsesEarliestChangeOfAllJobs(): void
    {
        $this->insertJob(['application_deadline' => $this->getTimestamp('2026-01-16 00:00:00')]);
        $this->insertJob(['starttime' => $this->getTimestamp('2026-01-15 13:00:00')]);
        $this->insertJob(['ending_date' => $this->getTimestamp('2026-01-15 00:00:00')]);

        self::assertSame(3 * 3600, $this->getCacheLifetime(1));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function passedPointInTimeDataProvider(): array
    {
        return [
            'application deadline yesterday' => [['application_deadline' => '2026-01-14 00:00:00']],
            'ending date yesterday' => [['ending_date' => '2026-01-14 00:00:00']],
            'starttime passed' => [['starttime' => '2026-01-15 09:00:00']],
            'endtime is now' => [['endtime' => '2026-01-15 10:00:00']],
        ];
    }

    /**
     * @param array<string, string> $dates
     */
    #[Test]
    #[DataProvider('passedPointInTimeDataProvider')]
    public function passedPointsInTimeAreIgnored(array $dates): void
    {
        $this->insertJob(array_map($this->getTimestamp(...), $dates));

        self::assertSame(self::INITIAL_LIFETIME, $this->getCacheLifetime(1));
    }

    #[Test]
    public function shorterCacheLifetimeIsKept(): void
    {
        $this->insertJob(['application_deadline' => $this->getTimestamp('2026-01-15 00:00:00')]);

        self::assertSame(600, $this->getCacheLifetime(1, 600));
    }

    /**
     * @return array<string, array{array<string, int>}>
     */
    public static function ignoredJobDataProvider(): array
    {
        return [
            'hidden job' => [['hidden' => 1]],
            'deleted job' => [['deleted' => 1]],
            'translated job' => [['sys_language_uid' => 1, 'l10n_parent' => 1]],
            'job outside of the storage' => [['pid' => 99]],
        ];
    }

    /**
     * @param array<string, int> $values
     */
    #[Test]
    #[DataProvider('ignoredJobDataProvider')]
    public function jobsWhichAreNotListedAreIgnored(array $values): void
    {
        $this->insertJob([...$values, 'application_deadline' => $this->getTimestamp('2026-01-15 00:00:00')]);

        self::assertSame(self::INITIAL_LIFETIME, $this->getCacheLifetime(1));
    }

    #[Test]
    public function jobsInSubFolderOfSiteStorageAreConsidered(): void
    {
        $this->insertJob(['pid' => 3, 'starttime' => $this->getTimestamp('2026-01-15 11:00:00')]);

        self::assertSame(3600, $this->getCacheLifetime(1));
    }

    #[Test]
    public function storageOfPluginIsUsedInsteadOfSiteStorage(): void
    {
        $this->insertJob(['pid' => 2, 'starttime' => $this->getTimestamp('2026-01-15 11:00:00')]);
        $this->insertJob(['pid' => 8, 'starttime' => $this->getTimestamp('2026-01-15 12:00:00')]);
        $this->insertJob(['pid' => 7, 'starttime' => $this->getTimestamp('2026-01-15 13:00:00')]);

        // Page 5: storage page 7 without sub folders
        self::assertSame(3 * 3600, $this->getCacheLifetime(5));
    }
}
