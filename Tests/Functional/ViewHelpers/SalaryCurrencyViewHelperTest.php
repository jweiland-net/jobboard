<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\ViewHelpers;

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
 * The currency in the structured data of the detail view is tested in
 * JobboardControllerSalaryNumberFormatTest.
 */
final class SalaryCurrencyViewHelperTest extends FunctionalTestCase
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
                    [
                        'languageId' => 2,
                        'title' => 'Nederlands',
                        'base' => '/nl/',
                        'locale' => 'nl_NL.UTF-8',
                    ],
                    [
                        'languageId' => 3,
                        'title' => 'Deutsch (Schweiz)',
                        'base' => '/ch/',
                        'locale' => 'de_CH.UTF-8',
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
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function salaryCurrencyDataProvider(): array
    {
        return [
            'default' => [[], 'EUR'],
            'site setting' => [['currency' => 'CHF'], 'CHF'],
            'invalid site setting' => [['currency' => 'Franken'], 'EUR'],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    #[Test]
    #[DataProvider('salaryCurrencyDataProvider')]
    public function salaryCurrencyReturnsCurrencyOfSiteSettings(array $salarySettings, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->render('{jobboard:salaryCurrency()}', [], $this->createFrontendRequest(1, $salarySettings)),
        );
    }

    #[Test]
    public function salaryCurrencyWithoutRequestReturnsEur(): void
    {
        self::assertSame('EUR', $this->render('{jobboard:salaryCurrency()}'));
    }
}
