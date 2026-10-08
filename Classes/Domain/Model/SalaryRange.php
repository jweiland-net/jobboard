<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

/**
 * Immutable salary range (lowest to highest payable amount).
 *
 * For a job referencing several salary grades, the range spans the lowest
 * amount of all steps (or flat amounts) of all grades up to the highest one,
 * e.g. "S 8a" (3000 - 4000) and "S 8b" (3500 - 4500) result in 3000 - 4500.
 */
final readonly class SalaryRange
{
    public function __construct(
        private float $min = 0.0,
        private float $max = 0.0,
    ) {
        if ($min < 0.0 || $max < $min) {
            throw new \InvalidArgumentException(
                'A salary range requires 0 <= min <= max, got min ' . $min . ' and max ' . $max . '.',
                1791446400,
            );
        }
    }

    /**
     * Grades without any amount (e.g. stepped grades whose steps are all
     * hidden/expired) and amounts <= 0 are ignored. If no positive amount is
     * left at all, an empty range (0 - 0) is returned.
     *
     * @param iterable<SalaryGrade> $salaryGrades
     */
    public static function fromSalaryGrades(iterable $salaryGrades): self
    {
        $amounts = [];
        foreach ($salaryGrades as $salaryGrade) {
            foreach ($salaryGrade->getAmounts() as $amount) {
                if ($amount > 0.0) {
                    $amounts[] = $amount;
                }
            }
        }

        return $amounts === [] ? new self() : new self(min($amounts), max($amounts));
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    /**
     * False for an empty range and for a single amount (min === max).
     */
    public function getHasRange(): bool
    {
        return $this->max > $this->min;
    }

    public function getIsEmpty(): bool
    {
        return $this->max <= 0.0;
    }
}
