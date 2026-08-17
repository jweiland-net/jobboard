<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\Address;
use JWeiland\Jobboard\Domain\Model\Benefit;
use JWeiland\Jobboard\Domain\Model\ContractType;
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\SalaryGrade;
use JWeiland\Jobboard\Domain\Model\SalaryStep;
use JWeiland\Jobboard\Domain\Model\TenderType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class JobTest extends UnitTestCase
{
    protected Job $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Job();
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    #[Test]
    public function getTitleInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function setTitleSetsTitle(): void
    {
        $this->subject->setTitle('Skilled Craftsman (m/f/d)');

        self::assertSame(
            'Skilled Craftsman (m/f/d)',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function getReferenceNumberInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getReferenceNumber(),
        );
    }

    #[Test]
    public function setReferenceNumberSetsReferenceNumber(): void
    {
        $this->subject->setReferenceNumber('26-170');

        self::assertSame(
            '26-170',
            $this->subject->getReferenceNumber(),
        );
    }

    #[Test]
    public function getSubtitleInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getSubtitle(),
        );
    }

    #[Test]
    public function setSubtitleSetsSubtitle(): void
    {
        $this->subject->setSubtitle('Join our team');

        self::assertSame(
            'Join our team',
            $this->subject->getSubtitle(),
        );
    }

    #[Test]
    public function getDescriptionInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getDescription(),
        );
    }

    #[Test]
    public function setDescriptionSetsDescription(): void
    {
        $this->subject->setDescription('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getDescription(),
        );
    }

    #[Test]
    public function getOfferInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getOffer(),
        );
    }

    #[Test]
    public function setOfferSetsOffer(): void
    {
        $this->subject->setOffer('Flexible working hours');

        self::assertSame(
            'Flexible working hours',
            $this->subject->getOffer(),
        );
    }

    #[Test]
    public function getRequirementsInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getRequirements(),
        );
    }

    #[Test]
    public function setRequirementsSetsRequirements(): void
    {
        $this->subject->setRequirements('Completed vocational training');

        self::assertSame(
            'Completed vocational training',
            $this->subject->getRequirements(),
        );
    }

    #[Test]
    public function getFurtherInformationInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getFurtherInformation(),
        );
    }

    #[Test]
    public function setFurtherInformationSetsFurtherInformation(): void
    {
        $this->subject->setFurtherInformation('Start date negotiable');

        self::assertSame(
            'Start date negotiable',
            $this->subject->getFurtherInformation(),
        );
    }

    #[Test]
    public function getIsImportInitiallyReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->getIsImport(),
        );
    }

    #[Test]
    public function setIsImportSetsIsImport(): void
    {
        $this->subject->setIsImport(true);

        self::assertTrue(
            $this->subject->getIsImport(),
        );
    }

    #[Test]
    public function getVacancyIdInitiallyReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getVacancyId(),
        );
    }

    #[Test]
    public function setVacancyIdSetsVacancyId(): void
    {
        $this->subject->setVacancyId(12345);

        self::assertSame(
            12345,
            $this->subject->getVacancyId(),
        );
    }

    #[Test]
    public function getAddressInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getAddress());
    }

    #[Test]
    public function setAddressSetsAddress(): void
    {
        $instance = new Address();
        $this->subject->setAddress($instance);

        self::assertSame(
            $instance,
            $this->subject->getAddress(),
        );
    }

    #[Test]
    public function getJobRoleInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getJobRole());
    }

    #[Test]
    public function setJobRoleSetsJobRole(): void
    {
        $instance = new JobRole();
        $this->subject->setJobRole($instance);

        self::assertSame(
            $instance,
            $this->subject->getJobRole(),
        );
    }

    #[Test]
    public function getJobAreaInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getJobArea());
    }

    #[Test]
    public function setJobAreaSetsJobArea(): void
    {
        $instance = new JobArea();
        $this->subject->setJobArea($instance);

        self::assertSame(
            $instance,
            $this->subject->getJobArea(),
        );
    }

    #[Test]
    public function getJobTypeInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getJobType());
    }

    #[Test]
    public function setJobTypeSetsJobType(): void
    {
        $instance = new JobType();
        $this->subject->setJobType($instance);

        self::assertSame(
            $instance,
            $this->subject->getJobType(),
        );
    }

    #[Test]
    public function getContractTypeInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getContractType());
    }

    #[Test]
    public function setContractTypeSetsContractType(): void
    {
        $instance = new ContractType();
        $this->subject->setContractType($instance);

        self::assertSame(
            $instance,
            $this->subject->getContractType(),
        );
    }

    #[Test]
    public function getTenderTypeInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getTenderType());
    }

    #[Test]
    public function setTenderTypeSetsTenderType(): void
    {
        $instance = new TenderType();
        $this->subject->setTenderType($instance);

        self::assertSame(
            $instance,
            $this->subject->getTenderType(),
        );
    }

    #[Test]
    public function getSalaryModeInitiallyReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getSalaryMode(),
        );
    }

    #[Test]
    public function setSalaryModeSetsSalaryMode(): void
    {
        $this->subject->setSalaryMode(1);

        self::assertSame(
            1,
            $this->subject->getSalaryMode(),
        );
    }

    #[Test]
    public function getSalaryGradeInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getSalaryGrade());
    }

    #[Test]
    public function setSalaryGradeSetsSalaryGrade(): void
    {
        $instance = new SalaryGrade();
        $this->subject->setSalaryGrade($instance);

        self::assertSame(
            $instance,
            $this->subject->getSalaryGrade(),
        );
    }

    #[Test]
    public function getSalaryMinInitiallyReturnsZero(): void
    {
        self::assertSame(
            0.0,
            $this->subject->getSalaryMin(),
        );
    }

    #[Test]
    public function setSalaryMinSetsSalaryMin(): void
    {
        $this->subject->setSalaryMin(2500.5);

        self::assertSame(
            2500.5,
            $this->subject->getSalaryMin(),
        );
    }

    #[Test]
    public function getSalaryMaxInitiallyReturnsZero(): void
    {
        self::assertSame(
            0.0,
            $this->subject->getSalaryMax(),
        );
    }

    #[Test]
    public function setSalaryMaxSetsSalaryMax(): void
    {
        $this->subject->setSalaryMax(3500.5);

        self::assertSame(
            3500.5,
            $this->subject->getSalaryMax(),
        );
    }

    #[Test]
    public function getBenefitsInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getBenefits());
    }

    #[Test]
    public function setBenefitsSetsBenefits(): void
    {
        $instance = new Benefit();
        $this->subject->setBenefits($instance);

        self::assertSame(
            $instance,
            $this->subject->getBenefits(),
        );
    }

    #[Test]
    public function getEmployerInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getEmployer(),
        );
    }

    #[Test]
    public function setEmployerSetsEmployer(): void
    {
        $this->subject->setEmployer('Example Employer Ltd.');

        self::assertSame(
            'Example Employer Ltd.',
            $this->subject->getEmployer(),
        );
    }

    #[Test]
    public function getEmployerLogoInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getEmployerLogo(),
        );
    }

    #[Test]
    public function setEmployerLogoSetsEmployerLogo(): void
    {
        $object = new FileReference();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setEmployerLogo($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getEmployerLogo(),
        );
    }

    #[Test]
    public function addEmployerLogoAddsEmployerLogo(): void
    {
        $instance = new FileReference();
        $this->subject->addEmployerLogo($instance);

        self::assertTrue(
            $this->subject->getEmployerLogo()->contains($instance),
        );
    }

    #[Test]
    public function removeEmployerLogoRemovesEmployerLogo(): void
    {
        $instance = new FileReference();
        $this->subject->addEmployerLogo($instance);
        $this->subject->removeEmployerLogo($instance);

        self::assertFalse(
            $this->subject->getEmployerLogo()->contains($instance),
        );
    }

    #[Test]
    public function getEmployerAddressInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getEmployerAddress());
    }

    #[Test]
    public function setEmployerAddressSetsEmployerAddress(): void
    {
        $instance = new Address();
        $this->subject->setEmployerAddress($instance);

        self::assertSame(
            $instance,
            $this->subject->getEmployerAddress(),
        );
    }

    #[Test]
    public function getFirstNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getFirstName(),
        );
    }

    #[Test]
    public function setFirstNameSetsFirstName(): void
    {
        $this->subject->setFirstName('Jane');

        self::assertSame(
            'Jane',
            $this->subject->getFirstName(),
        );
    }

    #[Test]
    public function getLastNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getLastName(),
        );
    }

    #[Test]
    public function setLastNameSetsLastName(): void
    {
        $this->subject->setLastName('Doe');

        self::assertSame(
            'Doe',
            $this->subject->getLastName(),
        );
    }

    #[Test]
    public function getEmailInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getEmail(),
        );
    }

    #[Test]
    public function setEmailSetsEmail(): void
    {
        $this->subject->setEmail('test@example.com');

        self::assertSame(
            'test@example.com',
            $this->subject->getEmail(),
        );
    }

    #[Test]
    public function getTelephoneInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getTelephone(),
        );
    }

    #[Test]
    public function setTelephoneSetsTelephone(): void
    {
        $this->subject->setTelephone('+49 123 456789');

        self::assertSame(
            '+49 123 456789',
            $this->subject->getTelephone(),
        );
    }

    #[Test]
    public function getFunctionInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getFunction(),
        );
    }

    #[Test]
    public function setFunctionSetsFunction(): void
    {
        $this->subject->setFunction('HR Manager');

        self::assertSame(
            'HR Manager',
            $this->subject->getFunction(),
        );
    }

    #[Test]
    public function getStartDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getStartDate());
    }

    #[Test]
    public function setStartDateSetsStartDate(): void
    {
        $date = new \DateTime();
        $this->subject->setStartDate($date);

        self::assertSame(
            $date,
            $this->subject->getStartDate(),
        );
    }

    #[Test]
    public function getEndingDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getEndingDate());
    }

    #[Test]
    public function setEndingDateSetsEndingDate(): void
    {
        $date = new \DateTime();
        $this->subject->setEndingDate($date);

        self::assertSame(
            $date,
            $this->subject->getEndingDate(),
        );
    }

    #[Test]
    public function getApplicationDeadlineInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getApplicationDeadline());
    }

    #[Test]
    public function setApplicationDeadlineSetsApplicationDeadline(): void
    {
        $date = new \DateTime();
        $this->subject->setApplicationDeadline($date);

        self::assertSame(
            $date,
            $this->subject->getApplicationDeadline(),
        );
    }

    #[Test]
    public function getApplicationGuidelinesInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getApplicationGuidelines(),
        );
    }

    #[Test]
    public function setApplicationGuidelinesSetsApplicationGuidelines(): void
    {
        $this->subject->setApplicationGuidelines('Please apply via our online form.');

        self::assertSame(
            'Please apply via our online form.',
            $this->subject->getApplicationGuidelines(),
        );
    }

    #[Test]
    public function getHeaderLogoInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getHeaderLogo(),
        );
    }

    #[Test]
    public function setHeaderLogoSetsHeaderLogo(): void
    {
        $object = new FileReference();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setHeaderLogo($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getHeaderLogo(),
        );
    }

    #[Test]
    public function addHeaderLogoAddsHeaderLogo(): void
    {
        $instance = new FileReference();
        $this->subject->addHeaderLogo($instance);

        self::assertTrue(
            $this->subject->getHeaderLogo()->contains($instance),
        );
    }

    #[Test]
    public function removeHeaderLogoRemovesHeaderLogo(): void
    {
        $instance = new FileReference();
        $this->subject->addHeaderLogo($instance);
        $this->subject->removeHeaderLogo($instance);

        self::assertFalse(
            $this->subject->getHeaderLogo()->contains($instance),
        );
    }

    #[Test]
    public function getTenderFileInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getTenderFile(),
        );
    }

    #[Test]
    public function setTenderFileSetsTenderFile(): void
    {
        $object = new FileReference();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setTenderFile($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getTenderFile(),
        );
    }

    #[Test]
    public function addTenderFileAddsTenderFile(): void
    {
        $instance = new FileReference();
        $this->subject->addTenderFile($instance);

        self::assertTrue(
            $this->subject->getTenderFile()->contains($instance),
        );
    }

    #[Test]
    public function removeTenderFileRemovesTenderFile(): void
    {
        $instance = new FileReference();
        $this->subject->addTenderFile($instance);
        $this->subject->removeTenderFile($instance);

        self::assertFalse(
            $this->subject->getTenderFile()->contains($instance),
        );
    }

    #[Test]
    public function getPdfFilesInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getPdfFiles(),
        );
    }

    #[Test]
    public function setPdfFilesSetsPdfFiles(): void
    {
        $object = new FileReference();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setPdfFiles($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getPdfFiles(),
        );
    }

    #[Test]
    public function addPdfFileAddsPdfFile(): void
    {
        $instance = new FileReference();
        $this->subject->addPdfFile($instance);

        self::assertTrue(
            $this->subject->getPdfFiles()->contains($instance),
        );
    }

    #[Test]
    public function removePdfFileRemovesPdfFile(): void
    {
        $instance = new FileReference();
        $this->subject->addPdfFile($instance);
        $this->subject->removePdfFile($instance);

        self::assertFalse(
            $this->subject->getPdfFiles()->contains($instance),
        );
    }

    #[Test]
    public function getPdfTstampInitiallyReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getPdfTstamp(),
        );
    }

    #[Test]
    public function setPdfTstampSetsPdfTstamp(): void
    {
        $this->subject->setPdfTstamp(1234567890);

        self::assertSame(
            1234567890,
            $this->subject->getPdfTstamp(),
        );
    }

    #[Test]
    public function getRelatedJobsInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getRelatedJobs(),
        );
    }

    #[Test]
    public function setRelatedJobsSetsRelatedJobs(): void
    {
        $object = new Job();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setRelatedJobs($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getRelatedJobs(),
        );
    }

    #[Test]
    public function getLinkInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getLink(),
        );
    }

    #[Test]
    public function setLinkSetsLink(): void
    {
        $this->subject->setLink('https://example.com');

        self::assertSame(
            'https://example.com',
            $this->subject->getLink(),
        );
    }

    #[Test]
    public function getIsInternalInitiallyReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->getIsInternal(),
        );
    }

    #[Test]
    public function setIsInternalSetsIsInternal(): void
    {
        $this->subject->setIsInternal(true);

        self::assertTrue(
            $this->subject->getIsInternal(),
        );
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithoutAnySalaryInformationReturnZero(): void
    {
        self::assertSame(0.0, $this->subject->getSalaryRangeMin());
        self::assertSame(0.0, $this->subject->getSalaryRangeMax());
        self::assertFalse($this->subject->getHasSalaryInformation());
        self::assertFalse($this->subject->getHasSalaryRange());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithGradeModeAndSteppedGradeUseLowestAndHighestStepAmount(): void
    {
        $salaryGrade = new SalaryGrade();
        $salaryGrade->setHasSteps(true);

        $stepOne = new SalaryStep();
        $stepOne->setAmount(3220.85);
        $salaryGrade->getSalarySteps()->attach($stepOne);

        $stepTwo = new SalaryStep();
        $stepTwo->setAmount(3314.32);
        $salaryGrade->getSalarySteps()->attach($stepTwo);

        $stepThree = new SalaryStep();
        $stepThree->setAmount(3407.74);
        $salaryGrade->getSalarySteps()->attach($stepThree);

        $this->subject->setSalaryMode(0);
        $this->subject->setSalaryGrade($salaryGrade);

        self::assertSame(3220.85, $this->subject->getSalaryRangeMin());
        self::assertSame(3407.74, $this->subject->getSalaryRangeMax());
        self::assertTrue($this->subject->getHasSalaryRange());
        self::assertTrue($this->subject->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithGradeModeAndFlatGradeReturnSameAmountForMinAndMax(): void
    {
        $salaryGrade = new SalaryGrade();
        $salaryGrade->setHasSteps(false);
        $salaryGrade->setFlatAmount(3500.0);

        $this->subject->setSalaryMode(0);
        $this->subject->setSalaryGrade($salaryGrade);

        self::assertSame(3500.0, $this->subject->getSalaryRangeMin());
        self::assertSame(3500.0, $this->subject->getSalaryRangeMax());
        self::assertFalse($this->subject->getHasSalaryRange());
        self::assertTrue($this->subject->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithGradeModeAndWithoutSalaryGradeReturnZero(): void
    {
        $this->subject->setSalaryMode(0);

        self::assertSame(0.0, $this->subject->getSalaryRangeMin());
        self::assertSame(0.0, $this->subject->getSalaryRangeMax());
        self::assertFalse($this->subject->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFreeEntryModeAndBothAmountsUseMinAndMax(): void
    {
        $this->subject->setSalaryMode(1);
        $this->subject->setSalaryMin(2500.0);
        $this->subject->setSalaryMax(3200.0);

        self::assertSame(2500.0, $this->subject->getSalaryRangeMin());
        self::assertSame(3200.0, $this->subject->getSalaryRangeMax());
        self::assertTrue($this->subject->getHasSalaryRange());
        self::assertTrue($this->subject->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFreeEntryModeAndOnlyMinAmountFallsBackMaxToMin(): void
    {
        $this->subject->setSalaryMode(1);
        $this->subject->setSalaryMin(2500.0);

        self::assertSame(2500.0, $this->subject->getSalaryRangeMin());
        self::assertSame(2500.0, $this->subject->getSalaryRangeMax());
        self::assertFalse($this->subject->getHasSalaryRange());
        self::assertTrue($this->subject->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFreeEntryModeAndNoAmountsReturnZero(): void
    {
        $this->subject->setSalaryMode(1);

        self::assertSame(0.0, $this->subject->getSalaryRangeMin());
        self::assertSame(0.0, $this->subject->getSalaryRangeMax());
        self::assertFalse($this->subject->getHasSalaryRange());
        self::assertFalse($this->subject->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFreeEntryModeIgnoresUnrelatedSalaryGrade(): void
    {
        $salaryGrade = new SalaryGrade();
        $salaryGrade->setHasSteps(false);
        $salaryGrade->setFlatAmount(9999.0);

        $this->subject->setSalaryMode(1);
        $this->subject->setSalaryGrade($salaryGrade);
        $this->subject->setSalaryMin(2500.0);
        $this->subject->setSalaryMax(3200.0);

        self::assertSame(2500.0, $this->subject->getSalaryRangeMin());
        self::assertSame(3200.0, $this->subject->getSalaryRangeMax());
    }
}
