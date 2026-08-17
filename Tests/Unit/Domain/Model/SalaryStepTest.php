<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\SalaryGrade;
use JWeiland\Jobboard\Domain\Model\SalaryStep;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class SalaryStepTest extends UnitTestCase
{
    protected SalaryStep $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SalaryStep();
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    #[Test]
    public function getStepLabelInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getStepLabel(),
        );
    }

    #[Test]
    public function setStepLabelSetsStepLabel(): void
    {
        $this->subject->setStepLabel('3');

        self::assertSame(
            '3',
            $this->subject->getStepLabel(),
        );
    }

    #[Test]
    public function getAmountInitiallyReturnsZero(): void
    {
        self::assertSame(
            0.0,
            $this->subject->getAmount(),
        );
    }

    #[Test]
    public function setAmountSetsAmount(): void
    {
        $this->subject->setAmount(3407.74);

        self::assertSame(
            3407.74,
            $this->subject->getAmount(),
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
}
