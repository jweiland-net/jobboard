<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Class SalaryGrade
 */
class SalaryGrade extends AbstractEntity
{
    protected string $title = '';

    protected bool $hasSteps = true;

    protected float $flatAmount = 0.0;

    protected ?SalaryTable $salaryTable = null;

    /**
     * @var ObjectStorage<SalaryStep>
     */
    protected ObjectStorage $salarySteps;

    public function __construct()
    {
        $this->salarySteps = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        $this->salarySteps ??= new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function hasSteps(): bool
    {
        return $this->hasSteps;
    }

    public function setHasSteps(bool $hasSteps): void
    {
        $this->hasSteps = $hasSteps;
    }

    public function getFlatAmount(): float
    {
        return $this->flatAmount;
    }

    public function setFlatAmount(float $flatAmount): void
    {
        $this->flatAmount = $flatAmount;
    }

    public function getSalaryTable(): ?SalaryTable
    {
        return $this->salaryTable;
    }

    public function setSalaryTable(SalaryTable $salaryTable): void
    {
        $this->salaryTable = $salaryTable;
    }

    /**
     * @return ObjectStorage<SalaryStep>
     */
    public function getSalarySteps(): ObjectStorage
    {
        return $this->salarySteps;
    }

    /**
     * @param ObjectStorage<SalaryStep> $salarySteps
     */
    public function setSalarySteps(ObjectStorage $salarySteps): void
    {
        $this->salarySteps = $salarySteps;
    }

    /**
     * Minimum amount of this grade: the flat amount if it has no steps, otherwise
     * the lowest amount among the steps that actually exist (gaps are not positional).
     */
    public function getMinAmount(): float
    {
        if (!$this->hasSteps) {
            return $this->flatAmount;
        }

        $amounts = $this->getStepAmounts();

        return $amounts === [] ? 0.0 : min($amounts);
    }

    /**
     * Maximum amount of this grade: the flat amount if it has no steps, otherwise
     * the highest amount among the steps that actually exist (gaps are not positional).
     */
    public function getMaxAmount(): float
    {
        if (!$this->hasSteps) {
            return $this->flatAmount;
        }

        $amounts = $this->getStepAmounts();

        return $amounts === [] ? 0.0 : max($amounts);
    }

    /**
     * @return float[]
     */
    private function getStepAmounts(): array
    {
        $amounts = [];
        foreach ($this->salarySteps as $step) {
            $amounts[] = $step->getAmount();
        }

        return $amounts;
    }
}
