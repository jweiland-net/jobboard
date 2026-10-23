<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class EffectiveSalaryTest extends UnitTestCase
{
    /**
     * @return array<string, array{float, float, float, float}>
     */
    public static function freeEntryDataProvider(): array
    {
        return [
            'range' => [2500.0, 3200.0, 2500.0, 3200.0],
            'minimum only is used as maximum, too' => [2500.0, 0.0, 2500.0, 2500.0],
            'maximum only' => [0.0, 3200.0, 0.0, 3200.0],
            'no amount' => [0.0, 0.0, 0.0, 0.0],
            'negative minimum is ignored' => [-100.0, 0.0, 0.0, 0.0],
            'negative maximum falls back to minimum' => [2500.0, -1.0, 2500.0, 2500.0],
        ];
    }

    #[Test]
    #[DataProvider('freeEntryDataProvider')]
    public function fromFreeEntryUsesMinimumAsFallbackForMaximum(
        float $salaryMin,
        float $salaryMax,
        float $expectedMin,
        float $expectedMax,
    ): void {
        $subject = EffectiveSalary::fromFreeEntry($salaryMin, $salaryMax);

        self::assertSame($expectedMin, $subject->getMin());
        self::assertSame($expectedMax, $subject->getMax());
        self::assertSame(0, $subject->getValidUntil());
    }

    /**
     * @return array<string, array{float[], float, float}>
     */
    public static function amountsDataProvider(): array
    {
        return [
            'no amounts' => [[], 0.0, 0.0],
            'single amount' => [[3500.0], 3500.0, 3500.0],
            'several amounts' => [[3407.74, 3220.85, 3314.32], 3220.85, 3407.74],
            'zero amounts are ignored' => [[0.0, 3000.0, 0.0, 3200.0], 3000.0, 3200.0],
            'negative amounts are ignored' => [[-5000.0, 3000.0], 3000.0, 3000.0],
            'only zero amounts' => [[0.0, 0.0], 0.0, 0.0],
        ];
    }

    /**
     * @param float[] $amounts
     */
    #[Test]
    #[DataProvider('amountsDataProvider')]
    public function fromAmountsUsesLowestAndHighestPositiveAmount(
        array $amounts,
        float $expectedMin,
        float $expectedMax,
    ): void {
        $subject = EffectiveSalary::fromAmounts($amounts, 1767225600);

        self::assertSame($expectedMin, $subject->getMin());
        self::assertSame($expectedMax, $subject->getMax());
        self::assertSame(1767225600, $subject->getValidUntil());
    }

    #[Test]
    public function fromAmountsNeverReturnsNegativeValidUntil(): void
    {
        self::assertSame(0, EffectiveSalary::fromAmounts([3000.0], -1)->getValidUntil());
    }

    #[Test]
    public function fromRowReadsEffectiveSalaryColumns(): void
    {
        $subject = EffectiveSalary::fromRow([
            'effective_salary_min' => '2500.50',
            'effective_salary_max' => '3200.00',
            'effective_salary_valid_until' => '1767225600',
        ]);

        self::assertSame(2500.5, $subject->getMin());
        self::assertSame(3200.0, $subject->getMax());
        self::assertSame(1767225600, $subject->getValidUntil());
    }

    #[Test]
    public function fromRowWithoutColumnsReturnsEmptyEffectiveSalary(): void
    {
        $subject = EffectiveSalary::fromRow([]);

        self::assertSame(0.0, $subject->getMin());
        self::assertSame(0.0, $subject->getMax());
        self::assertSame(0, $subject->getValidUntil());
        self::assertFalse($subject->getHasSalaryInformation());
    }

    /**
     * @return array<string, array{EffectiveSalary, bool}>
     */
    public static function hasSalaryInformationDataProvider(): array
    {
        return [
            'nothing' => [EffectiveSalary::fromFreeEntry(0.0, 0.0), false],
            'minimum and maximum' => [EffectiveSalary::fromFreeEntry(2500.0, 3200.0), true],
            'maximum only' => [EffectiveSalary::fromFreeEntry(0.0, 3200.0), true],
            'amounts' => [EffectiveSalary::fromAmounts([3000.0], 0), true],
            'no amounts but upcoming change' => [EffectiveSalary::fromAmounts([], 1767225600), false],
        ];
    }

    #[Test]
    #[DataProvider('hasSalaryInformationDataProvider')]
    public function getHasSalaryInformationRequiresPositiveMinimumOrMaximum(
        EffectiveSalary $subject,
        bool $expected,
    ): void {
        self::assertSame($expected, $subject->getHasSalaryInformation());
    }

    #[Test]
    public function toFieldArrayFormatsDecimalsLikeTheDatabase(): void
    {
        self::assertSame(
            [
                'effective_salary_min' => '3220.85',
                'effective_salary_max' => '3500.00',
                'effective_salary_valid_until' => 1767225600,
            ],
            EffectiveSalary::fromAmounts([3500.0, 3220.849], 1767225600)->toFieldArray(),
        );
    }

    #[Test]
    public function equalsComparesWithPrecisionOfDatabaseColumns(): void
    {
        $subject = EffectiveSalary::fromAmounts([3220.85, 3500.0], 0);

        self::assertTrue($subject->equals(EffectiveSalary::fromRow([
            'effective_salary_min' => '3220.85',
            'effective_salary_max' => '3500.00',
            'effective_salary_valid_until' => 0,
        ])));
        self::assertTrue($subject->equals(EffectiveSalary::fromAmounts([3220.8501, 3500.0], 0)));
    }

    /**
     * @return array<string, array{EffectiveSalary}>
     */
    public static function differentEffectiveSalaryDataProvider(): array
    {
        return [
            'other minimum' => [EffectiveSalary::fromAmounts([3220.86, 3500.0], 0)],
            'other maximum' => [EffectiveSalary::fromAmounts([3220.85, 3500.01], 0)],
            'other valid until' => [EffectiveSalary::fromAmounts([3220.85, 3500.0], 1767225600)],
        ];
    }

    #[Test]
    #[DataProvider('differentEffectiveSalaryDataProvider')]
    public function equalsDetectsChangedValues(EffectiveSalary $effectiveSalary): void
    {
        self::assertFalse(EffectiveSalary::fromAmounts([3220.85, 3500.0], 0)->equals($effectiveSalary));
    }
}
