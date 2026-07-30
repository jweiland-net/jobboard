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
use JWeiland\Jobfair2\Domain\Model\SalaryTable;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class SalaryTableTest extends UnitTestCase
{
    protected SalaryTable $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new SalaryTable();
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
        $this->subject->setTitle('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getTitle(),
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
    public function getSalaryGradesInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getSalaryGrades(),
        );
    }

    #[Test]
    public function setSalaryGradesSetsSalaryGrades(): void
    {
        $object = new SalaryGrade();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setSalaryGrades($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getSalaryGrades(),
        );
    }
}
