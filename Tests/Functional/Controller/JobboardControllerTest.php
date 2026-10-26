<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Controller;

use JWeiland\Jobboard\Controller\JobboardController;
use JWeiland\Jobboard\Middleware\RememberJobMiddleware;
use JWeiland\Jobboard\Service\EffectiveSalaryUpdaterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationChangedEvent;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
 *
 * The fixture contains no effective salary at all: setUp() calculates it
 * with EffectiveSalaryUpdater (like the command "jobboard:salary:update
 * --all"), so the visibility tests cover calculator, updater and the SQL
 * filter of JobRepository end-to-end. Listed jobs: 1, 7 and 8.
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

    /**
     * The testing framework disables the page cache (NullBackend). List and
     * detail are cached in production, so the page cache is enabled here:
     * remembered jobs must never end up in a cached page.
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobVisibilityBySalaryInformation.csv');
        $this->withBackendUser(
            fn(): int => $this->get(EffectiveSalaryUpdaterInterface::class)->updateJobs(range(1, 9)),
        );

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
        // Storage of the jobs, which can be remembered (RememberJobMiddleware)
        $siteWriter->writeSettings('main', ['jobboard' => ['storagePid' => '2']]);
        // A previous test may have changed settings.yaml (writeSalaryRequired()),
        // but the site settings are cached by the hash of config.yaml only
        $this->get(CacheManager::class)->getCache('core')->flush();

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
            plugin.tx_jobboard.ignoreFlexFormSettingsIfEmpty = showJobAreaCount, pageBrowser.enable
            config.pageTitleProviders.jobboard {
                provider = JWeiland\\Jobboard\\PageTitle\\JobPageTitleProvider
                before = record, seo
            }
        ');
    }

    /**
     * DataHandler needs a backend user, the frontend requests must not have
     * one (no preview of hidden records).
     */
    private function withBackendUser(callable $callable): void
    {
        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $GLOBALS['BE_USER']->backendCheckLogin();
        try {
            $callable();
        } finally {
            unset($GLOBALS['BE_USER']);
        }
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     */
    private function processDataMap(array $dataMap): void
    {
        $this->withBackendUser(static function () use ($dataMap): void {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap, []);
            $dataHandler->process_datamap();
        });
    }

    /**
     * Site setting "jobboard.salary.required". Site settings are cached by the
     * hash of config.yaml: a changed settings.yaml is only read again after
     * flushing the core cache. The page cache is flushed as well, like a
     * change of the site settings in the backend would require.
     */
    private function writeSalaryRequired(bool $salaryRequired): void
    {
        $this->get(SiteWriter::class)->writeSettings('main', [
            'jobboard' => ['storagePid' => '2', 'salary' => ['required' => $salaryRequired]],
        ]);
        $this->get(CacheManager::class)->getCache('core')->flush();
        $this->get(CacheManager::class)->getCache('pages')->flush();
        $this->get(EventDispatcherInterface::class)->dispatch(new SiteConfigurationChangedEvent('main'));
    }

    /**
     * Raw SQL: neither the hook nor any cache clearing is triggered
     *
     * @param array<string, mixed> $values
     */
    private function updateRecordWithoutDataHandler(string $table, int $uid, array $values): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable($table)->update($table, $values, ['uid' => $uid]);
    }

    private function fetchRememberedJobsPageBody(string $sessionCookie = ''): string
    {
        $queryString = '&id=1'
            . '&tx_jobboard_jobboard[controller]=Jobboard'
            . '&tx_jobboard_jobboard[action]=rememberedJobs';
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($queryString);

        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_jobboard_jobboard[controller]', 'Jobboard')
            ->withQueryParameter('tx_jobboard_jobboard[action]', 'rememberedJobs')
            ->withQueryParameter('cHash', $cHash);

        return (string)$this->executeFrontendSubRequest($this->withSession($request, $sessionCookie))->getBody();
    }

    private function fetchListPageBody(string $sessionCookie = ''): string
    {
        $request = (new InternalRequest('https://example.com/'))->withPageId(1);

        return (string)$this->executeFrontendSubRequest($this->withSession($request, $sessionCookie))->getBody();
    }

    private function withSession(InternalRequest $request, string $sessionCookie): InternalRequest
    {
        return $sessionCookie === '' ? $request : $request->withCookieParams(['fe_typo_user' => $sessionCookie]);
    }

    /**
     * Remembers the jobs through RememberJobMiddleware like the browser does
     * and returns the cookie of the TYPO3 frontend user session.
     */
    private function rememberJobs(int ...$jobUids): string
    {
        $sessionCookie = '';
        foreach ($jobUids as $jobUid) {
            $request = (new InternalRequest('https://example.com/'))
                ->withMethod('POST')
                ->withHeader(RememberJobMiddleware::HEADER_NAME, '1')
                ->withParsedBody(['job' => (string)$jobUid, 'remember' => '1']);
            $response = $this->executeFrontendSubRequest($this->withSession($request, $sessionCookie));
            self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
            if (preg_match('/^fe_typo_user=([^;]+)/', $response->getHeaderLine('Set-Cookie'), $matches) === 1) {
                $sessionCookie = $matches[1];
            }
        }

        return $sessionCookie;
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
    private function fetchSearchActionPageBody(int $pageId, array $arguments = [], string $sessionCookie = ''): string
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

        $request = $this->withSession($request->withQueryParameter('cHash', $cHash), $sessionCookie);

        return (string)$this->executeFrontendSubRequest($request)->getBody();
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

    /**
     * @return array<string, array{int, string}>
     */
    public static function jobWithoutValidSalaryInformationDataProvider(): array
    {
        return [
            'expired salary grade' => [2, 'Job with expired grade'],
            'hidden salary grade' => [3, 'Job with hidden grade'],
            'not yet started salary grade' => [4, 'Job with not yet started grade'],
            'salary grade with all steps expired' => [5, 'Job with all steps expired'],
            'no salary grade selected' => [6, 'Job without any salary grade selected'],
            'free entry without any amount' => [9, 'Job with free entry but no amount at all'],
        ];
    }

    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function updaterStoresNoEffectiveSalaryForJobWithoutValidSalaryInformation(int $jobUid, string $title): void
    {
        $row = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->select(['effective_salary_min', 'effective_salary_max'], 'tx_jobboard_domain_model_job', ['uid' => $jobUid])
            ->fetchAssociative();

        self::assertSame(
            [0.0, 0.0],
            [(float)$row['effective_salary_min'], (float)$row['effective_salary_max']],
            $title,
        );
    }

    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function listActionExcludesJobWithoutValidSalaryInformation(int $jobUid, string $title): void
    {
        $body = $this->fetchListPageBody();

        self::assertStringNotContainsString($title, $body);
        self::assertStringNotContainsString('data-jobboard-uid="' . $jobUid . '"', $body);
    }

    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function searchActionExcludesJobWithoutValidSalaryInformation(int $jobUid, string $title): void
    {
        self::assertStringNotContainsString($title, $this->fetchSearchActionPageBody(4));
    }

    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function listActionIncludesJobWithoutValidSalaryInformationIfSalaryIsNotRequired(int $jobUid, string $title): void
    {
        $this->writeSalaryRequired(false);

        $body = $this->fetchListPageBody();

        self::assertStringContainsString($title, $body);
        self::assertStringContainsString('data-jobboard-uid="' . $jobUid . '"', $body);
    }

    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function searchActionIncludesJobWithoutValidSalaryInformationIfSalaryIsNotRequired(int $jobUid, string $title): void
    {
        $this->writeSalaryRequired(false);

        self::assertStringContainsString($title, $this->fetchSearchActionPageBody(4));
    }

    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function listActionExcludesJobWithoutValidSalaryInformationIfSalaryIsRequiredExplicitly(
        int $jobUid,
        string $title,
    ): void {
        $this->writeSalaryRequired(true);

        self::assertStringNotContainsString($title, $this->fetchListPageBody());
    }

    #[Test]
    public function listActionWithoutSalaryRequirementKeepsAllOtherRules(): void
    {
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 9, ['application_deadline' => 946684800]);
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 6, ['hidden' => 1]);
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 5, ['title' => '']);
        $this->writeSalaryRequired(false);

        $body = $this->fetchListPageBody();

        self::assertStringNotContainsString('data-jobboard-uid="9"', $body);
        self::assertStringNotContainsString('data-jobboard-uid="6"', $body);
        self::assertStringNotContainsString('data-jobboard-uid="5"', $body);
        self::assertStringContainsString('data-jobboard-uid="4"', $body);
    }

    #[Test]
    public function appliesMaxEntriesLimitToJobsWithoutSalaryInformationIfSalaryIsNotRequired(): void
    {
        $this->writeSalaryRequired(false);

        // Page 3 hosts a plugin with settings.maxEntries = 2: jobs 1 and 2
        $body = $this->fetchSearchFormPageBody(3);

        self::assertSame(2, substr_count($body, 'data-jobboard-uid="'));
        $this->assertAppearsBefore('data-jobboard-uid="1"', 'data-jobboard-uid="2"', $body);
    }

    #[Test]
    public function hidingSalaryGradeInBackendRemovesJobFromCachedList(): void
    {
        // Cache the list first
        self::assertStringContainsString('Job with visible stepped grade', $this->fetchListPageBody());

        // The hook recalculates job 1 and DataHandler clears the page cache
        $this->processDataMap(['tx_jobboard_domain_model_salarygrade' => [1 => ['hidden' => 1]]]);

        $body = $this->fetchListPageBody();
        self::assertStringNotContainsString('Job with visible stepped grade', $body);
        self::assertStringContainsString('Job with free entry range', $body);
    }

    #[Test]
    public function addingAmountInBackendAddsJobToCachedList(): void
    {
        // A job, which was not listed, has no cache tag on the list page: the
        // documented TCEMAIN.clearCacheCmd of the storage folder clears it
        $this->updateRecordWithoutDataHandler('pages', 2, ['TSconfig' => 'TCEMAIN.clearCacheCmd = 1']);
        self::assertStringNotContainsString('Job with free entry but no amount at all', $this->fetchListPageBody());

        $this->processDataMap(['tx_jobboard_domain_model_job' => [9 => ['salary_min' => '1800.00']]]);

        self::assertStringContainsString('Job with free entry but no amount at all', $this->fetchListPageBody());
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
    public function appliesConfiguredMaxEntriesLimitInDatabaseAfterSalaryFilter(): void
    {
        // Page 3 hosts a plugin with settings.maxEntries = 2. Jobs 2 to 6 come
        // before job 7 in the default order (uid), but have no effective salary:
        // the limit must still render the first 2 listed jobs, 1 and 7.
        $body = $this->fetchSearchFormPageBody(3);

        self::assertSame(2, substr_count($body, 'data-jobboard-uid="'));
        $this->assertAppearsBefore('data-jobboard-uid="1"', 'data-jobboard-uid="7"', $body);
        self::assertStringNotContainsString('data-jobboard-uid="8"', $body);
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
    public function detailActionRejectsJobWithoutResolvableSalaryInformationIfSalaryIsRequiredExplicitly(): void
    {
        $this->writeSalaryRequired(true);

        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest(2));
        $pageBody = explode('</head>', (string)$response->getBody(), 2)[1] ?? '';

        self::assertStringNotContainsString('Job with expired grade', $pageBody);
    }

    /**
     * Without salary information, no salary is rendered at all: neither the
     * salary of the job details, nor the salary section, nor "baseSalary" of
     * the structured data.
     */
    #[Test]
    #[DataProvider('jobWithoutValidSalaryInformationDataProvider')]
    public function detailActionRendersJobWithoutSalaryInformationIfSalaryIsNotRequired(int $jobUid, string $title): void
    {
        $this->writeSalaryRequired(false);

        $response = $this->executeFrontendSubRequest($this->buildDetailActionRequest($jobUid));
        $body = (string)$response->getBody();
        $pageBody = explode('</head>', $body, 2)[1] ?? '';

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h1', $pageBody);
        self::assertStringContainsString($title, $pageBody);
        self::assertStringNotContainsString('<dt>Salary</dt>', $pageBody);
        self::assertStringNotContainsString('>Salary</h2>', $pageBody);
        self::assertStringNotContainsString('€', $pageBody);
        self::assertStringNotContainsString('baseSalary', $body);
    }

    #[Test]
    public function detailActionRendersSalaryOfJobWithSalaryInformationIfSalaryIsNotRequired(): void
    {
        $this->writeSalaryRequired(false);

        $body = (string)$this->executeFrontendSubRequest($this->buildDetailActionRequest(7))->getBody();

        self::assertStringContainsString('<dt>Salary</dt>', $body);
        self::assertStringContainsString('>Salary</h2>', $body);
        self::assertStringContainsString('baseSalary', $body);
    }

    /**
     * Job 7 has salary information, job 9 has none
     */
    private function fetchDetailPageBodyWithRelatedJobs(): string
    {
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 1, ['related_jobs' => '7,9']);
        $body = (string)$this->executeFrontendSubRequest($this->buildDetailActionRequest(1))->getBody();

        return explode('</head>', $body, 2)[1] ?? '';
    }

    #[Test]
    public function detailActionLinksRelatedJobsWithSalaryInformationOnlyIfSalaryIsRequired(): void
    {
        $pageBody = $this->fetchDetailPageBodyWithRelatedJobs();

        self::assertStringContainsString('>Related jobs</h2>', $pageBody);
        self::assertStringContainsString('>Job with free entry range</a>', $pageBody);
        self::assertStringNotContainsString('Job with free entry but no amount at all', $pageBody);
    }

    #[Test]
    public function detailActionLinksAllRelatedJobsIfSalaryIsNotRequired(): void
    {
        $this->writeSalaryRequired(false);

        $pageBody = $this->fetchDetailPageBodyWithRelatedJobs();

        self::assertStringContainsString('>Job with free entry range</a>', $pageBody);
        self::assertStringContainsString('>Job with free entry but no amount at all</a>', $pageBody);
    }

    #[Test]
    public function detailActionRendersNoRelatedJobsSectionIfNoRelatedJobIsLinked(): void
    {
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 1, ['related_jobs' => '9']);

        $body = (string)$this->executeFrontendSubRequest($this->buildDetailActionRequest(1))->getBody();

        self::assertStringNotContainsString('>Related jobs</h2>', $body);
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
            'Download PDF <span class="d-none d-sm-inline">file of the job offer</span>',
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

    #[Test]
    public function listActionRendersNoMapAlertIfMaps2IsNotInstalled(): void
    {
        // Without EXT:maps2 a missing geo position is no configuration error
        $body = $this->fetchSearchFormPageBody();

        self::assertStringContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('alert alert-warning', $body);
        self::assertStringNotContainsString('The map cannot be shown', $body);
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

    #[Test]
    public function listActionContainsUrlOfCurrentPageForRememberedJobs(): void
    {
        self::assertStringContainsString(
            'data-jobboard-remember-url="/list-with-search"',
            $this->fetchSearchFormPageBody(),
        );
    }

    #[Test]
    public function listActionRendersAllJobsIfPageBrowserIsDisabledByTypoScript(): void
    {
        $this->setItemsPerPage(1);
        $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_jobboard.settings.pageBrowser.enable = 0');

        $body = $this->fetchListPageBody();

        self::assertSame(3, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringNotContainsString('currentPage', $body);
    }

    #[Test]
    public function listActionRendersAllJobsIfPageBrowserIsHiddenByContentElement(): void
    {
        $this->setItemsPerPage(1);

        $body = $this->fetchSearchFormPageBody(8);

        self::assertSame(3, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringNotContainsString('currentPage', $body);
    }

    #[Test]
    public function listActionRendersPageBrowserIfContentElementOverridesDisabledTypoScript(): void
    {
        $this->setItemsPerPage(1);
        $this->addTypoScriptToTemplateRecord(1, 'plugin.tx_jobboard.settings.pageBrowser.enable = 0');

        $body = $this->fetchSearchFormPageBody(9);

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('Jobs 1 to 1 of 3', $body);
    }

    #[Test]
    public function listActionRendersNoPageBrowserIfMaxEntriesIsSet(): void
    {
        // Page 3 limits the list to 2 jobs ("maxEntries")
        $this->setItemsPerPage(1);

        $body = $this->fetchSearchFormPageBody(3);

        self::assertSame(2, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringNotContainsString('currentPage', $body);
    }

    #[Test]
    public function pluginConfigurationCachesListAndDetailOnly(): void
    {
        $controllerConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['Jobboard']['plugins']
            ['Jobboard']['controllers'][JobboardController::class];

        self::assertSame(['list', 'search', 'detail', 'rememberedJobs'], $controllerConfiguration['actions']);
        self::assertSame(['search', 'rememberedJobs'], $controllerConfiguration['nonCacheableActions']);
    }

    #[Test]
    public function listActionIsCached(): void
    {
        $this->fetchListPageBody();
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 7, ['title' => 'Changed title']);

        $body = $this->fetchListPageBody();

        self::assertStringContainsString('Job with free entry range', $body);
        self::assertStringNotContainsString('Changed title', $body);
    }

    #[Test]
    public function detailActionIsCached(): void
    {
        $this->executeFrontendSubRequest($this->buildDetailActionRequest(1));
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 1, ['title' => 'Changed title']);

        $body = (string)$this->executeFrontendSubRequest($this->buildDetailActionRequest(1))->getBody();

        self::assertStringContainsString('Job with visible stepped grade', $body);
        self::assertStringNotContainsString('Changed title', $body);
    }

    #[Test]
    public function searchActionIsNotCached(): void
    {
        $this->fetchSearchActionPageBody(4);
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 7, ['title' => 'Changed title']);

        self::assertStringContainsString('Changed title', $this->fetchSearchActionPageBody(4));
    }

    #[Test]
    public function listActionRendersNoVisitorSpecificMarkupForVisitorWithRememberedJobs(): void
    {
        // The visitor with remembered jobs renders the page first, so it
        // would end up in the page cache for every other visitor
        $sessionCookie = $this->rememberJobs(8);
        $bodyOfVisitorWithRememberedJobs = $this->fetchListPageBody($sessionCookie);
        $bodyOfOtherVisitor = $this->fetchListPageBody();

        self::assertSame($bodyOfOtherVisitor, $bodyOfVisitorWithRememberedJobs);
        self::assertStringNotContainsString('highlight-row', $bodyOfVisitorWithRememberedJobs);
        self::assertStringNotContainsString(' checked>', $bodyOfVisitorWithRememberedJobs);
        self::assertStringContainsString('name="remember_job" value="8">', $bodyOfVisitorWithRememberedJobs);
    }

    #[Test]
    public function listActionDoesNotSortRememberedJobsFirst(): void
    {
        $body = $this->fetchListPageBody($this->rememberJobs(8));

        $this->assertAppearsBefore('data-jobboard-uid="1"', 'data-jobboard-uid="7"', $body);
        $this->assertAppearsBefore('data-jobboard-uid="7"', 'data-jobboard-uid="8"', $body);
    }

    #[Test]
    public function listActionDoesNotMoveRememberedJobsToFirstPage(): void
    {
        $this->setItemsPerPage(1);

        $body = $this->fetchListPageBody($this->rememberJobs(8));

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('data-jobboard-uid="1"', $body);
    }

    #[Test]
    public function searchActionRendersNoVisitorSpecificMarkupForVisitorWithRememberedJobs(): void
    {
        $body = $this->fetchSearchActionPageBody(4, ['jobArea' => 1], $this->rememberJobs(8));

        $this->assertAppearsBefore('data-jobboard-uid="1"', 'data-jobboard-uid="8"', $body);
        self::assertStringNotContainsString('highlight-row', $body);
        self::assertStringNotContainsString(' checked>', $body);
    }

    #[Test]
    public function listActionContainsLinkToRememberedJobs(): void
    {
        self::assertStringContainsString('tx_jobboard_jobboard%5Baction%5D=rememberedJobs', $this->fetchListPageBody());
    }

    #[Test]
    public function rememberedJobsActionListsRememberedJobsCheckedInOrderOfJobList(): void
    {
        $body = $this->fetchRememberedJobsPageBody($this->rememberJobs(8, 1));

        self::assertSame(2, substr_count($body, 'data-jobboard-uid="'));
        $this->assertAppearsBefore('data-jobboard-uid="1"', 'data-jobboard-uid="8"', $body);
        self::assertStringContainsString('name="remember_job" value="1" checked>', $body);
        self::assertStringContainsString('name="remember_job" value="8" checked>', $body);
        self::assertStringNotContainsString('data-jobboard-uid="7"', $body);
    }

    #[Test]
    public function rememberedJobsActionIgnoresRememberedJobsWhichAreNotListed(): void
    {
        // After they were remembered, job 7 is hidden, job 8 expires and job 10
        // loses its salary information (RememberJobMiddleware does not accept
        // such jobs, but they may still be in the session).
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->insert('tx_jobboard_domain_model_job', [
                'uid' => 10,
                'pid' => 2,
                'title' => 'Job losing its salary information',
                'effective_salary_min' => 2500,
            ]);
        $sessionCookie = $this->rememberJobs(7, 8, 10, 1);
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 7, ['hidden' => 1]);
        $this->updateRecordWithoutDataHandler('tx_jobboard_domain_model_job', 8, ['application_deadline' => 946684800]);
        $this->updateRecordWithoutDataHandler(
            'tx_jobboard_domain_model_job',
            10,
            ['effective_salary_min' => 0, 'effective_salary_max' => 0],
        );

        $body = $this->fetchRememberedJobsPageBody($sessionCookie);

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('data-jobboard-uid="1"', $body);
    }

    #[Test]
    public function rememberedJobsActionListsJobsWithoutSalaryInformationIfSalaryIsNotRequired(): void
    {
        $this->writeSalaryRequired(false);

        $body = $this->fetchRememberedJobsPageBody($this->rememberJobs(9, 1));

        self::assertSame(2, substr_count($body, 'data-jobboard-uid="'));
        $this->assertAppearsBefore('data-jobboard-uid="1"', 'data-jobboard-uid="9"', $body);
    }

    #[Test]
    public function rememberedJobsActionIgnoresJobsWithoutSalaryInformationIfSalaryIsRequiredAgain(): void
    {
        $this->writeSalaryRequired(false);
        $sessionCookie = $this->rememberJobs(9, 1);
        $this->writeSalaryRequired(true);

        $body = $this->fetchRememberedJobsPageBody($sessionCookie);

        self::assertSame(1, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('data-jobboard-uid="1"', $body);
    }

    #[Test]
    public function rememberedJobsActionWithoutRememberedJobsRendersHint(): void
    {
        $body = $this->fetchRememberedJobsPageBody();

        self::assertSame(0, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('You have not remembered any jobs yet.', $body);
    }

    #[Test]
    public function rememberedJobsActionIsNotCached(): void
    {
        self::assertStringContainsString(
            'data-jobboard-uid="8"',
            $this->fetchRememberedJobsPageBody($this->rememberJobs(8)),
        );

        $body = $this->fetchRememberedJobsPageBody();

        self::assertSame(0, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('You have not remembered any jobs yet.', $body);
    }

    #[Test]
    public function rememberedJobsActionIsNotPaginated(): void
    {
        $this->setItemsPerPage(1);

        $body = $this->fetchRememberedJobsPageBody($this->rememberJobs(8, 1, 7));

        self::assertSame(3, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringNotContainsString('currentPage', $body);
    }
}
