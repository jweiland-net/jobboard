<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Class Job
 */
class Job extends AbstractEntity
{
    protected string $title = '';

    protected string $referenceNumber = '';

    protected string $subtitle = '';

    protected string $description = '';

    protected string $offer = '';

    protected string $requirements = '';

    protected string $furtherInformation = '';

    protected bool $isImport = false;

    protected int $vacancyId = 0;

    protected ?Address $address = null;

    protected ?JobRole $jobRole = null;

    protected ?JobArea $jobArea = null;

    protected ?JobType $jobType = null;

    protected ?ContractType $contractType = null;

    protected ?TenderType $tenderType = null;

    protected int $salaryMode = 0;

    protected ?SalaryGrade $salaryGrade = null;

    protected float $salaryMin = 0.0;

    protected float $salaryMax = 0.0;

    protected ?Benefit $benefits = null;

    protected string $employer = '';

    protected ?FileReference $employerLogo = null;

    protected ?Address $employerAddress = null;

    protected string $firstName = '';

    protected string $lastName = '';

    protected string $email = '';

    protected string $telephone = '';

    protected string $function = '';

    protected ?\DateTime $startDate = null;

    protected ?\DateTime $endingDate = null;

    protected ?\DateTime $applicationDeadline = null;

    protected string $applicationGuidelines = '';

    protected ?FileReference $headerLogo = null;

    protected ?FileReference $tenderFile = null;

    protected ?FileReference $pdfFiles = null;

    protected int $pdfTstamp = 0;

    /**
     * @var ObjectStorage<Job>
     */
    protected ObjectStorage $relatedJobs;

    protected string $link = '';

    protected bool $isInternal = false;

    public function __construct()
    {
        $this->relatedJobs = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        $this->relatedJobs ??= new ObjectStorage();
    }

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

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getOffer(): string
    {
        return $this->offer;
    }

    public function setOffer(string $offer): void
    {
        $this->offer = $offer;
    }

    public function getRequirements(): string
    {
        return $this->requirements;
    }

    public function setRequirements(string $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function getFurtherInformation(): string
    {
        return $this->furtherInformation;
    }

    public function setFurtherInformation(string $furtherInformation): void
    {
        $this->furtherInformation = $furtherInformation;
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

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function getJobRole(): ?JobRole
    {
        return $this->jobRole;
    }

    public function setJobRole(JobRole $jobRole): void
    {
        $this->jobRole = $jobRole;
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

    public function getContractType(): ?ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType $contractType): void
    {
        $this->contractType = $contractType;
    }

    public function getTenderType(): ?TenderType
    {
        return $this->tenderType;
    }

    public function setTenderType(TenderType $tenderType): void
    {
        $this->tenderType = $tenderType;
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

    public function getBenefits(): ?Benefit
    {
        return $this->benefits;
    }

    public function setBenefits(Benefit $benefits): void
    {
        $this->benefits = $benefits;
    }

    public function getEmployer(): string
    {
        return $this->employer;
    }

    public function setEmployer(string $employer): void
    {
        $this->employer = $employer;
    }

    public function getEmployerLogo(): ?FileReference
    {
        return $this->employerLogo;
    }

    public function setEmployerLogo(FileReference $employerLogo): void
    {
        $this->employerLogo = $employerLogo;
    }

    public function getEmployerAddress(): ?Address
    {
        return $this->employerAddress;
    }

    public function setEmployerAddress(Address $employerAddress): void
    {
        $this->employerAddress = $employerAddress;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): void
    {
        $this->telephone = $telephone;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    public function setFunction(string $function): void
    {
        $this->function = $function;
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

    public function getApplicationDeadline(): ?\DateTime
    {
        return $this->applicationDeadline;
    }

    public function setApplicationDeadline(\DateTime $applicationDeadline): void
    {
        $this->applicationDeadline = $applicationDeadline;
    }

    public function getApplicationGuidelines(): string
    {
        return $this->applicationGuidelines;
    }

    public function setApplicationGuidelines(string $applicationGuidelines): void
    {
        $this->applicationGuidelines = $applicationGuidelines;
    }

    public function getHeaderLogo(): ?FileReference
    {
        return $this->headerLogo;
    }

    public function setHeaderLogo(FileReference $headerLogo): void
    {
        $this->headerLogo = $headerLogo;
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

    /**
     * @return ObjectStorage<Job>
     */
    public function getRelatedJobs(): ObjectStorage
    {
        return $this->relatedJobs;
    }

    /**
     * @param ObjectStorage<Job> $relatedJobs
     */
    public function setRelatedJobs(ObjectStorage $relatedJobs): void
    {
        $this->relatedJobs = $relatedJobs;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function setIsInternal(bool $isInternal): void
    {
        $this->isInternal = $isInternal;
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
