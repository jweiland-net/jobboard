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
use TYPO3\CMS\Core\Schema\Field\FileFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class ImageColumnTcaTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function imageColumnDataProvider(): array
    {
        return [
            'employer logo of job' => ['tx_jobboard_domain_model_job', 'employer_logo'],
            'header logos of job' => ['tx_jobboard_domain_model_job', 'header_logos'],
            'image of benefit' => ['tx_jobboard_domain_model_benefit', 'image'],
        ];
    }

    #[Test]
    #[DataProvider('imageColumnDataProvider')]
    public function imageColumnAllowsImageFilesOnly(string $table, string $column): void
    {
        $field = $this->get(TcaSchemaFactory::class)->get($table)->getField($column);
        self::assertInstanceOf(FileFieldType::class, $field);
        // "common-image-types" is resolved to $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
        $allowed = array_diff($field->getAllowedFileExtensions(), $field->getDisallowedFileExtensions());

        self::assertContains('png', $allowed);
        self::assertContains('jpg', $allowed);
        self::assertContains('svg', $allowed);
        self::assertNotContains('pdf', $allowed);
        self::assertNotContains('ai', $allowed);
        self::assertNotContains('docx', $allowed);
    }
}
