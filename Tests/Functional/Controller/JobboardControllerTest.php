<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * A job without resolvable salary information must never reach the frontend -
 * this is a legal requirement, not a cosmetic one. Covers every way the salary
 * information behind a job can become unresolvable through TYPO3's own access
 * restrictions (hidden/starttime/endtime on the salary grade or its steps),
 * not just the "editor never filled it in" case, by rendering the real
 * "jobboard_jobboard" content element through a frontend sub-request and
 * checking only the resulting HTML - the same path a visitor's browser goes
 * through, not the controller's internals.
 */
final class JobboardControllerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-core',
        'typo3/cms-frontend',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobVisibilityBySalaryInformation.csv');

        $this->get(SiteWriter::class)->write('main', [
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

        $this->setUpFrontendRootPage(
            1,
            [],
            [
                'config' => 'page = PAGE' . LF
                    . 'page.10 = CONTENT' . LF
                    . 'page.10.table = tt_content' . LF
                    // Matches the default behaviour of a real (production context) TYPO3
                    // instance: an uncaught exception from a content element must not break
                    // the whole page, it is replaced with a generic error message instead.
                    . 'config.contentObjectExceptionHandler = 1',
            ],
        );
        $this->addTypoScriptToTemplateRecord(1, '
            tt_content.jobboard_jobboard = EXTBASEPLUGIN
            tt_content.jobboard_jobboard {
                extensionName = Jobboard
                pluginName = Jobboard
            }
            plugin.tx_jobboard.view {
                templateRootPaths.0 = EXT:jobboard/Resources/Private/Templates/
                partialRootPaths.0 = EXT:jobboard/Resources/Private/Partials/
                partialRootPaths.2 = EXT:maps2/Resources/Private/Partials/
                layoutRootPaths.0 = EXT:jobboard/Resources/Private/Layouts/
            }
            plugin.tx_jobboard.persistence.storagePid = 2
            plugin.tx_jobboard.ignoreFlexFormSettingsIfEmpty = showJobAreaCount
            config.pageTitleProviders.jobboard {
                provider = JWeiland\\Jobboard\\PageTitle\\JobPageTitleProvider
                before = record, seo
            }
        ');
    }

    private function fetchListPageBody(): string
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://example.com/'))->withPageId(1),
        );

        return (string)$response->getBody();
    }

    private function fetchSearchFormPageBody(int $pageId = 4): string
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://example.com/'))->withPageId($pageId),
        );

        return (string)$response->getBody();
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private function fetchSearchActionPageBody(int $pageId, array $arguments = []): string
    {
        $parameters = [
            'tx_jobboard_jobboard[controller]' => 'Jobboard',
            'tx_jobboard_jobboard[action]' => 'search',
        ];
        foreach ($arguments as $argumentName => $value) {
            $parameters['tx_jobboard_jobboard[' . $argumentName . ']'] = (string)$value;
        }

        $request = (new InternalRequest('https://example.com/'))->withPageId($pageId);
        foreach ($parameters as $parameterName => $value) {
            $request = $request->withQueryParameter($parameterName, $value);
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=' . $pageId . '&' . http_build_query($parameters),
        );

        return (string)$this->executeFrontendSubRequest($request->withQueryParameter('cHash', $cHash))->getBody();
    }

    private function assertAppearsBefore(string $expectedFirst, string $expectedSecond, string $haystack): void
    {
        self::assertStringContainsString($expectedFirst, $haystack);
        self::assertStringContainsString($expectedSecond, $haystack);
        self::assertLessThan(
            strpos($haystack, $expectedSecond),
            strpos($haystack, $expectedFirst),
        );
    }

    private function buildDetailActionRequest(int $jobUid): InternalRequest
    {
        $queryString = '&id=1'
            . '&tx_jobboard_jobboard[controller]=Jobboard'
            . '&tx_jobboard_jobboard[action]=detail'
            . '&tx_jobboard_jobboard[job]=' . $jobUid;
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($queryString);

        return (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_jobboard_jobboard[controller]', 'Jobboard')
            ->withQueryParameter('tx_jobboard_jobboard[action]', 'detail')
            ->withQueryParameter('tx_jobboard_jobboard[job]', $jobUid)
            ->withQueryParameter('cHash', $cHash);
    }

    #[Test]
    public function excludesJobWithExpiredSalaryGrade(): void
    {
        self::assertStringNotContainsString(
            'Job with expired grade',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function excludesJobWithHiddenSalaryGrade(): void
    {
        self::assertStringNotContainsString(
            'Job with hidden grade',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function excludesJobWithNotYetStartedSalaryGrade(): void
    {
        self::assertStringNotContainsString(
            'Job with not yet started grade',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function excludesJobWhoseSalaryGradeStepsAreAllExpired(): void
    {
        self::assertStringNotContainsString(
            'Job with all steps expired',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function excludesJobWithoutAnySalaryGradeSelected(): void
    {
        self::assertStringNotContainsString(
            'Job without any salary grade selected',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function excludesFreeEntryJobWithoutAnyAmount(): void
    {
        self::assertStringNotContainsString(
            'Job with free entry but no amount at all',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function includesJobWithVisibleSteppedSalaryGrade(): void
    {
        self::assertStringContainsString(
            'Job with visible stepped grade',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function includesFreeEntryJobWithARange(): void
    {
        self::assertStringContainsString(
            'Job with free entry range',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function includesFreeEntryJobWithASingleAmount(): void
    {
        self::assertStringContainsString(
            'Job with a single free entry amount',
            $this->fetchListPageBody(),
        );
    }

    #[Test]
    public function appliesConfiguredMaxEntriesLimitAfterFilteringOutIneligibleJobs(): void
    {
        // Only 3 jobs are eligible in total (see the fixture): "Job with visible
        // stepped grade", "Job with free entry range" and "Job with a single free
        // entry amount". A limit of 2 must still render exactly 2 of those - never
        // fewer just because ineligible jobs were skipped internally. Page 3 hosts a
        // second plugin instance whose FlexForm sets settings.maxEntries to 2.
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://example.com/'))->withPageId(3),
        );
        $body = (string)$response->getBody();
        $eligibleTitles = [
            'Job with visible stepped grade',
            'Job with free entry range',
            'Job with a single free entry amount',
        ];

        $containedTitles = array_filter(
            $eligibleTitles,
            static fn(string $title): bool => str_contains($body, $title),
        );

        self::assertCount(
            2,
            $containedTitles,
        );
    }

    #[Test]
    public function jobAreaSelectOptionsAreSortedByLabelRegardlessOfUidOrder(): void
    {
        // JobArea uid 1 ("Warehouse") is attached to the job listed before uid 2's
        // job ("Assembly"), so without sorting the options would render in that,
        // alphabetically wrong, order. f:form.select's sortByOptionLabel handles
        // this in Fluid, not JobboardController::getJobAreas().
        $this->assertAppearsBefore('Assembly', 'Warehouse', $this->fetchSearchFormPageBody());
    }

    #[Test]
    public function jobAreaSelectOptionsContainNoNumberOfJobsByDefault(): void
    {
        $body = $this->fetchSearchFormPageBody();

        self::assertStringContainsString('Warehouse', $body);
        self::assertStringNotContainsString('Warehouse (', $body);
    }

    #[Test]
    public function jobAreaSelectOptionsContainNumberOfJobsIfEnabledInFlexForm(): void
    {
        // "Warehouse" is attached to two eligible jobs (uid 1 and 8), "Assembly"
        // to one (uid 7). The ineligible job uid 2 also has "Warehouse", but must
        // not be counted.
        $body = $this->fetchSearchFormPageBody(5);

        self::assertStringContainsString('Warehouse (2)', $body);
        self::assertStringContainsString('Assembly (1)', $body);
    }

    #[Test]
    public function jobAreaSelectOptionsContainNumberOfJobsIfFlexFormIsEmptyAndEnabledInTypoScript(): void
    {
        $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_jobboard.settings.showJobAreaCount = 1');

        self::assertStringContainsString('Warehouse (2)', $this->fetchSearchFormPageBody());
    }

    #[Test]
    public function jobAreaSelectOptionsContainNoNumberOfJobsIfDisabledInFlexFormButEnabledInTypoScript(): void
    {
        $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_jobboard.settings.showJobAreaCount = 1');

        $body = $this->fetchSearchFormPageBody(6);

        self::assertStringContainsString('Warehouse', $body);
        self::assertStringNotContainsString('Warehouse (', $body);
    }

    #[Test]
    public function searchFormContainsUrlOfCurrentPageForAddressSearch(): void
    {
        // address-suggestions.js posts the zip/city suggestion request to this
        // URL. It must be the URL of the current page (site and language
        // resolved by the frontend middleware stack), never a relative
        // "index.php".
        self::assertStringContainsString(
            'data-jobboard-address-search-url="/list-with-search"',
            $this->fetchSearchFormPageBody(),
        );
    }

    #[Test]
    public function listActionOnlyRendersJobsOfJobAreasConfiguredInFlexForm(): void
    {
        $body = $this->fetchSearchFormPageBody(7);

        self::assertStringContainsString('Job with free entry range', $body);
        self::assertStringNotContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('Job with a single free entry amount', $body);
    }

    #[Test]
    public function jobAreaSelectOptionsOnlyContainJobAreasConfiguredInFlexForm(): void
    {
        // The listed job has the job areas "Assembly" and "Office", but the
        // plugin is restricted to "Assembly" only.
        $body = $this->fetchSearchFormPageBody(7);

        self::assertStringContainsString('>Assembly (1)</option>', $body);
        self::assertStringNotContainsString('>Office', $body);
        self::assertStringNotContainsString('>Warehouse', $body);
    }

    #[Test]
    public function jobAreaSelectOptionsContainAllJobAreasOfListedJobsWithoutRestrictionInFlexForm(): void
    {
        $body = $this->fetchSearchFormPageBody(5);

        self::assertStringContainsString('>Office (1)</option>', $body);
    }

    #[Test]
    public function searchActionOnlyRendersJobsOfJobAreasConfiguredInFlexForm(): void
    {
        $body = $this->fetchSearchActionPageBody(7);

        self::assertStringContainsString('Job with free entry range', $body);
        self::assertStringNotContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('Job with a single free entry amount', $body);
        self::assertStringContainsString('>Assembly (1)</option>', $body);
        self::assertStringNotContainsString('>Office', $body);
    }

    #[Test]
    public function searchActionIgnoresJobAreaNotConfiguredInFlexForm(): void
    {
        // "Warehouse" (uid 1) is not allowed on this page. Selecting it must
        // neither reveal its jobs nor narrow down the allowed ones.
        $body = $this->fetchSearchActionPageBody(7, ['jobArea' => 1]);

        self::assertStringContainsString('Job with free entry range', $body);
        self::assertStringNotContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('Job with a single free entry amount', $body);
        self::assertStringNotContainsString('>Warehouse', $body);
    }

    #[Test]
    public function searchActionWithoutRestrictionInFlexFormRendersJobsOfAllJobAreas(): void
    {
        $body = $this->fetchSearchActionPageBody(4);

        self::assertStringContainsString('Job with free entry range', $body);
        self::assertStringContainsString('Job with visible stepped grade', $body);
        self::assertStringContainsString('Job with a single free entry amount', $body);
    }

    #[Test]
    public function searchActionWithoutRestrictionInFlexFormFiltersBySelectedJobArea(): void
    {
        $body = $this->fetchSearchActionPageBody(4, ['jobArea' => 1]);

        self::assertStringContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('Job with free entry range', $body);
    }

    #[Test]
    public function jobRoleSelectOptionsAreSortedByLabelRegardlessOfUidOrder(): void
    {
        $this->assertAppearsBefore('Apprentice', 'Working Student', $this->fetchSearchFormPageBody());
    }

    #[Test]
    public function jobTypeSelectOptionsAreSortedByLabelRegardlessOfUidOrder(): void
    {
        $this->assertAppearsBefore('Full-time', 'Temporary', $this->fetchSearchFormPageBody());
    }

    #[Test]
    public function detailActionRendersJobWithResolvableSalaryInformation(): void
    {
        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Job with visible stepped grade', (string)$response->getBody());
    }

    #[Test]
    public function detailActionRejectsJobWithoutResolvableSalaryInformation(): void
    {
        // JobboardController::detailAction() throws PageNotFoundException, which is not
        // specially routed to an HTTP 404 by TYPO3 for exceptions thrown from within
        // content rendering - it is only caught by the generic content object exception
        // handler, keeping the page itself at 200 while the plugin's own output is
        // replaced. The job must never end up in that output either way. The page title
        // is resolved by JobPageTitleProvider without knowing about this check, so it
        // may still contain the job title. That's accepted, as the job is not rendered.
        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(2));
        $pageBody = explode('</head>', (string)$response->getBody(), 2)[1] ?? '';

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Job with expired grade', $pageBody);
    }

    #[Test]
    public function detailActionHidesApplicationLinkWithoutPidOfApplicationForm(): void
    {
        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertStringNotContainsString('jobuid', (string)$response->getBody());
    }

    #[Test]
    public function detailActionRendersApplicationLinkWithPidOfApplicationForm(): void
    {
        $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_jobboard.settings.pidOfApplicationForm = 1');

        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertStringContainsString('jobuid=1', (string)$response->getBody());
    }

    #[Test]
    public function detailActionRendersTranslatedApplicationLinkLabel(): void
    {
        $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_jobboard.settings.pidOfApplicationForm = 1');

        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertStringContainsString('>Apply online</a>', (string)$response->getBody());
    }

    #[Test]
    public function detailActionRendersTranslatedPdfDownloadLabel(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->update('tx_jobboard_domain_model_job', ['description' => 'Job description'], ['uid' => 1]);

        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertStringContainsString(
            'Download PDF <span class="vhidden-sm">file of the job offer</span>',
            (string)$response->getBody(),
        );
    }

    #[Test]
    public function detailActionSetsJobTitleAsPageTitle(): void
    {
        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertStringContainsString(
            '<title>Job with visible stepped grade</title>',
            (string)$response->getBody(),
        );
    }

    #[Test]
    public function detailActionKeepsJobTitleAsPageTitleOnCachedPageDelivery(): void
    {
        $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));
        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));

        self::assertStringContainsString(
            '<title>Job with visible stepped grade</title>',
            (string)$response->getBody(),
        );
    }

    #[Test]
    public function listActionKeepsPageTitle(): void
    {
        self::assertStringContainsString('<title>Root</title>', $this->fetchListPageBody());
    }

    #[Test]
    public function listActionRendersJobsWithoutMapIfMaps2IsNotInstalled(): void
    {
        // EXT:maps2 is not loaded in this test case. The list must render
        // without the map instead of failing on the missing maps2 partial.
        $body = $this->fetchListPageBody();

        self::assertStringContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('maps2', $body);
    }

    private function setItemsPerPage(int $itemsPerPage): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_jobboard.settings.pageBrowser.itemsPerPage = ' . $itemsPerPage,
        );
    }

    private function fetchListPageBodyOfPage(int $currentPage): string
    {
        $queryString = '&id=1'
            . '&tx_jobboard_jobboard[controller]=Jobboard'
            . '&tx_jobboard_jobboard[action]=list'
            . '&tx_jobboard_jobboard[currentPage]=' . $currentPage;
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($queryString);

        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_jobboard_jobboard[controller]', 'Jobboard')
            ->withQueryParameter('tx_jobboard_jobboard[action]', 'list')
            ->withQueryParameter('tx_jobboard_jobboard[currentPage]', $currentPage)
            ->withQueryParameter('cHash', $cHash);

        return (string)$this->executeFrontendSubRequest($request)->getBody();
    }

    #[Test]
    public function listActionRendersNoPageBrowserIfAllJobsFitOnOnePage(): void
    {
        $body = $this->fetchListPageBody();

        self::assertSame(3, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringNotContainsString('Jobs 1 to', $body);
        self::assertStringNotContainsString('currentPage', $body);
    }

    #[Test]
    public function listActionRendersConfiguredNumberOfJobsPerPage(): void
    {
        $this->setItemsPerPage(2);

        $body = $this->fetchListPageBody();

        self::assertSame(2, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('Jobs 1 to 2 of 3', $body);
        self::assertStringContainsString('tx_jobboard_jobboard%5BcurrentPage%5D=2', $body);
    }

    #[Test]
    public function listActionRendersRemainingJobsOnLastPage(): void
    {
        $this->setItemsPerPage(2);

        $body = $this->fetchListPageBodyOfPage(2);

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('Jobs 3 to 3 of 3', $body);
        self::assertStringContainsString('tx_jobboard_jobboard%5BcurrentPage%5D=1', $body);
    }

    #[Test]
    public function searchActionKeepsSearchArgumentsInPageLinks(): void
    {
        $this->setItemsPerPage(1);

        // Job area "Warehouse" (uid 1) matches two eligible jobs of the fixture
        $body = $this->fetchSearchActionPageBody(4, ['jobArea' => 1]);

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('Jobs 1 to 1 of 2', $body);
        self::assertStringContainsString('tx_jobboard_jobboard%5Baction%5D=search', $body);
        self::assertStringContainsString('tx_jobboard_jobboard%5BjobArea%5D=1', $body);
        self::assertStringContainsString('tx_jobboard_jobboard%5BcurrentPage%5D=2', $body);
    }

    #[Test]
    public function searchActionBuildsSearchOptionsFromAllJobsNotOnlyFromCurrentPage(): void
    {
        $this->setItemsPerPage(1);

        $body = $this->fetchSearchFormPageBody();

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('Warehouse', $body);
        self::assertStringContainsString('Assembly', $body);
        self::assertStringContainsString('Office', $body);
    }
}
