<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\SalaryGrade;
use JWeiland\Jobboard\Domain\Model\SalaryRange;
use JWeiland\Jobboard\Domain\Model\SalaryStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * A salary grade is described in the data providers as either a float
 * (flat grade with this amount) or a list of floats (stepped grade with one
 * step per amount, an empty list is a stepped grade without loaded steps).
 */
class SalaryRangeTest extends UnitTestCase
{
    #[Test]
    public function constructorWithoutArgumentsCreatesEmptyRange(): void
    {
        $subject = new SalaryRange();

        self::assertSame(0.0, $subject->getMin());
        self::assertSame(0.0, $subject->getMax());
        self::assertTrue($subject->getIsEmpty());
        self::assertFalse($subject->getHasRange());
    }

    /**
     * @return array<string, array{float, float, bool, bool}>
     */
    public static function validBoundariesDataProvider(): array
    {
        return [
            'zero to zero' => [0.0, 0.0, false, true],
            'single amount' => [3500.0, 3500.0, false, false],
            'real range' => [3000.0, 4500.0, true, false],
            'zero to positive amount' => [0.0, 3000.0, true, false],
            'cent difference' => [3220.85, 3220.86, true, false],
        ];
    }

    #[Test]
    #[DataProvider('validBoundariesDataProvider')]
    public function constructorWithValidBoundariesCreatesRange(
        float $min,
        float $max,
        bool $expectedHasRange,
        bool $expectedIsEmpty,
    ): void {
        $subject = new SalaryRange($min, $max);

        self::assertSame($min, $subject->getMin());
        self::assertSame($max, $subject->getMax());
        self::assertSame($expectedHasRange, $subject->getHasRange());
        self::assertSame($expectedIsEmpty, $subject->getIsEmpty());
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function invalidBoundariesDataProvider(): array
    {
        return [
            'negative min and zero max' => [-0.01, 0.0],
            'negative min and positive max' => [-100.0, 3000.0],
            'negative min and negative max' => [-200.0, -100.0],
            'max lower than min' => [4000.0, 3000.0],
            'negative max' => [0.0, -1.0],
        ];
    }

    #[Test]
    #[DataProvider('invalidBoundariesDataProvider')]
    public function constructorWithInvalidBoundariesThrowsException(float $min, float $max): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791446400);

        new SalaryRange($min, $max);
    }

    /**
     * @return array<string, array{list<float|list<float>>, float, float, bool, bool}>
     */
    public static function salaryGradesDataProvider(): array
    {
        return [
            'no grades' => [
                [],
                0.0,
                0.0,
                false,
                true,
            ],
            'single flat grade' => [
                [3500.0],
                3500.0,
                3500.0,
                false,
                false,
            ],
            'single stepped grade' => [
                [[3220.85, 3314.32, 3407.74]],
                3220.85,
                3407.74,
                true,
                false,
            ],
            'single stepped grade with unsorted steps' => [
                [[3314.32, 3407.74, 3220.85]],
                3220.85,
                3407.74,
                true,
                false,
            ],
            'two stepped grades S 8a and S 8b' => [
                [[3000.0, 3250.0, 3500.0, 4000.0], [3500.0, 3800.0, 4500.0]],
                3000.0,
                4500.0,
                true,
                false,
            ],
            'flat grade above stepped grade' => [
                [3500.0, [3220.85, 3407.74]],
                3220.85,
                3500.0,
                true,
                false,
            ],
            'flat grade below stepped grade' => [
                [2800.0, [3000.0, 3200.0]],
                2800.0,
                3200.0,
                true,
                false,
            ],
            'flat grade inside stepped grade' => [
                [[3000.0, 4000.0], 3500.0],
                3000.0,
                4000.0,
                true,
                false,
            ],
            'two flat grades' => [
                [3100.0, 2900.0],
                2900.0,
                3100.0,
                true,
                false,
            ],
            'overlapping stepped grades' => [
                [[3000.0, 3600.0], [3400.0, 4200.0]],
                3000.0,
                4200.0,
                true,
                false,
            ],
            'nested stepped grades' => [
                [[3000.0, 5000.0], [3500.0, 4000.0]],
                3000.0,
                5000.0,
                true,
                false,
            ],
            'disjoint stepped grades' => [
                [[2000.0, 2500.0], [4000.0, 4500.0]],
                2000.0,
                4500.0,
                true,
                false,
            ],
            'three grades with min and max taken from different grades' => [
                [[3000.0, 3100.0], 2500.0, [4800.0, 5200.0]],
                2500.0,
                5200.0,
                true,
                false,
            ],
            'stepped grade without steps before valid grade' => [
                [[], [3000.0, 4000.0]],
                3000.0,
                4000.0,
                true,
                false,
            ],
            'stepped grade without steps after valid grade' => [
                [[3000.0, 4000.0], []],
                3000.0,
                4000.0,
                true,
                false,
            ],
            'stepped grade without steps next to flat grade' => [
                [[], 3500.0],
                3500.0,
                3500.0,
                false,
                false,
            ],
            'only stepped grades without steps' => [
                [[], []],
                0.0,
                0.0,
                false,
                true,
            ],
            'flat grade with amount 0 next to valid grade' => [
                [0.0, [3000.0, 4000.0]],
                3000.0,
                4000.0,
                true,
                false,
            ],
            'only flat grade with amount 0' => [
                [0.0],
                0.0,
                0.0,
                false,
                true,
            ],
            'step with amount 0' => [
                [[0.0, 3000.0, 3500.0]],
                3000.0,
                3500.0,
                true,
                false,
            ],
            'only steps with amount 0' => [
                [[0.0, 0.0]],
                0.0,
                0.0,
                false,
                true,
            ],
            'negative amounts are ignored' => [
                [-100.0, [-50.0, 3000.0]],
                3000.0,
                3000.0,
                false,
                false,
            ],
            'identical flat amounts across grades' => [
                [3500.0, 3500.0],
                3500.0,
                3500.0,
                false,
                false,
            ],
            'identical amounts across flat and stepped grade' => [
                [3500.0, [3500.0, 3500.0]],
                3500.0,
                3500.0,
                false,
                false,
            ],
            'shared boundary amount across grades' => [
                [[3000.0, 3500.0], [3500.0, 4000.0]],
                3000.0,
                4000.0,
                true,
                false,
            ],
        ];
    }

    /**
     * @param list<float|list<float>> $gradeDefinitions
     */
    #[Test]
    #[DataProvider('salaryGradesDataProvider')]
    public function fromSalaryGradesSpansLowestToHighestPositiveAmount(
        array $gradeDefinitions,
        float $expectedMin,
        float $expectedMax,
        bool $expectedHasRange,
        bool $expectedIsEmpty,
    ): void {
        $subject = SalaryRange::fromSalaryGrades($this->createSalaryGrades($gradeDefinitions));

        self::assertSame($expectedMin, $subject->getMin());
        self::assertSame($expectedMax, $subject->getMax());
        self::assertSame($expectedHasRange, $subject->getHasRange());
        self::assertSame($expectedIsEmpty, $subject->getIsEmpty());
    }

    /**
     * @param list<float|list<float>> $gradeDefinitions
     */
    #[Test]
    #[DataProvider('salaryGradesDataProvider')]
    public function fromSalaryGradesIsIndependentOfGradeAndStepOrder(
        array $gradeDefinitions,
        float $expectedMin,
        float $expectedMax,
    ): void {
        $reversedGradeDefinitions = array_map(
            static fn(float|array $definition): float|array => is_array($definition)
                ? array_reverse($definition)
                : $definition,
            array_reverse($gradeDefinitions),
        );

        $subject = SalaryRange::fromSalaryGrades($this->createSalaryGrades($reversedGradeDefinitions));

        self::assertSame($expectedMin, $subject->getMin());
        self::assertSame($expectedMax, $subject->getMax());
    }

    #[Test]
    public function fromSalaryGradesAcceptsObjectStorage(): void
    {
        $salaryGrades = new ObjectStorage();
        foreach ($this->createSalaryGrades([[3000.0, 4000.0], [3500.0, 4500.0]]) as $salaryGrade) {
            $salaryGrades->attach($salaryGrade);
        }

        $subject = SalaryRange::fromSalaryGrades($salaryGrades);

        self::assertSame(3000.0, $subject->getMin());
        self::assertSame(4500.0, $subject->getMax());
    }

    #[Test]
    public function fromSalaryGradesAcceptsGenerator(): void
    {
        $salaryGrades = $this->createSalaryGrades([3500.0, [3220.85, 3407.74]]);
        $generator = (static function () use ($salaryGrades): \Generator {
            yield from $salaryGrades;
        })();

        $subject = SalaryRange::fromSalaryGrades($generator);

        self::assertSame(3220.85, $subject->getMin());
        self::assertSame(3500.0, $subject->getMax());
    }

    #[Test]
    public function fromSalaryGradesIgnoresFlatAmountOfSteppedGrade(): void
    {
        $salaryGrade = new SalaryGrade();
        $salaryGrade->setHasSteps(true);
        $salaryGrade->setFlatAmount(1000.0);
        $salaryGrade->getSalarySteps()->attach($this->createSalaryStep(3000.0));

        $subject = SalaryRange::fromSalaryGrades([$salaryGrade]);

        self::assertSame(3000.0, $subject->getMin());
        self::assertSame(3000.0, $subject->getMax());
    }

    #[Test]
    public function fromSalaryGradesIgnoresStepsOfFlatGrade(): void
    {
        $salaryGrade = new SalaryGrade();
        $salaryGrade->setHasSteps(false);
        $salaryGrade->setFlatAmount(3500.0);
        $salaryGrade->getSalarySteps()->attach($this->createSalaryStep(1000.0));
        $salaryGrade->getSalarySteps()->attach($this->createSalaryStep(9000.0));

        $subject = SalaryRange::fromSalaryGrades([$salaryGrade]);

        self::assertSame(3500.0, $subject->getMin());
        self::assertSame(3500.0, $subject->getMax());
    }

    /**
     * @param list<float|list<float>> $gradeDefinitions
     * @return list<SalaryGrade>
     */
    private function createSalaryGrades(array $gradeDefinitions): array
    {
        $salaryGrades = [];
        foreach ($gradeDefinitions as $definition) {
            $salaryGrade = new SalaryGrade();
            if (is_array($definition)) {
                $salaryGrade->setHasSteps(true);
                foreach ($definition as $amount) {
                    $salaryGrade->getSalarySteps()->attach($this->createSalaryStep($amount));
                }
            } else {
                $salaryGrade->setHasSteps(false);
                $salaryGrade->setFlatAmount($definition);
            }
            $salaryGrades[] = $salaryGrade;
        }

        return $salaryGrades;
    }

    private function createSalaryStep(float $amount): SalaryStep
    {
        $salaryStep = new SalaryStep();
        $salaryStep->setAmount($amount);

        return $salaryStep;
    }
}
