<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Salary amounts of the detail view (salary range of the job details and of
 * the salary section, salary steps) in site language 0 (English) and 1
 * (German) with the site settings "jobboard.salary.*" of the number format.
 * The job has a salary grade with the steps 1234.50 and 12345.67.
 */
final class JobboardControllerSalaryNumberFormatTest extends FunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SalaryNumberFormat.csv');

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
     * Site settings are cached by the hash of config.yaml: a changed
     * settings.yaml is only read again after flushing the core cache.
     *
     * @param array<string, mixed> $salarySettings
     */
    private function writeSalarySettings(array $salarySettings): void
    {
        $this->get(SiteWriter::class)->writeSettings('main', [
            'jobboard' => ['storagePid' => '2', 'salary' => $salarySettings],
        ]);
        $this->get(CacheManager::class)->getCache('core')->flush();
    }

    private function fetchDetailPageBody(string $base): string
    {
        $parameters = [
            'tx_jobboard_jobboard[controller]' => 'Jobboard',
            'tx_jobboard_jobboard[action]' => 'detail',
            'tx_jobboard_jobboard[job]' => '1',
        ];

        $request = (new InternalRequest('https://example.com' . $base))->withPageId(1);
        foreach ($parameters as $parameterName => $value) {
            $request = $request->withQueryParameter($parameterName, $value);
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=1&' . http_build_query($parameters),
        );
        $response = $this->executeFrontendSubRequest($request->withQueryParameter('cHash', $cHash));
        self::assertSame(200, $response->getStatusCode());

        return (string)$response->getBody();
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, string}>
     */
    public static function numberFormatDataProvider(): array
    {
        $nbsp = "\u{00A0}";

        return [
            'automatic by default in English' => [[], '/', '€1,234.50', '€12,345.67'],
            'automatic by default in German' => [[], '/de/', '1.234,50' . $nbsp . '€', '12.345,67' . $nbsp . '€'],
            'automatic in German without decimals' => [
                ['numberFormat' => 'automatic', 'decimals' => 0],
                '/de/',
                '1.235' . $nbsp . '€',
                '12.346' . $nbsp . '€',
            ],
            'automatic with Swiss francs in German' => [
                ['currency' => 'CHF'],
                '/de/',
                '1.234,50' . $nbsp . 'CHF',
                '12.345,67' . $nbsp . 'CHF',
            ],
            'automatic with US dollars in English' => [['currency' => 'USD'], '/', '$1,234.50', '$12,345.67'],
            'custom in English' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => ' ', 'decimalSeparator' => ',', 'decimals' => 2],
                '/',
                '1 234,50' . $nbsp . '€',
                '12 345,67' . $nbsp . '€',
            ],
            'custom with currency sign before the amount in German' => [
                [
                    'numberFormat' => 'custom',
                    'thousandsSeparator' => '.',
                    'decimalSeparator' => ',',
                    'currencyPosition' => 'before',
                ],
                '/de/',
                '€' . $nbsp . '1.234,50',
                '€' . $nbsp . '12.345,67',
            ],
            'custom without grouping in German' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => '', 'decimalSeparator' => '.', 'decimals' => 3],
                '/de/',
                '1234.500' . $nbsp . '€',
                '12345.670' . $nbsp . '€',
            ],
            'invalid custom falls back to automatic in German' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => '.', 'decimalSeparator' => '.'],
                '/de/',
                '1.234,50' . $nbsp . '€',
                '12.345,67' . $nbsp . '€',
            ],
            'removed format "english" falls back to automatic in German' => [
                ['numberFormat' => 'english'],
                '/de/',
                '1.234,50' . $nbsp . '€',
                '12.345,67' . $nbsp . '€',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    #[Test]
    #[DataProvider('numberFormatDataProvider')]
    public function detailActionFormatsAllSalaryAmountsWithConfiguredNumberFormat(
        array $salarySettings,
        string $base,
        string $expectedMinimum,
        string $expectedMaximum,
    ): void {
        $this->writeSalarySettings($salarySettings);

        $pageBody = explode('</head>', $this->fetchDetailPageBody($base), 2)[1] ?? '';

        // Salary range of the job details and of the salary section
        self::assertSame(2, substr_count($pageBody, $expectedMinimum . ' - ' . $expectedMaximum));
        // Salary steps
        self::assertStringContainsString('Step 1: ' . $expectedMinimum . '</li>', $pageBody);
        self::assertStringContainsString('Step 2: ' . $expectedMaximum . '</li>', $pageBody);
    }

    /**
     * The structured data for search engines keeps the plain numbers
     */
    #[Test]
    public function detailActionKeepsPlainSalaryNumbersInStructuredData(): void
    {
        $this->writeSalarySettings(['numberFormat' => 'custom', 'thousandsSeparator' => '.', 'decimalSeparator' => ',']);

        $body = $this->fetchDetailPageBody('/de/');

        self::assertMatchesRegularExpression('/"minValue":\s*1234\.5\b/', $body);
        self::assertMatchesRegularExpression('/"maxValue":\s*12345\.67\b/', $body);
    }

    /**
     * The structured data uses the same currency as the salary amounts
     */
    #[Test]
    public function detailActionUsesCurrencyOfSiteSettingsInStructuredData(): void
    {
        $this->writeSalarySettings(['currency' => 'CHF']);

        $body = $this->fetchDetailPageBody('/de/');

        self::assertMatchesRegularExpression('/"currency":\s*"CHF"/', $body);
        self::assertStringContainsString("1.234,50\u{00A0}CHF", $body);
    }

    #[Test]
    public function detailActionUsesEurInStructuredDataByDefault(): void
    {
        $this->writeSalarySettings([]);

        self::assertMatchesRegularExpression('/"currency":\s*"EUR"/', $this->fetchDetailPageBody('/'));
    }
}
