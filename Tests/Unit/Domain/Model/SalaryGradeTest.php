<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Tests\Unit\Domain\Model;

use JWeiland\Jobfair2\Domain\Model\SalaryGrade;
use JWeiland\Jobfair2\Domain\Model\SalaryStep;
use JWeiland\Jobfair2\Domain\Model\SalaryTable;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class SalaryGradeTest extends UnitTestCase
{
    protected SalaryGrade $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SalaryGrade();
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
        $this->subject->setTitle('A7');

        self::assertSame(
            'A7',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function hasStepsInitiallyReturnsTrue(): void
    {
        self::assertTrue(
            $this->subject->hasSteps(),
        );
    }

    #[Test]
    public function setHasStepsSetsHasSteps(): void
    {
        $this->subject->setHasSteps(false);

        self::assertFalse(
            $this->subject->hasSteps(),
        );
    }

    #[Test]
    public function getFlatAmountInitiallyReturnsZero(): void
    {
        self::assertSame(
            0.0,
            $this->subject->getFlatAmount(),
        );
    }

    #[Test]
    public function setFlatAmountSetsFlatAmount(): void
    {
        $this->subject->setFlatAmount(3500.0);

        self::assertSame(
            3500.0,
            $this->subject->getFlatAmount(),
        );
    }

    #[Test]
    public function getSalaryTableInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getSalaryTable());
    }

    #[Test]
    public function setSalaryTableSetsSalaryTable(): void
    {
        $instance = new SalaryTable();
        $this->subject->setSalaryTable($instance);

        self::assertSame(
            $instance,
            $this->subject->getSalaryTable(),
        );
    }

    #[Test]
    public function getSalaryStepsInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getSalarySteps(),
        );
    }

    #[Test]
    public function setSalaryStepsSetsSalarySteps(): void
    {
        $object = new SalaryStep();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setSalarySteps($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getSalarySteps(),
        );
    }

    #[Test]
    public function getMinAmountWithoutStepsReturnsFlatAmount(): void
    {
        $this->subject->setHasSteps(false);
        $this->subject->setFlatAmount(3500.0);

        self::assertSame(
            3500.0,
            $this->subject->getMinAmount(),
        );
    }

    #[Test]
    public function getMaxAmountWithoutStepsReturnsFlatAmount(): void
    {
        $this->subject->setHasSteps(false);
        $this->subject->setFlatAmount(3500.0);

        self::assertSame(
            3500.0,
            $this->subject->getMaxAmount(),
        );
    }

    #[Test]
    public function getMinAmountWithStepsReturnsLowestStepAmount(): void
    {
        $this->subject->setHasSteps(true);

        $stepOne = new SalaryStep();
        $stepOne->setAmount(3314.32);
        $this->subject->getSalarySteps()->attach($stepOne);

        $stepTwo = new SalaryStep();
        $stepTwo->setAmount(3220.85);
        $this->subject->getSalarySteps()->attach($stepTwo);

        self::assertSame(
            3220.85,
            $this->subject->getMinAmount(),
        );
    }

    #[Test]
    public function getMaxAmountWithStepsReturnsHighestStepAmount(): void
    {
        $this->subject->setHasSteps(true);

        $stepOne = new SalaryStep();
        $stepOne->setAmount(3220.85);
        $this->subject->getSalarySteps()->attach($stepOne);

        $stepTwo = new SalaryStep();
        $stepTwo->setAmount(3407.74);
        $this->subject->getSalarySteps()->attach($stepTwo);

        self::assertSame(
            3407.74,
            $this->subject->getMaxAmount(),
        );
    }

    #[Test]
    public function getMinAmountWithStepsButNoExistingStepsReturnsZero(): void
    {
        $this->subject->setHasSteps(true);

        self::assertSame(
            0.0,
            $this->subject->getMinAmount(),
        );
    }

    #[Test]
    public function getMaxAmountWithStepsButNoExistingStepsReturnsZero(): void
    {
        $this->subject->setHasSteps(true);

        self::assertSame(
            0.0,
            $this->subject->getMaxAmount(),
        );
    }
}
