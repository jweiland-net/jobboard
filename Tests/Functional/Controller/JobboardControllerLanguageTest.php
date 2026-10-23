<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Controller;

use JWeiland\Jobboard\Middleware\RememberJobMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Search and remembered jobs on site language 1 (German, fallback type
 * "fallback"), rendered through frontend sub-requests. Jobs 1 and 2 are
 * translated (11, 12), job 3 is not, job 4 (and its translation 14) has
 * expired. Job areas, roles and types are translated, the translated jobs
 * reference their default UIDs.
 */
final class JobboardControllerLanguageTest extends FunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobboardControllerLanguage.csv');

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
                [
                    'languageId' => 1,
                    'title' => 'Deutsch',
                    'navigationTitle' => 'Deutsch',
                    'base' => '/de/',
                    'locale' => 'de_DE.UTF-8',
                    'flag' => 'de',
                    'fallbackType' => 'fallback',
                    'fallbacks' => '0',
                ],
            ],
        ]);
        $siteWriter->writeSettings('main', ['jobboard' => ['storagePid' => '2']]);

        $this->setUpFrontendRootPage(1, [], [
            'config' => 'page = PAGE' . LF
                . 'page.10 = CONTENT' . LF
                . 'page.10.table = tt_content',
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            tt_content.jobboard_jobboard = EXTBASEPLUGIN
            tt_content.jobboard_jobboard {
                extensionName = Jobboard
                pluginName = Jobboard
            }
            plugin.tx_jobboard.view {
                templateRootPaths.0 = EXT:jobboard/Resources/Private/Templates/
                partialRootPaths.0 = EXT:jobboard/Resources/Private/Partials/
                layoutRootPaths.0 = EXT:jobboard/Resources/Private/Layouts/
            }
            plugin.tx_jobboard.persistence.storagePid = 2
        ');
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private function buildPluginRequest(string $base, string $action, array $arguments = []): InternalRequest
    {
        $parameters = [
            'tx_jobboard_jobboard[controller]' => 'Jobboard',
            'tx_jobboard_jobboard[action]' => $action,
        ];
        foreach ($arguments as $argumentName => $value) {
            $parameters['tx_jobboard_jobboard[' . $argumentName . ']'] = (string)$value;
        }

        $request = (new InternalRequest('https://example.com' . $base))->withPageId(1);
        foreach ($parameters as $parameterName => $value) {
            $request = $request->withQueryParameter($parameterName, $value);
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=1&' . http_build_query($parameters),
        );

        return $request->withQueryParameter('cHash', $cHash);
    }

    /**
     * @param array<string, int|string> $arguments
     */
    private function fetchSearchActionPageBody(string $base, array $arguments): string
    {
        return (string)$this->executeFrontendSubRequest($this->buildPluginRequest($base, 'search', $arguments))->getBody();
    }

    private function rememberJobs(int ...$jobUids): string
    {
        $sessionCookie = '';
        foreach ($jobUids as $jobUid) {
            $request = (new InternalRequest('https://example.com/de/'))
                ->withMethod('POST')
                ->withHeader(RememberJobMiddleware::HEADER_NAME, '1')
                ->withParsedBody(['job' => (string)$jobUid, 'remember' => '1']);
            if ($sessionCookie !== '') {
                $request = $request->withCookieParams(['fe_typo_user' => $sessionCookie]);
            }
            $response = $this->executeFrontendSubRequest($request);
            self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
            if (preg_match('/^fe_typo_user=([^;]+)/', $response->getHeaderLine('Set-Cookie'), $matches) === 1) {
                $sessionCookie = $matches[1];
            }
        }

        return $sessionCookie;
    }

    /**
     * @return array<string, array{array<string, int>, string[], string[]}>
     */
    public static function searchDataProvider(): array
    {
        return [
            'job role "Specialist"' => [
                ['jobRole' => 1],
                ['Fachkraft in Vollzeit in der IT', 'Part-time specialist in IT without translation'],
                ['Auszubildender in Teilzeit im Handwerk'],
            ],
            'job type "Part-time"' => [
                ['jobType' => 2],
                ['Auszubildender in Teilzeit im Handwerk', 'Part-time specialist in IT without translation'],
                ['Fachkraft in Vollzeit in der IT'],
            ],
            'job area "Crafts"' => [
                ['jobArea' => 2],
                ['Auszubildender in Teilzeit im Handwerk'],
                ['Fachkraft in Vollzeit in der IT', 'Part-time specialist in IT without translation'],
            ],
            'job role, job type and job area' => [
                ['jobRole' => 1, 'jobType' => 1, 'jobArea' => 1],
                ['Fachkraft in Vollzeit in der IT'],
                ['Auszubildender in Teilzeit im Handwerk', 'Part-time specialist in IT without translation'],
            ],
        ];
    }

    /**
     * @param array<string, int> $arguments
     * @param string[] $expectedTitles
     * @param string[] $unexpectedTitles
     */
    #[Test]
    #[DataProvider('searchDataProvider')]
    public function searchActionFiltersByTranslatedRelationsInLanguage1(
        array $arguments,
        array $expectedTitles,
        array $unexpectedTitles,
    ): void {
        $body = $this->fetchSearchActionPageBody('/de/', $arguments);

        foreach ($expectedTitles as $expectedTitle) {
            self::assertStringContainsString($expectedTitle, $body);
        }
        foreach ($unexpectedTitles as $unexpectedTitle) {
            self::assertStringNotContainsString($unexpectedTitle, $body);
        }
        self::assertStringNotContainsString('Abgelaufene Fachkraft', $body);
    }

    #[Test]
    public function searchActionReturnsTheSameJobsInLanguage0AndLanguage1(): void
    {
        $body = $this->fetchSearchActionPageBody('/', ['jobRole' => 1]);

        self::assertStringContainsString('Full-time specialist in IT', $body);
        self::assertStringContainsString('Part-time specialist in IT without translation', $body);
        self::assertStringNotContainsString('Part-time apprentice in crafts', $body);
        self::assertSame(
            substr_count($body, 'data-jobboard-uid="'),
            substr_count($this->fetchSearchActionPageBody('/de/', ['jobRole' => 1]), 'data-jobboard-uid="'),
        );
    }

    #[Test]
    public function searchFormOptionsAreTranslatedInLanguage1(): void
    {
        $body = $this->fetchSearchActionPageBody('/de/', []);

        self::assertStringContainsString('Fachkraft', $body);
        self::assertStringContainsString('Teilzeit', $body);
        self::assertStringContainsString('Handwerk', $body);
    }

    #[Test]
    public function rememberedJobsActionListsTranslatedJobsInLanguage1(): void
    {
        $sessionCookie = $this->rememberJobs(2, 1, 3);

        $request = $this->buildPluginRequest('/de/', 'rememberedJobs')->withCookieParams(['fe_typo_user' => $sessionCookie]);
        $body = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertSame(3, substr_count($body, 'data-jobboard-uid="'));
        self::assertStringContainsString('Fachkraft in Vollzeit in der IT', $body);
        self::assertStringContainsString('Auszubildender in Teilzeit im Handwerk', $body);
        self::assertStringContainsString('Part-time specialist in IT without translation', $body);
        self::assertStringNotContainsString('Full-time specialist in IT', $body);
        // The checkboxes use the UIDs of the default language
        self::assertStringContainsString('name="remember_job" value="1" checked>', $body);
        self::assertStringContainsString('name="remember_job" value="2" checked>', $body);
    }

    #[Test]
    public function translatedJobCanNotBeRemembered(): void
    {
        $request = (new InternalRequest('https://example.com/de/'))
            ->withMethod('POST')
            ->withHeader(RememberJobMiddleware::HEADER_NAME, '1')
            ->withParsedBody(['job' => '11', 'remember' => '1']);

        self::assertSame(404, $this->executeFrontendSubRequest($request)->getStatusCode());
    }
}
