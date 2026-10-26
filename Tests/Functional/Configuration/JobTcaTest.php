<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobTcaTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function logoColumnDataProvider(): array
    {
        return [
            'employer logo' => ['employer_logo'],
            'header logo' => ['header_logo'],
        ];
    }

    #[Test]
    #[DataProvider('logoColumnDataProvider')]
    public function logoColumnAllowsImageFilesOnly(string $column): void
    {
        $config = $GLOBALS['TCA']['tx_jobboard_domain_model_job']['columns'][$column]['config'];
        // "common-image-types" is resolved to $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
        $allowed = array_diff(
            GeneralUtility::trimExplode(',', $config['allowed'], true),
            GeneralUtility::trimExplode(',', $config['disallowed'] ?? '', true),
        );

        self::assertSame('file', $config['type']);
        self::assertContains('png', $allowed);
        self::assertContains('jpg', $allowed);
        self::assertContains('svg', $allowed);
        self::assertNotContains('pdf', $allowed);
        self::assertNotContains('ai', $allowed);
        self::assertNotContains('docx', $allowed);
    }
}
