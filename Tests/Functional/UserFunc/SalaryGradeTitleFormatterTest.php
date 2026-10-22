<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\UserFunc;

use JWeiland\Jobboard\UserFunc\SalaryGradeTitleFormatter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class SalaryGradeTitleFormatterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private SalaryGradeTitleFormatter $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SalaryGradeTitleFormatter.csv');

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');

        $this->subject = new SalaryGradeTitleFormatter();
    }

    #[Test]
    public function formatTitleAppendsSalaryTableTitle(): void
    {
        $parameters = [
            'row' => [
                'title' => 'A7',
                'salary_table' => 1,
            ],
        ];

        $this->subject->formatTitle($parameters);

        self::assertSame('A7, Grundgehaltssaetze Baden-Wuerttemberg', $parameters['title']);
    }

    #[Test]
    public function formatInlineChildTitleDoesNotAppendSalaryTableTitle(): void
    {
        $parameters = [
            'row' => [
                'title' => 'A7',
                'salary_table' => 1,
            ],
        ];

        $this->subject->formatInlineChildTitle($parameters);

        self::assertSame('A7', $parameters['title']);
    }

    #[Test]
    public function formatTitleWithoutSalaryTableLeavesTitleUnchanged(): void
    {
        $parameters = [
            'row' => [
                'title' => 'A7',
                'salary_table' => 0,
            ],
        ];

        $this->subject->formatTitle($parameters);

        self::assertSame('A7', $parameters['title']);
    }
}
