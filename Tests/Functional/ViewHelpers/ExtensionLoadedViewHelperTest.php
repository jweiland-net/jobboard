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
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Test case.
 *
 * EXT:maps2 is not loaded in this test case.
 */
final class ExtensionLoadedViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function extensionKeyDataProvider(): array
    {
        return [
            'loaded extension' => ['jobboard', 'loaded'],
            'loaded core extension' => ['extbase', 'loaded'],
            'optional extension, which is not loaded' => ['maps2', 'not loaded'],
            'unknown extension' => ['not_existing_extension', 'not loaded'],
            'empty extension key' => ['', 'not loaded'],
        ];
    }

    #[Test]
    #[DataProvider('extensionKeyDataProvider')]
    public function renderReturnsWhetherExtensionIsLoaded(string $extensionKey, string $expected): void
    {
        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}'
            . '<f:if condition="{jobboard:extensionLoaded(extensionKey: extensionKey)}" then="loaded" else="not loaded"/>',
        );
        $context->getVariableProvider()->add('extensionKey', $extensionKey);

        self::assertSame($expected, (string)(new TemplateView($context))->render());
    }
}
