<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Class Job
 */
class Job extends AbstractEntity
{
    protected string $title = '';

    protected string $referenceNumber = '';

    protected bool $isImport = false;

    protected int $vacancyId = 0;

    protected string $description = '';

    protected ?Address $address = null;

    protected string $link = '';

    protected ?JobArea $jobArea = null;

    protected ?JobType $jobType = null;

    protected ?\DateTime $startDate = null;

    protected ?\DateTime $endingDate = null;

    protected string $employer = '';

    protected ?Address $employerAddress = null;

    protected string $email = '';

    protected ?FileReference $tenderFile = null;

    protected ?FileReference $pdfFiles = null;

    protected int $pdfTstamp = 0;

    protected bool $isInternal = false;

    protected int $salaryMode = 0;

    protected ?SalaryGrade $salaryGrade = null;

    protected float $salaryMin = 0.0;

    protected float $salaryMax = 0.0;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getReferenceNumber(): string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(string $referenceNumber): void
    {
        $this->referenceNumber = $referenceNumber;
    }

    public function isImport(): bool
    {
        return $this->isImport;
    }

    public function setIsImport(bool $isImport): void
    {
        $this->isImport = $isImport;
    }

    public function getVacancyId(): int
    {
        return $this->vacancyId;
    }

    public function setVacancyId(int $vacancyId): void
    {
        $this->vacancyId = $vacancyId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function getJobArea(): ?JobArea
    {
        return $this->jobArea;
    }

    public function setJobArea(JobArea $jobArea): void
    {
        $this->jobArea = $jobArea;
    }

    public function getJobType(): ?JobType
    {
        return $this->jobType;
    }

    public function setJobType(JobType $jobType): void
    {
        $this->jobType = $jobType;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndingDate(): ?\DateTime
    {
        return $this->endingDate;
    }

    public function setEndingDate(\DateTime $endingDate): void
    {
        $this->endingDate = $endingDate;
    }

    public function getEmployer(): string
    {
        return $this->employer;
    }

    public function setEmployer(string $employer): void
    {
        $this->employer = $employer;
    }

    public function getEmployerAddress(): ?Address
    {
        return $this->employerAddress;
    }

    public function setEmployerAddress(Address $employerAddress): void
    {
        $this->employerAddress = $employerAddress;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getTenderFile(): ?FileReference
    {
        return $this->tenderFile;
    }

    public function setTenderFile(FileReference $tenderFile): void
    {
        $this->tenderFile = $tenderFile;
    }

    public function getPdfFiles(): ?FileReference
    {
        return $this->pdfFiles;
    }

    public function setPdfFiles(FileReference $pdfFiles): void
    {
        $this->pdfFiles = $pdfFiles;
    }

    public function getPdfTstamp(): int
    {
        return $this->pdfTstamp;
    }

    public function setPdfTstamp(int $pdfTstamp): void
    {
        $this->pdfTstamp = $pdfTstamp;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function setIsInternal(bool $isInternal): void
    {
        $this->isInternal = $isInternal;
    }

    public function getSalaryMode(): int
    {
        return $this->salaryMode;
    }

    public function setSalaryMode(int $salaryMode): void
    {
        $this->salaryMode = $salaryMode;
    }

    public function getSalaryGrade(): ?SalaryGrade
    {
        return $this->salaryGrade;
    }

    public function setSalaryGrade(SalaryGrade $salaryGrade): void
    {
        $this->salaryGrade = $salaryGrade;
    }

    public function getSalaryMin(): float
    {
        return $this->salaryMin;
    }

    public function setSalaryMin(float $salaryMin): void
    {
        $this->salaryMin = $salaryMin;
    }

    public function getSalaryMax(): float
    {
        return $this->salaryMax;
    }

    public function setSalaryMax(float $salaryMax): void
    {
        $this->salaryMax = $salaryMax;
    }

    /**
     * Lowest payable amount, regardless of salaryMode: the salary grade's
     * minimum (steps or flat amount) if this job references one, otherwise
     * the free-text salaryMin.
     */
    public function getSalaryRangeMin(): float
    {
        if ($this->salaryMode === 1) {
            return $this->salaryMin;
        }

        return $this->salaryGrade?->getMinAmount() ?? 0.0;
    }

    /**
     * Highest payable amount, regardless of salaryMode. Falls back to
     * salaryMin for free-text entries where only a single amount was
     * maintained (salaryMax left empty), so it never reports a smaller
     * maximum than the minimum.
     */
    public function getSalaryRangeMax(): float
    {
        if ($this->salaryMode === 1) {
            return $this->salaryMax > 0.0 ? $this->salaryMax : $this->salaryMin;
        }

        return $this->salaryGrade?->getMaxAmount() ?? 0.0;
    }

    /**
     * False for a single maintained amount (flat salary grade, grade with
     * only one existing step, or free-text entry without a max), so
     * templates can render "3.220,85 €" instead of a meaningless
     * "3.220,85 € - 3.220,85 €" range.
     */
    public function getHasSalaryRange(): bool
    {
        return $this->getSalaryRangeMax() > $this->getSalaryRangeMin();
    }

    public function getHasSalaryInformation(): bool
    {
        return $this->getSalaryRangeMin() > 0.0 || $this->getSalaryRangeMax() > 0.0;
    }
}
