<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Class SalaryTable
 */
class SalaryTable extends AbstractEntity
{
    protected string $title = '';

    protected string $description = '';

    /**
     * @var ObjectStorage<SalaryGrade>
     */
    protected ObjectStorage $salaryGrades;

    public function __construct()
    {
        $this->salaryGrades = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        $this->salaryGrades ??= new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return ObjectStorage<SalaryGrade>
     */
    public function getSalaryGrades(): ObjectStorage
    {
        return $this->salaryGrades;
    }

    /**
     * @param ObjectStorage<SalaryGrade> $salaryGrades
     */
    public function setSalaryGrades(ObjectStorage $salaryGrades): void
    {
        $this->salaryGrades = $salaryGrades;
    }
}
