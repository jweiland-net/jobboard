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
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
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
                templateRootPaths.1 = EXT:maps2/Resources/Private/Templates/
                partialRootPaths.0 = EXT:jobboard/Resources/Private/Partials/
                partialRootPaths.1 = EXT:maps2/Resources/Private/Partials/
                layoutRootPaths.0 = EXT:jobboard/Resources/Private/Layouts/
                layoutRootPaths.1 = EXT:maps2/Resources/Private/Layouts/
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
        // replaced. The job must never end up in that output either way.
        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(2));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Job with expired grade', (string)$response->getBody());
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
}
