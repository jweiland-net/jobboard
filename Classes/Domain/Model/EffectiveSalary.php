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
 * Effective salary of a job as stored in the columns "effective_salary_min",
 * "effective_salary_max" and "effective_salary_valid_until". Pure value
 * object, same rules as Job::getSalaryRangeMin()/getSalaryRangeMax(), except
 * the look-ahead for expiring grades/steps of EffectiveSalaryCalculator.
 *
 * "validUntil" is the next starttime or endtime of a related salary grade or
 * step, which has not been applied yet. 0 = no upcoming change. An endtime is
 * applied a look-ahead earlier, see EffectiveSalaryCalculatorInterface.
 */
final readonly class EffectiveSalary
{
    public const FIELD_MIN = 'effective_salary_min';

    public const FIELD_MAX = 'effective_salary_max';

    public const FIELD_VALID_UNTIL = 'effective_salary_valid_until';

    public const FIELDS = [
        self::FIELD_MIN,
        self::FIELD_MAX,
        self::FIELD_VALID_UNTIL,
    ];

    private function __construct(
        private float $min,
        private float $max,
        private int $validUntil,
    ) {}

    /**
     * Free-text salary (salary_mode 1): a single maintained amount (empty
     * maximum) is used as maximum, too.
     */
    public static function fromFreeEntry(float $salaryMin, float $salaryMax): self
    {
        $min = max($salaryMin, 0.0);

        return new self($min, $salaryMax > 0.0 ? $salaryMax : $min, 0);
    }

    /**
     * Salary grades (salary_mode 0): lowest and highest positive amount of
     * all visible flat amounts and steps. Without any, the result is 0 - 0.
     *
     * @param float[] $amounts
     */
    public static function fromAmounts(array $amounts, int $validUntil): self
    {
        $amounts = array_filter($amounts, static fn(float $amount): bool => $amount > 0.0);

        return $amounts === []
            ? new self(0.0, 0.0, max($validUntil, 0))
            : new self(min($amounts), max($amounts), max($validUntil, 0));
    }

    /**
     * @param array<string, mixed> $row Row with the effective salary columns
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (float)($row[self::FIELD_MIN] ?? 0.0),
            (float)($row[self::FIELD_MAX] ?? 0.0),
            (int)($row[self::FIELD_VALID_UNTIL] ?? 0),
        );
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getValidUntil(): int
    {
        return $this->validUntil;
    }

    public function getHasSalaryInformation(): bool
    {
        return $this->min > 0.0 || $this->max > 0.0;
    }

    /**
     * Compares with the precision of the database columns (decimal 10,2)
     */
    public function equals(self $effectiveSalary): bool
    {
        return $this->toFieldArray() === $effectiveSalary->toFieldArray();
    }

    /**
     * Field values for DataHandler. Decimals are formatted as strings with
     * 2 decimals, like the database returns them.
     *
     * @return array<string, string|int>
     */
    public function toFieldArray(): array
    {
        return [
            self::FIELD_MIN => number_format($this->min, 2, '.', ''),
            self::FIELD_MAX => number_format($this->max, 2, '.', ''),
            self::FIELD_VALID_UNTIL => $this->validUntil,
        ];
    }
}
