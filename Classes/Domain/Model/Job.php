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

class Job extends AbstractEntity
{
    protected string $title = '';

    protected string $referenceNumber = '';

    protected string $subtitle = '';

    protected string $pathSegment = '';

    protected string $description = '';

    protected string $offer = '';

    protected string $requirements = '';

    protected string $furtherInformation = '';

    protected bool $isImport = false;

    protected int $vacancyId = 0;

    protected ?Address $address = null;

    protected ?JobRole $jobRole = null;

    protected ?JobType $jobType = null;

    protected ?ContractType $contractType = null;

    /**
     * @var ObjectStorage<JobArea>
     */
    protected ObjectStorage $jobAreas;

    protected ?TenderType $tenderType = null;

    protected int $salaryMode = 0;

    /**
     * @var ObjectStorage<SalaryGrade>
     */
    protected ObjectStorage $salaryGrades;

    protected float $salaryMin = 0.0;

    protected float $salaryMax = 0.0;

    /**
     * @var ObjectStorage<Benefit>
     */
    protected ObjectStorage $benefits;

    protected string $employer = '';

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $employerLogo;

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

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $headerLogo;

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $tenderFile;

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $pdfFiles;

    protected int $pdfTstamp = 0;

    /**
     * @var ObjectStorage<Job>
     */
    protected ObjectStorage $relatedJobs;

    protected string $link = '';

    public function __construct()
    {
        $this->jobAreas = new ObjectStorage();
        $this->salaryGrades = new ObjectStorage();
        $this->benefits = new ObjectStorage();
        $this->employerLogo = new ObjectStorage();
        $this->headerLogo = new ObjectStorage();
        $this->tenderFile = new ObjectStorage();
        $this->pdfFiles = new ObjectStorage();
        $this->relatedJobs = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        $this->jobAreas ??= new ObjectStorage();
        $this->salaryGrades ??= new ObjectStorage();
        $this->benefits ??= new ObjectStorage();
        $this->employerLogo ??= new ObjectStorage();
        $this->headerLogo ??= new ObjectStorage();
        $this->tenderFile ??= new ObjectStorage();
        $this->pdfFiles ??= new ObjectStorage();
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

    public function getPathSegment(): string
    {
        return $this->pathSegment;
    }

    public function setPathSegment(string $pathSegment): void
    {
        $this->pathSegment = $pathSegment;
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

    public function getIsImport(): bool
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

    /**
     * @return ObjectStorage<JobArea>
     */
    public function getJobAreas(): ObjectStorage
    {
        return $this->jobAreas;
    }

    /**
     * @param ObjectStorage<JobArea> $jobAreas
     */
    public function setJobAreas(ObjectStorage $jobAreas): void
    {
        $this->jobAreas = $jobAreas;
    }

    public function addJobArea(JobArea $jobArea): void
    {
        $this->jobAreas->attach($jobArea);
    }

    public function removeJobArea(JobArea $jobArea): void
    {
        $this->jobAreas->detach($jobArea);
    }

    /**
     * First selected (and visible) job area. Kept for templates which only
     * render a single job area via {job.jobArea}. Use {job.jobAreas} to
     * render all selected job areas.
     */
    public function getJobArea(): ?JobArea
    {
        foreach ($this->jobAreas as $jobArea) {
            return $jobArea;
        }

        return null;
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

    public function addSalaryGrade(SalaryGrade $salaryGrade): void
    {
        $this->salaryGrades->attach($salaryGrade);
    }

    public function removeSalaryGrade(SalaryGrade $salaryGrade): void
    {
        $this->salaryGrades->detach($salaryGrade);
    }

    /**
     * First selected (and visible) salary grade. Kept for templates which
     * only render a single grade via {job.salaryGrade}. Use
     * {job.salaryGrades} to render all selected grades.
     */
    public function getSalaryGrade(): ?SalaryGrade
    {
        foreach ($this->salaryGrades as $salaryGrade) {
            return $salaryGrade;
        }

        return null;
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
     * @return ObjectStorage<Benefit>
     */
    public function getBenefits(): ObjectStorage
    {
        return $this->benefits;
    }

    /**
     * @param ObjectStorage<Benefit> $benefits
     */
    public function setBenefits(ObjectStorage $benefits): void
    {
        $this->benefits = $benefits;
    }

    public function addBenefit(Benefit $benefit): void
    {
        $this->benefits->attach($benefit);
    }

    public function removeBenefit(Benefit $benefit): void
    {
        $this->benefits->detach($benefit);
    }

    public function getEmployer(): string
    {
        return $this->employer;
    }

    public function setEmployer(string $employer): void
    {
        $this->employer = $employer;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getEmployerLogo(): ObjectStorage
    {
        return $this->employerLogo;
    }

    /**
     * @param ObjectStorage<FileReference> $employerLogo
     */
    public function setEmployerLogo(ObjectStorage $employerLogo): void
    {
        $this->employerLogo = $employerLogo;
    }

    public function addEmployerLogo(FileReference $employerLogo): void
    {
        $this->employerLogo->attach($employerLogo);
    }

    public function removeEmployerLogo(FileReference $employerLogo): void
    {
        $this->employerLogo->detach($employerLogo);
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

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getHeaderLogo(): ObjectStorage
    {
        return $this->headerLogo;
    }

    /**
     * @param ObjectStorage<FileReference> $headerLogo
     */
    public function setHeaderLogo(ObjectStorage $headerLogo): void
    {
        $this->headerLogo = $headerLogo;
    }

    public function addHeaderLogo(FileReference $headerLogo): void
    {
        $this->headerLogo->attach($headerLogo);
    }

    public function removeHeaderLogo(FileReference $headerLogo): void
    {
        $this->headerLogo->detach($headerLogo);
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getTenderFile(): ObjectStorage
    {
        return $this->tenderFile;
    }

    /**
     * @param ObjectStorage<FileReference> $tenderFile
     */
    public function setTenderFile(ObjectStorage $tenderFile): void
    {
        $this->tenderFile = $tenderFile;
    }

    public function addTenderFile(FileReference $tenderFile): void
    {
        $this->tenderFile->attach($tenderFile);
    }

    public function removeTenderFile(FileReference $tenderFile): void
    {
        $this->tenderFile->detach($tenderFile);
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getPdfFiles(): ObjectStorage
    {
        return $this->pdfFiles;
    }

    /**
     * @param ObjectStorage<FileReference> $pdfFiles
     */
    public function setPdfFiles(ObjectStorage $pdfFiles): void
    {
        $this->pdfFiles = $pdfFiles;
    }

    public function addPdfFile(FileReference $pdfFile): void
    {
        $this->pdfFiles->attach($pdfFile);
    }

    public function removePdfFile(FileReference $pdfFile): void
    {
        $this->pdfFiles->detach($pdfFile);
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

    /**
     * Range over all steps (or flat amounts) of all selected salary grades,
     * from the lowest to the highest amount. Ignores salaryMode.
     */
    public function getSalaryGradesRange(): SalaryRange
    {
        return SalaryRange::fromSalaryGrades($this->salaryGrades);
    }

    /**
     * Lowest payable amount, regardless of salaryMode: the lowest amount of
     * all selected salary grades (steps or flat amounts), otherwise the
     * free-text salaryMin.
     */
    public function getSalaryRangeMin(): float
    {
        if ($this->salaryMode === 1) {
            return $this->salaryMin;
        }

        return $this->getSalaryGradesRange()->getMin();
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

        return $this->getSalaryGradesRange()->getMax();
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
