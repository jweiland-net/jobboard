<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Partials;

use JWeiland\Jobboard\Domain\Model\Address;
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Maps2\Domain\Model\PoiCollection;
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

    /**
     * @param Job[] $jobs
     */
    private function renderSearchForm(array $jobs): string
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
            '<f:render partial="SearchForm" arguments="{jobs: jobs, settings: {showSearch: 0}}"/>',
        );
        $context->getVariableProvider()->add('jobs', $jobs);

        return (string)(new TemplateView($context))->render();
    }

    private function buildJob(?PoiCollection $poiCollection): Job
    {
        $address = new Address();
        if ($poiCollection instanceof PoiCollection) {
            $address->setTxMaps2Uid($poiCollection);
        }

        $job = new Job();
        $job->setTitle('Job');
        $job->setAddress($address);

        return $job;
    }

    #[Test]
    public function searchFormRendersAlertIfNoAddressOfListedJobsHasPoiCollection(): void
    {
        $body = $this->renderSearchForm([$this->buildJob(null), $this->buildJob(null), new Job()]);

        self::assertStringContainsString('<div class="alert alert-warning mb-0" role="status">', $body);
        self::assertStringContainsString(self::ALERT, $body);
        self::assertStringContainsString('None of the addresses of the listed jobs has a geo position.', $body);
        self::assertStringNotContainsString('data-test-map', $body);
    }

    #[Test]
    public function searchFormRendersMapIfOneAddressHasPoiCollection(): void
    {
        $body = $this->renderSearchForm([$this->buildJob(null), $this->buildJob(new PoiCollection())]);

        self::assertStringContainsString('data-test-map="1"', $body);
        self::assertStringNotContainsString(self::ALERT, $body);
    }

    #[Test]
    public function searchFormRendersNeitherMapNorAlertWithoutJobs(): void
    {
        $body = $this->renderSearchForm([]);

        self::assertStringNotContainsString(self::ALERT, $body);
        self::assertStringNotContainsString('data-test-map', $body);
    }
}
