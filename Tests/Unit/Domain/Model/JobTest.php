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
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\SalaryGrade;
use JWeiland\Jobboard\Domain\Model\SalaryStep;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
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
    public function isImportInitiallyReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->isImport(),
        );
    }

    #[Test]
    public function setIsImportSetsIsImport(): void
    {
        $this->subject->setIsImport(true);

        self::assertTrue(
            $this->subject->isImport(),
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
    public function getTenderFileInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getTenderFile());
    }

    #[Test]
    public function setTenderFileSetsTenderFile(): void
    {
        $instance = new FileReference();
        $this->subject->setTenderFile($instance);

        self::assertSame(
            $instance,
            $this->subject->getTenderFile(),
        );
    }

    #[Test]
    public function getPdfFilesInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getPdfFiles());
    }

    #[Test]
    public function setPdfFilesSetsPdfFiles(): void
    {
        $instance = new FileReference();
        $this->subject->setPdfFiles($instance);

        self::assertSame(
            $instance,
            $this->subject->getPdfFiles(),
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
    public function isInternalInitiallyReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->isInternal(),
        );
    }

    #[Test]
    public function setIsInternalSetsIsInternal(): void
    {
        $this->subject->setIsInternal(true);

        self::assertTrue(
            $this->subject->isInternal(),
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
