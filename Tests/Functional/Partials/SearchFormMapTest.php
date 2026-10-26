<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Partials;

use JWeiland\Jobboard\Domain\Model\Job;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Test case.
 *
 * Renders the partial SearchForm.html with EXT:maps2 loaded. The partial
 * "Maps2/PoiCollection" of EXT:maps2 is replaced by a stub (see
 * Fixtures/Partials/), as it needs a complete maps2 configuration. Without
 * EXT:maps2, see JobboardControllerTest.
 */
final class SearchFormMapTest extends FunctionalTestCase
{
    private const ALERT = 'The map cannot be shown';

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Addresses 1 and 2 share PoiCollection 1, address 3 has PoiCollection
        // 2 and address 5 has none, see AddressPoiCollectionViewHelperTest
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AddressPoiCollection.csv');
    }

    /**
     * "jobs" are the jobs of the current page, "addressUids" the addresses of
     * all jobs of the list or search result (see JobboardController).
     *
     * @param Job[] $jobs
     * @param int[] $addressUids
     */
    private function renderSearchForm(array $jobs, array $addressUids): string
    {
        $extbaseRequestParameters = new ExtbaseRequestParameters();
        $extbaseRequestParameters->setControllerExtensionName('Jobboard');
        $extbaseRequestParameters->setPluginName('Jobboard');
        $extbaseRequestParameters->setControllerName('Jobboard');
        $extbaseRequestParameters->setControllerActionName('list');
        $site = new Site('main', 1, [
            'base' => 'https://example.com/',
            'languages' => [['languageId' => 0, 'title' => 'English', 'base' => '/', 'locale' => 'en_US.UTF-8']],
        ]);
        $request = new Request(
            (new ServerRequest('https://example.com/'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('site', $site)
                ->withAttribute('language', $site->getDefaultLanguage())
                ->withAttribute('extbase', $extbaseRequestParameters),
        );

        $context = $this->get(RenderingContextFactory::class)->create([], $request);
        $context->getTemplatePaths()->setPartialRootPaths([
            'EXT:jobboard/Resources/Private/Partials/',
            __DIR__ . '/../Fixtures/Partials/',
        ]);
        $context->getTemplatePaths()->setTemplateSource(
            '<f:render partial="SearchForm" arguments="{jobs: jobs, addressUids: addressUids, settings: {showSearch: 0}}"/>',
        );
        $context->getVariableProvider()->add('jobs', $jobs);
        $context->getVariableProvider()->add('addressUids', $addressUids);

        return (string)(new TemplateView($context))->render();
    }

    private function buildJob(): Job
    {
        $job = new Job();
        $job->setTitle('Job');

        return $job;
    }

    #[Test]
    public function searchFormRendersAlertIfNoAddressOfListedJobsHasPoiCollection(): void
    {
        $body = $this->renderSearchForm([$this->buildJob(), $this->buildJob()], [5]);

        self::assertStringContainsString('<div class="alert alert-warning mb-0" role="status">', $body);
        self::assertStringContainsString(self::ALERT, $body);
        self::assertStringContainsString('None of the addresses of the listed jobs has a geo position.', $body);
        self::assertStringNotContainsString('data-test-map', $body);
    }

    #[Test]
    public function searchFormRendersMapIfOneAddressHasPoiCollection(): void
    {
        $body = $this->renderSearchForm([$this->buildJob(), $this->buildJob()], [5, 1]);

        self::assertStringContainsString('data-test-map="1"', $body);
        self::assertStringNotContainsString(self::ALERT, $body);
    }

    #[Test]
    public function searchFormRendersOneMarkerPerLocation(): void
    {
        $body = $this->renderSearchForm([$this->buildJob()], [1, 2, 3]);

        self::assertStringContainsString('data-test-map="2"', $body);
    }

    #[Test]
    public function searchFormRendersLocationsOfAllJobsIndependentOfJobsOfCurrentPage(): void
    {
        // One job on the current page, but the result has jobs at 2 locations
        $body = $this->renderSearchForm([$this->buildJob()], [1, 3]);

        self::assertStringContainsString('data-test-map="2"', $body);
    }

    #[Test]
    public function searchFormRendersNeitherMapNorAlertWithoutJobs(): void
    {
        $body = $this->renderSearchForm([], []);

        self::assertStringNotContainsString(self::ALERT, $body);
        self::assertStringNotContainsString('data-test-map', $body);
    }
}
