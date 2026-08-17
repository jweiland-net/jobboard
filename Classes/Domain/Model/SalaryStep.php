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

/**
 * Class SalaryStep
 */
class SalaryStep extends AbstractEntity
{
    protected string $stepLabel = '';

    protected float $amount = 0.0;

    protected ?SalaryGrade $salaryGrade = null;

    public function getStepLabel(): string
    {
        return $this->stepLabel;
    }

    public function setStepLabel(string $stepLabel): void
    {
        $this->stepLabel = $stepLabel;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getSalaryGrade(): ?SalaryGrade
    {
        return $this->salaryGrade;
    }

    public function setSalaryGrade(SalaryGrade $salaryGrade): void
    {
        $this->salaryGrade = $salaryGrade;
    }
}
