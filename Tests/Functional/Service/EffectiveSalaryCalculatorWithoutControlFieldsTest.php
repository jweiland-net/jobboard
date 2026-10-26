<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\EffectiveSalaryCalculatorInterface;
use JWeiland\Jobboard\Tests\Functional\Traits\RemoveTcaCapabilityTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Salary grades and steps without TCA ctrl "enablecolumns" starttime/endtime
 * and steps without language capability: there is no time condition (always
 * started, never ending, no next change) and no language constraint. Same
 * fixture and NOW as EffectiveSalaryCalculatorTest.
 */
final class EffectiveSalaryCalculatorWithoutControlFieldsTest extends FunctionalTestCase
{
    use RemoveTcaCapabilityTrait;

    private const NOW = 1767225600;

    private const TABLE_SALARY_GRADE = 'tx_jobboard_domain_model_salarygrade';

    private const TABLE_SALARY_STEP = 'tx_jobboard_domain_model_salarystep';

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectiveSalaryCalculator.csv');
        $this->removeTimeRestrictions(self::TABLE_SALARY_GRADE);
        $this->removeTimeRestrictions(self::TABLE_SALARY_STEP);
        $this->removeLanguageCapability(self::TABLE_SALARY_STEP);
    }

    protected function tearDown(): void
    {
        $this->restoreTca();

        parent::tearDown();
    }

    /**
     * @return array<string, array{int, float, float, int}>
     */
    public static function effectiveSalaryDataProvider(): array
    {
        return [
            'flat grade is unchanged' => [5, 4000.0, 4000.0, 0],
            'grade starting in one second is valid, no next change' => [10, 1000.0, 1000.0, 0],
            'grade expiring at the end of the look-ahead is valid' => [11, 1000.0, 1000.0, 0],
            'grade expiring after the look-ahead has no next change' => [12, 5000.0, 5000.0, 0],
            'expired grade is valid' => [13, 1000.0, 1000.0, 0],
            // Hidden and deleted steps are still ignored, the translation (50)
            // counts without language capability
            'stepped grade with scheduled steps and translation' => [14, 50.0, 6000.0, 0],
            'not yet started stepped grade is valid' => [16, 100.0, 100.0, 0],
        ];
    }

    #[Test]
    #[DataProvider('effectiveSalaryDataProvider')]
    public function calculateForJobsIgnoresMissingControlFields(
        int $jobUid,
        float $expectedMin,
        float $expectedMax,
        int $expectedValidUntil,
    ): void {
        $effectiveSalary = $this->get(EffectiveSalaryCalculatorInterface::class)->calculateForJobs([$jobUid], self::NOW)[$jobUid];

        self::assertSame(
            [$expectedMin, $expectedMax, $expectedValidUntil],
            [$effectiveSalary->getMin(), $effectiveSalary->getMax(), $effectiveSalary->getValidUntil()],
        );
    }
}
