<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\ViewHelpers\Format;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Test case.
 *
 * The number formats themselves are tested in SalaryNumberFormatTest and,
 * in the detail view, in JobboardControllerSalaryNumberFormatTest.
 */
final class SalaryViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $template, array $variables = [], ?ServerRequest $request = null): string
    {
        $context = $this->get(RenderingContextFactory::class)->create([], $request);
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}' . $template,
        );
        foreach ($variables as $name => $value) {
            $context->getVariableProvider()->add($name, $value);
        }

        return (string)(new TemplateView($context))->render();
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    private function createFrontendRequest(int $languageId, array $salarySettings = []): ServerRequest
    {
        $site = new Site(
            'main',
            1,
            [
                'base' => 'https://example.com/',
                'languages' => [
                    [
                        'languageId' => 0,
                        'title' => 'English',
                        'base' => '/',
                        'locale' => 'en_US.UTF-8',
                    ],
                    [
                        'languageId' => 1,
                        'title' => 'Deutsch',
                        'base' => '/de/',
                        'locale' => 'de_DE.UTF-8',
                    ],
                ],
            ],
            SiteSettings::createFromSettingsTree(['jobboard' => ['salary' => $salarySettings]]),
        );

        return (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getLanguageById($languageId));
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function templateDataProvider(): array
    {
        return [
            'tag content with currency sign' => [
                '<jobboard:format.salary currencySign="€">{amount}</jobboard:format.salary>',
                ['amount' => 1234.5],
                '1,234.50 €',
            ],
            'inline notation with currency sign' => [
                '{amount -> jobboard:format.salary(currencySign: \'€\')}',
                ['amount' => 12345.67],
                '12,345.67 €',
            ],
            'argument amount' => [
                '<jobboard:format.salary amount="{amount}"/>',
                ['amount' => 1234.5],
                '1,234.50',
            ],
            'without currency sign no trailing space' => [
                '<jobboard:format.salary>{amount}</jobboard:format.salary>',
                ['amount' => 1234.5],
                '1,234.50',
            ],
            'numeric string' => [
                '<jobboard:format.salary>{amount}</jobboard:format.salary>',
                ['amount' => '1234.5'],
                '1,234.50',
            ],
            'integer' => [
                '<jobboard:format.salary>{amount}</jobboard:format.salary>',
                ['amount' => 3000],
                '3,000.00',
            ],
            'not numeric is zero' => [
                '<jobboard:format.salary currencySign="€">{amount}</jobboard:format.salary>',
                ['amount' => 'on request'],
                '0.00 €',
            ],
            'empty is zero' => [
                '<jobboard:format.salary currencySign="€">{amount}</jobboard:format.salary>',
                [],
                '0.00 €',
            ],
        ];
    }

    /**
     * Without a request, there are no site settings and the labels of the
     * default language (English) are used.
     *
     * @param array<string, mixed> $variables
     */
    #[Test]
    #[DataProvider('templateDataProvider')]
    public function renderWithoutRequestUsesEnglishFormat(string $template, array $variables, string $expected): void
    {
        self::assertSame($expected, $this->render($template, $variables));
    }

    /**
     * @return array<string, array{int, array<string, mixed>, string}>
     */
    public static function requestDataProvider(): array
    {
        return [
            'automatic in English' => [0, [], '12,345.67 €'],
            'automatic in German' => [1, [], '12.345,67 €'],
            'english in German' => [1, ['numberFormat' => 'english'], '12,345.67 €'],
            'german in English' => [0, ['numberFormat' => 'german'], '12.345,67 €'],
            'automatic in German without decimals' => [1, ['decimals' => 0], '12.346 €'],
            'custom separator is escaped' => [
                0,
                ['numberFormat' => 'custom', 'thousandsSeparator' => '\'', 'decimalSeparator' => '.'],
                '12&#039;345.67 €',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    #[Test]
    #[DataProvider('requestDataProvider')]
    public function renderUsesSiteSettingsAndLanguageOfRequest(
        int $languageId,
        array $salarySettings,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->render(
                '<jobboard:format.salary currencySign="€">{amount}</jobboard:format.salary>',
                ['amount' => 12345.67],
                $this->createFrontendRequest($languageId, $salarySettings),
            ),
        );
    }
}
