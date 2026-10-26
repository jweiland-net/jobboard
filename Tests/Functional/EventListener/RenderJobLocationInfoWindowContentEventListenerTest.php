<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\EventListener;

use JWeiland\Jobboard\Domain\Model\JobLink;
use JWeiland\Jobboard\Domain\Model\JobLocation;
use JWeiland\Jobboard\EventListener\RenderJobLocationInfoWindowContentEventListener;
use JWeiland\Jobboard\Service\JobLocationServiceInterface;
use JWeiland\Maps2\Event\RenderInfoWindowContentEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Covers the info window of job locations on the map of EXT:maps2:
 * RenderJobLocationInfoWindowContentEventListener (registered by
 * Configuration/Services.php with EXT:maps2 only), JobLocationService,
 * StoragePidResolver, OpenJobRestriction, JobUriBuilder,
 * JobLocationInfoWindowRenderer and Templates/Map/InfoWindow.html.
 *
 * Open jobs of address 1 (POI 1), ordered by title: 2 ("First job in sub
 * folder", sub folder of the storage), 1 ("Second job") and 3 ("Third job
 * without translation"). Jobs 1 and 2 are translated (11, 12) into
 * language 1, job 3 is not. Jobs 4 to 10 are hidden, without salary,
 * expired, ended, without title, outside of the storage or deleted.
 */
final class RenderJobLocationInfoWindowContentEventListenerTest extends FunctionalTestCase
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-core',
        'typo3/cms-frontend',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobLocationInfoWindow.csv');
        $this->writeSite();
    }

    /**
     * @param array<string, mixed> $jobboardSettings
     */
    private function writeSite(
        string $fallbackType = 'fallback',
        array $jobboardSettings = [],
        bool $withRouteEnhancer = false,
    ): void {
        // Settings of config.yaml: the settings of the site are cached by the
        // hash of config.yaml, a changed settings.yaml would not be read again
        $configuration = [
            'rootPageId' => 1,
            'settings' => ['jobboard' => array_replace(['storagePid' => '2'], $jobboardSettings)],
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
                [
                    'languageId' => 1,
                    'title' => 'Deutsch',
                    'navigationTitle' => 'Deutsch',
                    'base' => '/de/',
                    'locale' => 'de_DE.UTF-8',
                    'flag' => 'de',
                    'fallbackType' => $fallbackType,
                    'fallbacks' => '0',
                ],
            ],
        ];
        if ($withRouteEnhancer) {
            $configuration['imports'] = [
                ['resource' => 'EXT:jobboard/Configuration/Routes/Default.yaml'],
            ];
        }

        $this->get(SiteWriter::class)->write('main', $configuration);
    }

    /**
     * Like the request the middleware of EXT:maps2 receives: site, language
     * and page are resolved, but there is no TypoScript and no Extbase request.
     */
    private function buildRequest(int $languageId = 0, int $pageId = 5): ServerRequestInterface
    {
        $site = $this->get(SiteFinder::class)->getSiteByIdentifier('main');

        return (new ServerRequest('https://example.com/', 'POST'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getLanguageById($languageId))
            ->withAttribute('routing', new PageArguments($pageId, '0', []));
    }

    private function renderInfoWindow(
        int $poiCollectionUid,
        ?ServerRequestInterface $request = null,
        string $content = '',
    ): string {
        $event = new RenderInfoWindowContentEvent(
            ['uid' => $poiCollectionUid, 'title' => 'Title of the PoiCollection'],
            $request ?? $this->buildRequest(),
        );
        $event->setInfoWindowContent($content);

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        return $event->getInfoWindowContent();
    }

    private function findJobLocation(int $poiCollectionUid, ?ServerRequestInterface $request = null): ?JobLocation
    {
        return $this->get(JobLocationServiceInterface::class)
            ->findByPoiCollection($poiCollectionUid, $request ?? $this->buildRequest());
    }

    /**
     * @return string[]
     */
    private function getJobTitles(?JobLocation $jobLocation): array
    {
        self::assertInstanceOf(JobLocation::class, $jobLocation);

        return array_map(static fn(JobLink $jobLink): string => $jobLink->getTitle(), $jobLocation->getJobLinks());
    }

    /**
     * @return int[]
     */
    private function getJobUids(?JobLocation $jobLocation): array
    {
        self::assertInstanceOf(JobLocation::class, $jobLocation);

        return array_map(static fn(JobLink $jobLink): int => $jobLink->getUid(), $jobLocation->getJobLinks());
    }

    private function getJobLink(?JobLocation $jobLocation, int $jobUid): JobLink
    {
        self::assertInstanceOf(JobLocation::class, $jobLocation);
        foreach ($jobLocation->getJobLinks() as $jobLink) {
            if ($jobLink->getUid() === $jobUid) {
                return $jobLink;
            }
        }

        self::fail('No link to job ' . $jobUid . ' found.');
    }

    private function addLinkColumn(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE_JOB);
        if (!$connection->createSchemaManager()->introspectTable(self::TABLE_JOB)->hasColumn('link')) {
            $connection->executeStatement(
                'ALTER TABLE ' . self::TABLE_JOB . ' ADD COLUMN link VARCHAR(2048) DEFAULT \'\' NOT NULL',
            );
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function updateJob(int $jobUid, array $values): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_JOB)
            ->update(self::TABLE_JOB, $values, ['uid' => $jobUid]);
    }

    #[Test]
    public function listenerIsRegisteredWithMaps2(): void
    {
        self::assertTrue($this->has(RenderJobLocationInfoWindowContentEventListener::class));
    }

    #[Test]
    public function infoWindowListsOpenJobsOfTheLocation(): void
    {
        $content = $this->renderInfoWindow(1);

        self::assertMatchesRegularExpression('#<h3[^>]*>Example Employer Ltd.</h3>#', $content);
        self::assertStringContainsString('12345 Example City', $content);
        self::assertStringContainsString('3 jobs at this location', $content);
        self::assertSame(3, substr_count($content, '<li'));
        self::assertStringContainsString('>First job in sub folder</a>', $content);
        self::assertStringContainsString('>Second job</a>', $content);
        self::assertStringContainsString('>Third job without translation</a>', $content);
    }

    #[Test]
    public function infoWindowListsJobsWithoutSalaryInformationIfSalaryIsNotRequired(): void
    {
        $this->writeSite('fallback', ['salary' => ['required' => false]]);

        $content = $this->renderInfoWindow(1);

        // Hidden, expired, ended, untitled, outside and deleted jobs stay hidden
        self::assertSame([2, 5, 1, 3], $this->getJobUids($this->findJobLocation(1)));
        self::assertStringContainsString('4 jobs at this location', $content);
        self::assertStringContainsString('>Job without salary information</a>', $content);
    }

    #[Test]
    public function infoWindowListsNoJobsWithoutSalaryInformationIfSalaryIsRequiredExplicitly(): void
    {
        $this->writeSite('fallback', ['salary' => ['required' => true]]);

        self::assertSame([2, 1, 3], $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function locationWithJobsWithoutSalaryInformationOnlyIsShownIfSalaryIsNotRequired(): void
    {
        // Address 3 (POI 2): its only job is open now, but has no salary
        $this->updateJob(13, ['application_deadline' => 0, 'effective_salary_min' => 0, 'effective_salary_max' => 0]);
        self::assertNull($this->findJobLocation(2));
        self::assertSame('', $this->renderInfoWindow(2));

        $this->writeSite('fallback', ['salary' => ['required' => false]]);

        self::assertSame([13], $this->getJobUids($this->findJobLocation(2)));
        self::assertStringContainsString('>Expired job of another employer</a>', $this->renderInfoWindow(2));
    }

    #[Test]
    public function infoWindowRendersStreetZipAndCityAsAddress(): void
    {
        $content = $this->renderInfoWindow(1);

        self::assertSame('Example Street 1', $this->findJobLocation(1)?->getStreet());
        self::assertMatchesRegularExpression(
            '#<address[^>]*>\s*Example Street 1<br>\s*12345 Example City\s*</address>#',
            $content,
        );
    }

    #[Test]
    public function infoWindowRendersAddressWithoutStreetAndLineBreak(): void
    {
        $content = $this->renderInfoWindow(4);

        self::assertSame('', $this->findJobLocation(4)?->getStreet());
        self::assertMatchesRegularExpression(
            '#<address[^>]*>\s*45678 Person City\s*</address>#',
            $content,
        );
        self::assertStringNotContainsString('<br>', $content);
    }

    #[Test]
    public function infoWindowRendersNoAddressWithoutStreetZipAndCity(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_address')->update(
            'tt_address',
            ['address' => '', 'zip' => '', 'city' => ''],
            ['uid' => 5],
        );

        self::assertStringNotContainsString('<address', $this->renderInfoWindow(4));
    }

    #[Test]
    public function infoWindowContainsNoTitleOfThePoiCollection(): void
    {
        self::assertStringNotContainsString('Title of the PoiCollection', $this->renderInfoWindow(1));
    }

    /**
     * @param array<int, string> $titles job UID => title
     */
    private function updateTitles(array $titles): void
    {
        foreach ($titles as $jobUid => $title) {
            $this->updateJob($jobUid, ['title' => $title]);
        }
    }

    #[Test]
    public function jobsAreOrderedByTitle(): void
    {
        self::assertSame([2, 1, 3], $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function infoWindowListsJobsOrderedByTitle(): void
    {
        $this->updateTitles([1 => 'Carpenter', 2 => 'Baker', 3 => 'Accountant']);

        $content = $this->renderInfoWindow(1);

        self::assertLessThan(strpos($content, '>Baker<'), strpos($content, '>Accountant<'));
        self::assertLessThan(strpos($content, '>Carpenter<'), strpos($content, '>Baker<'));
    }

    #[Test]
    public function applicationDeadlineDoesNotAffectTheOrder(): void
    {
        $this->updateJob(2, ['application_deadline' => 4102444800]);
        $this->updateJob(3, ['application_deadline' => 0]);

        self::assertSame([2, 1, 3], $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function orderIsCaseInsensitive(): void
    {
        $this->updateTitles([1 => 'Baker', 2 => 'apprentice', 3 => 'Carpenter']);

        self::assertSame(['apprentice', 'Baker', 'Carpenter'], $this->getJobTitles($this->findJobLocation(1)));
    }

    #[Test]
    public function numbersInTitlesAreOrderedNaturally(): void
    {
        $this->updateTitles([1 => 'Job 10', 2 => 'Job 2', 3 => 'Job 1']);

        self::assertSame(['Job 1', 'Job 2', 'Job 10'], $this->getJobTitles($this->findJobLocation(1)));
    }

    #[Test]
    public function jobsWithEqualTitlesAreOrderedByUid(): void
    {
        $this->updateTitles([1 => 'Same title', 2 => 'same title', 3 => 'Same title']);

        self::assertSame([1, 2, 3], $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function accentsAreNotIgnored(): void
    {
        $this->updateTitles([1 => 'Résumé writer', 2 => 'Resume writer', 3 => 'Résumé writer']);

        self::assertSame([2, 1, 3], $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function translatedJobsAreOrderedByTheirTranslatedTitle(): void
    {
        // English: First (2), Second (1), Third (3)
        self::assertSame(
            ['Erste Stelle im Unterordner', 'Third job without translation', 'Zweite Stelle'],
            $this->getJobTitles($this->findJobLocation(1, $this->buildRequest(1, 8))),
        );
    }

    #[Test]
    public function umlautsAreOrderedLocaleAwareInGerman(): void
    {
        // Translations 11 (of 1) and 12 (of 2), job 3 has no translation
        $this->updateTitles([11 => 'Zahnärztin', 12 => 'Bäcker', 3 => 'Ärztin']);

        self::assertSame(
            ['Ärztin', 'Bäcker', 'Zahnärztin'],
            $this->getJobTitles($this->findJobLocation(1, $this->buildRequest(1, 8))),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notListedJobDataProvider(): array
    {
        return [
            'hidden job' => ['Hidden job'],
            'job without salary information' => ['Job without salary information'],
            'job with passed application deadline' => ['Job with passed application deadline'],
            'job with ended employment' => ['Job with ended employment'],
            'job outside of the storage' => ['Job outside of the storage'],
            'deleted job' => ['Deleted job'],
            'translation of a job' => ['Zweite Stelle'],
        ];
    }

    #[Test]
    #[DataProvider('notListedJobDataProvider')]
    public function infoWindowDoesNotListJobsWhichAreNotListedInTheJobList(string $title): void
    {
        self::assertStringNotContainsString($title, $this->renderInfoWindow(1));
    }

    #[Test]
    public function jobWithoutTitleIsNotListed(): void
    {
        self::assertNotContains(8, $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function jobOnTheDayOfItsApplicationDeadlineIsListed(): void
    {
        $this->updateJob(6, ['application_deadline' => (new \DateTimeImmutable('today'))->getTimestamp()]);

        self::assertContains(6, $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function jobOfAnotherLanguageOnlyIsNotListedInDefaultLanguage(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE_JOB)->insert(self::TABLE_JOB, [
            'uid' => 20,
            'pid' => 2,
            'sys_language_uid' => 1,
            'title' => 'Nur auf Deutsch',
            'address' => 1,
            'effective_salary_min' => 3000,
        ]);

        self::assertSame([2, 1, 3], $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function jobInAllLanguagesIsListed(): void
    {
        $this->updateJob(5, ['sys_language_uid' => -1, 'effective_salary_min' => 3000]);

        self::assertContains(5, $this->getJobUids($this->findJobLocation(1)));
    }

    #[Test]
    public function infoWindowUsesSingularLabelAndNameOfPersonForOneJob(): void
    {
        $content = $this->renderInfoWindow(4);

        self::assertMatchesRegularExpression('#<h3[^>]*>Jane Doe</h3>#', $content);
        self::assertStringContainsString('1 job at this location', $content);
        self::assertStringNotContainsString('jobs at this location', $content);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function poiCollectionWithoutOpenJobsDataProvider(): array
    {
        return [
            'address with expired jobs only' => [2],
            'hidden address' => [3],
            'PoiCollection of another extension' => [5],
            'address with jobs outside of the storage' => [6],
            'deleted address' => [7],
            'PoiCollection does not exist' => [999],
            'no UID' => [0],
        ];
    }

    #[Test]
    #[DataProvider('poiCollectionWithoutOpenJobsDataProvider')]
    public function infoWindowContentStaysEmptyWithoutOpenJobs(int $poiCollectionUid): void
    {
        self::assertSame('', $this->renderInfoWindow($poiCollectionUid));
        self::assertNull($this->findJobLocation($poiCollectionUid));
    }

    #[Test]
    public function infoWindowContentStaysEmptyForTranslatedAddressOnly(): void
    {
        // Only the address of the default language is looked up
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_address')->update(
            'tt_address',
            ['tx_maps2_uid' => 8],
            ['uid' => 2],
        );

        self::assertSame('', $this->renderInfoWindow(8, $this->buildRequest(1)));
    }

    #[Test]
    public function infoWindowContentOfAnotherListenerIsNotOverridden(): void
    {
        self::assertSame(
            '<p>Rendered by another extension</p>',
            $this->renderInfoWindow(1, null, '<p>Rendered by another extension</p>'),
        );
    }

    #[Test]
    public function infoWindowContentStaysEmptyWithoutStoragePid(): void
    {
        $this->writeSite('fallback', ['storagePid' => '0']);

        self::assertSame('', $this->renderInfoWindow(1));
    }

    #[Test]
    public function infoWindowContentStaysEmptyWithoutSiteLanguage(): void
    {
        self::assertSame('', $this->renderInfoWindow(1, $this->buildRequest()->withoutAttribute('language')));
    }

    #[Test]
    public function storagePidOfTheRequestIsIgnored(): void
    {
        // Jobs outside the storage pages of the site setting are never listed,
        // whatever the request contains
        $request = $this->buildRequest()->withParsedBody(['storagePid' => '99'])->withQueryParams(['storagePid' => '99']);

        self::assertSame('', $this->renderInfoWindow(6, $request));
    }

    #[Test]
    public function infoWindowIsTranslatedWithFallbackTypeFallback(): void
    {
        $content = $this->renderInfoWindow(1, $this->buildRequest(1, 8));

        self::assertMatchesRegularExpression('#<h3[^>]*>Beispiel Arbeitgeber GmbH</h3>#', $content);
        self::assertStringContainsString('Beispielstraße 1<br>', $content);
        self::assertStringContainsString('12345 Beispielstadt', $content);
        self::assertStringContainsString('3 Stellen an diesem Standort', $content);
        self::assertStringContainsString('>Erste Stelle im Unterordner</a>', $content);
        self::assertStringContainsString('>Zweite Stelle</a>', $content);
        // No translation: the job of the default language is shown
        self::assertStringContainsString('>Third job without translation</a>', $content);
        self::assertStringNotContainsString('>Second job</a>', $content);
    }

    #[Test]
    public function translatedJobsLinkToTheUidOfTheDefaultLanguage(): void
    {
        // Ordered by the translated titles: "Erste ...", "Third ...", "Zweite ..."
        self::assertSame([2, 3, 1], $this->getJobUids($this->findJobLocation(1, $this->buildRequest(1, 8))));
    }

    #[Test]
    public function singularLabelIsTranslated(): void
    {
        self::assertStringContainsString(
            '1 Stelle an diesem Standort',
            $this->renderInfoWindow(4, $this->buildRequest(1, 8)),
        );
    }

    #[Test]
    public function jobsWithoutTranslationAreSkippedWithFallbackTypeStrict(): void
    {
        $this->writeSite('strict');

        $jobLocation = $this->findJobLocation(1, $this->buildRequest(1, 8));

        self::assertSame(['Erste Stelle im Unterordner', 'Zweite Stelle'], $this->getJobTitles($jobLocation));
    }

    #[Test]
    public function jobsOfTheLanguageAreSelectedWithFallbackTypeFree(): void
    {
        $this->writeSite('free');

        $jobLocation = $this->findJobLocation(1, $this->buildRequest(1, 8));

        self::assertSame(['Erste Stelle im Unterordner', 'Zweite Stelle'], $this->getJobTitles($jobLocation));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function companyOfAddressDataProvider(): array
    {
        return [
            'language 0, fallback type "fallback"' => ['fallback', 0, 'Example Employer Ltd.'],
            'language 0, fallback type "strict"' => ['strict', 0, 'Example Employer Ltd.'],
            'language 0, fallback type "free"' => ['free', 0, 'Example Employer Ltd.'],
            'language 1, fallback type "fallback"' => ['fallback', 1, 'Beispiel Arbeitgeber GmbH'],
            'language 1, fallback type "strict"' => ['strict', 1, 'Beispiel Arbeitgeber GmbH'],
            // Overlaid like Extbase relations (OVERLAYS_MIXED), also without
            // overlays of the jobs
            'language 1, fallback type "free"' => ['free', 1, 'Beispiel Arbeitgeber GmbH'],
        ];
    }

    #[Test]
    #[DataProvider('companyOfAddressDataProvider')]
    public function companyOfAddressIsTranslated(string $fallbackType, int $languageId, string $expectedCompany): void
    {
        $this->writeSite($fallbackType);
        $request = $this->buildRequest($languageId, $languageId === 0 ? 5 : 8);

        self::assertSame($expectedCompany, $this->findJobLocation(1, $request)?->getName());
        self::assertMatchesRegularExpression(
            '#<h3[^>]*>' . preg_quote($expectedCompany, '#') . '</h3>#',
            $this->renderInfoWindow(1, $request),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fallbackTypeDataProvider(): array
    {
        return [
            'fallback type "fallback"' => ['fallback'],
            'fallback type "strict"' => ['strict'],
            'fallback type "free"' => ['free'],
        ];
    }

    /**
     * Like Extbase: an address without translation keeps the default
     * language, the location is still shown (with translated jobs)
     */
    #[Test]
    #[DataProvider('fallbackTypeDataProvider')]
    public function companyOfUntranslatedAddressKeepsDefaultLanguage(string $fallbackType): void
    {
        $this->writeSite($fallbackType);
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_address')->update(
            'tt_address',
            ['deleted' => 1],
            ['uid' => 2],
        );

        $jobLocation = $this->findJobLocation(1, $this->buildRequest(1, 8));

        self::assertSame('Example Employer Ltd.', $jobLocation?->getName());
        self::assertContains('Zweite Stelle', $this->getJobTitles($jobLocation));
    }

    #[Test]
    public function jobLinksPointToDetailActionOnPageOfRequest(): void
    {
        $uri = $this->getJobLink($this->findJobLocation(1), 2)->getUri();

        self::assertStringStartsWith('/map?', $uri);
        self::assertStringContainsString('tx_jobboard_jobboard%5Baction%5D=detail', $uri);
        self::assertStringContainsString('tx_jobboard_jobboard%5Bcontroller%5D=Jobboard', $uri);
        self::assertStringContainsString('tx_jobboard_jobboard%5Bjob%5D=2', $uri);
        self::assertMatchesRegularExpression('/[?&]cHash=[a-f0-9]{32}$/', $uri);
    }

    #[Test]
    public function jobLinksPointToPidOfDetailPage(): void
    {
        $this->writeSite('fallback', ['pidOfDetailPage' => 4]);

        $uri = $this->getJobLink($this->findJobLocation(1), 2)->getUri();

        self::assertStringStartsWith('/job-detail?', $uri);
        self::assertStringContainsString('tx_jobboard_jobboard%5Bjob%5D=2', $uri);
    }

    #[Test]
    public function jobLinksPointToPageOfRequestIfPidOfDetailPageIsZero(): void
    {
        $this->writeSite('fallback', ['pidOfDetailPage' => 0]);

        self::assertStringStartsWith('/map?', $this->getJobLink($this->findJobLocation(1), 2)->getUri());
    }

    #[Test]
    public function jobLinksUseRouteEnhancerOfTheExtension(): void
    {
        $this->writeSite('fallback', ['pidOfDetailPage' => 4], true);

        self::assertSame('/job-detail/first-job-in-sub-folder', $this->getJobLink($this->findJobLocation(1), 2)->getUri());
    }

    #[Test]
    public function jobLinksUseLanguageOfTheRequest(): void
    {
        $this->writeSite('fallback', ['pidOfDetailPage' => 4], true);

        $jobLocation = $this->findJobLocation(1, $this->buildRequest(1, 8));

        self::assertSame('/de/stellendetails/erste-stelle-im-unterordner', $this->getJobLink($jobLocation, 2)->getUri());
    }

    #[Test]
    public function jobLinksWithoutPageAreEmptyAndRenderedAsText(): void
    {
        $request = $this->buildRequest()->withoutAttribute('routing');

        $content = $this->renderInfoWindow(1, $request);

        self::assertSame('', $this->getJobLink($this->findJobLocation(1, $request), 2)->getUri());
        self::assertStringNotContainsString('<a ', $content);
        self::assertStringContainsString('First job in sub folder', $content);
    }

    #[Test]
    public function internalJobLinksAreOpenedInTheSameWindow(): void
    {
        $content = $this->renderInfoWindow(1);

        self::assertStringNotContainsString('target="_blank"', $content);
        self::assertFalse($this->getJobLink($this->findJobLocation(1), 2)->getIsExternal());
    }

    #[Test]
    public function withoutLinkColumnInDatabaseJobsLinkToDetailAction(): void
    {
        // The TCA column "link" is "passthrough" without ext_tables.sql entry
        $jobLink = $this->getJobLink($this->findJobLocation(1), 1);

        self::assertFalse($jobLink->getIsExternal());
        self::assertStringContainsString('tx_jobboard_jobboard%5Bjob%5D=1', $jobLink->getUri());
    }

    #[Test]
    public function externalLinkOfJobIsOpenedInNewWindow(): void
    {
        $this->addLinkColumn();
        $this->updateJob(1, ['link' => 'https://jobs.example.org/apply?id=1&lang=en']);

        $content = $this->renderInfoWindow(1);
        $jobLink = $this->getJobLink($this->findJobLocation(1), 1);

        self::assertTrue($jobLink->getIsExternal());
        self::assertSame('https://jobs.example.org/apply?id=1&lang=en', $jobLink->getUri());
        self::assertMatchesRegularExpression(
            '#<a href="https://jobs\.example\.org/apply\?id=1&amp;lang=en" target="_blank" rel="noopener"[^>]*>Second job</a>#',
            $content,
        );
        // Other jobs still link to the detail action
        self::assertFalse($this->getJobLink($this->findJobLocation(1), 2)->getIsExternal());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noExternalLinkDataProvider(): array
    {
        return [
            'empty link' => [''],
            'whitespace only' => ['   '],
            'javascript URI' => ['javascript:alert(1)'],
            'page link of TYPO3' => ['t3://page?uid=4'],
            'relative URL' => ['/apply'],
            'protocol-relative URL' => ['//jobs.example.org/apply'],
        ];
    }

    #[Test]
    #[DataProvider('noExternalLinkDataProvider')]
    public function linkWhichIsNoHttpUrlIsIgnored(string $link): void
    {
        $this->addLinkColumn();
        $this->updateJob(1, ['link' => $link]);

        $jobLink = $this->getJobLink($this->findJobLocation(1), 1);

        self::assertFalse($jobLink->getIsExternal());
        self::assertStringContainsString('tx_jobboard_jobboard%5Bjob%5D=1', $jobLink->getUri());
    }

    #[Test]
    public function titlesAreEscaped(): void
    {
        $this->updateJob(1, ['title' => '<script>alert(1)</script>']);

        $content = $this->renderInfoWindow(1);

        self::assertStringNotContainsString('<script>', $content);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $content);
    }
}
