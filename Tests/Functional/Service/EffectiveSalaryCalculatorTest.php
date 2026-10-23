<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use JWeiland\Jobboard\Service\EffectiveSalaryCalculator;
use JWeiland\Jobboard\Service\EffectiveSalaryCalculatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * All start- and endtimes of the fixture are relative to NOW
 * (2026-01-01 00:00:00 UTC), which is passed to the calculator. The look-ahead
 * boundary is NOW + LOOK_AHEAD = 1767312000.
 */
final class EffectiveSalaryCalculatorTest extends FunctionalTestCase
{
    private const NOW = 1767225600;

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private EffectiveSalaryCalculatorInterface $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectiveSalaryCalculator.csv');

        $this->subject = $this->get(EffectiveSalaryCalculatorInterface::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    /**
     * @return array{float, float, int}
     */
    private function calculate(int $jobUid, int $now = self::NOW): array
    {
        $effectiveSalaries = $this->subject->calculateForJobs([$jobUid], $now);

        self::assertArrayHasKey($jobUid, $effectiveSalaries);

        return [
            $effectiveSalaries[$jobUid]->getMin(),
            $effectiveSalaries[$jobUid]->getMax(),
            $effectiveSalaries[$jobUid]->getValidUntil(),
        ];
    }

    #[Test]
    public function calculatorIsRegisteredForInterface(): void
    {
        self::assertInstanceOf(EffectiveSalaryCalculator::class, $this->subject);
    }

    #[Test]
    public function lookAheadIsOneDay(): void
    {
        self::assertSame(86400, EffectiveSalaryCalculatorInterface::LOOK_AHEAD);
    }

    /**
     * @return array<string, array{int, float, float, int}>
     */
    public static function effectiveSalaryDataProvider(): array
    {
        return [
            'free entry range' => [1, 2500.0, 3200.0, 0],
            'free entry single amount is used as maximum, too' => [2, 2500.0, 2500.0, 0],
            'free entry without amount' => [3, 0.0, 0.0, 0],
            'free entry ignores selected salary grades' => [4, 1000.0, 2000.0, 0],
            'flat grade' => [5, 4000.0, 4000.0, 0],
            'stepped grade uses lowest and highest step' => [6, 3000.0, 3500.0, 0],
            'multiple grades span all grades' => [7, 3000.0, 4000.0, 0],
            'hidden and deleted grades are ignored' => [8, 0.0, 0.0, 0],
            'grade with starttime now is valid' => [9, 2000.0, 2000.0, 0],
            'grade starting in one second is invalid and the next change' => [10, 0.0, 0.0, self::NOW + 1],
            'grade expiring at the end of the look-ahead is invalid already' => [11, 0.0, 0.0, 0],
            'grade expiring one second after the look-ahead is valid' => [12, 5000.0, 5000.0, self::NOW + 86401],
            'expired grade' => [13, 0.0, 0.0, 0],
            // Hidden, deleted, not yet started, expiring and zero steps and
            // translations are ignored. Next change: starttime of a step
            'stepped grade with invisible steps' => [14, 6000.0, 6000.0, self::NOW + 3600],
            'zero flat amount is ignored' => [15, 4000.0, 4000.0, 0],
            // The endtime of its step (NOW + 100000) is later
            'not yet started stepped grade' => [16, 0.0, 0.0, self::NOW + 7200],
            'salary grade mode without grade ignores free entry amounts' => [17, 0.0, 0.0, 0],
            'valid grade next to grade starting later' => [18, 4000.0, 4000.0, self::NOW + 1],
            'steps of a flat grade are ignored' => [19, 2500.0, 2500.0, 0],
            'hidden job is calculated, too' => [21, 4000.0, 4000.0, 0],
        ];
    }

    #[Test]
    #[DataProvider('effectiveSalaryDataProvider')]
    public function calculateForJobsReturnsEffectiveSalary(
        int $jobUid,
        float $expectedMin,
        float $expectedMax,
        int $expectedValidUntil,
    ): void {
        self::assertSame([$expectedMin, $expectedMax, $expectedValidUntil], $this->calculate($jobUid));
    }

    /**
     * @return array<string, array{int, int, float, float, int}>
     */
    public static function laterPointInTimeDataProvider(): array
    {
        return [
            'grade has started' => [10, self::NOW + 1, 1000.0, 1000.0, 0],
            'endtime has reached the end of the look-ahead' => [12, self::NOW + 1, 0.0, 0.0, 0],
            'endtime is still after the look-ahead' => [12, self::NOW, 5000.0, 5000.0, self::NOW + 86401],
            'step has started, next change is the endtime of another step' => [
                14,
                self::NOW + 3600,
                100.0,
                6000.0,
                self::NOW + 172800,
            ],
            'last step expires within the look-ahead' => [14, self::NOW + 86400, 100.0, 100.0, 0],
        ];
    }

    #[Test]
    #[DataProvider('laterPointInTimeDataProvider')]
    public function calculateForJobsDependsOnGivenPointInTime(
        int $jobUid,
        int $now,
        float $expectedMin,
        float $expectedMax,
        int $expectedValidUntil,
    ): void {
        self::assertSame([$expectedMin, $expectedMax, $expectedValidUntil], $this->calculate($jobUid, $now));
    }

    #[Test]
    public function calculateForJobsCalculatesSeveralJobsAtOnce(): void
    {
        $effectiveSalaries = $this->subject->calculateForJobs([5, 6, 7, 1], self::NOW);

        self::assertSame([1, 5, 6, 7], $this->sortedKeys($effectiveSalaries));
        self::assertSame(3000.0, $effectiveSalaries[7]->getMin());
        self::assertSame(4000.0, $effectiveSalaries[7]->getMax());
    }

    #[Test]
    public function calculateForJobsIgnoresDeletedAndUnknownJobs(): void
    {
        self::assertSame([5], $this->sortedKeys($this->subject->calculateForJobs([20, 999, 5], self::NOW)));
    }

    #[Test]
    public function calculateForJobsWithoutJobsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->calculateForJobs([], self::NOW));
    }

    /**
     * @param array<int, EffectiveSalary> $effectiveSalaries
     * @return int[]
     */
    private function sortedKeys(array $effectiveSalaries): array
    {
        $keys = array_keys($effectiveSalaries);
        sort($keys);

        return $keys;
    }
}
