<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Tests\Functional\Domain\Repository;

use JWeiland\Jobfair2\Domain\Model\Job;
use JWeiland\Jobfair2\Domain\Model\SalaryGrade;
use JWeiland\Jobfair2\Domain\Repository\JobRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Verifies that Job::getSalaryRangeMin()/getSalaryRangeMax() (and the two
 * boolean helpers built on top of them) work against records loaded through
 * Extbase persistence - not just against manually assembled objects like in
 * the unit test. This is the part a unit test cannot prove: that the
 * "salary_grade" group field is actually mapped back to a SalaryGrade object
 * by the DataMapper.
 */
class JobRepositoryTest extends FunctionalTestCase
{
    protected JobRepository $subject;

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobfair2',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/JobWithSalaryInformation.csv');

        $this->subject = GeneralUtility::makeInstance(JobRepository::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    private function findJobByUid(int $uid): Job
    {
        $job = $this->subject->findByUid($uid);

        self::assertInstanceOf(Job::class, $job);

        return $job;
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithSteppedSalaryGradeUseLowestAndHighestStepAmount(): void
    {
        $job = $this->findJobByUid(1);

        self::assertInstanceOf(
            SalaryGrade::class,
            $job->getSalaryGrade(),
        );
        self::assertSame('A7', $job->getSalaryGrade()->getTitle());

        self::assertSame(3220.85, $job->getSalaryRangeMin());
        self::assertSame(3407.74, $job->getSalaryRangeMax());
        self::assertTrue($job->getHasSalaryRange());
        self::assertTrue($job->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFlatSalaryGradeReturnSameAmountForMinAndMax(): void
    {
        $job = $this->findJobByUid(2);

        self::assertSame(3500.0, $job->getSalaryRangeMin());
        self::assertSame(3500.0, $job->getSalaryRangeMax());
        self::assertFalse($job->getHasSalaryRange());
        self::assertTrue($job->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFreeEntryRangeUseMinAndMax(): void
    {
        $job = $this->findJobByUid(3);

        self::assertNull($job->getSalaryGrade());
        self::assertSame(2500.0, $job->getSalaryRangeMin());
        self::assertSame(3200.0, $job->getSalaryRangeMax());
        self::assertTrue($job->getHasSalaryRange());
        self::assertTrue($job->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithFreeEntrySingleAmountFallsBackMaxToMin(): void
    {
        $job = $this->findJobByUid(4);

        self::assertSame(2500.0, $job->getSalaryRangeMin());
        self::assertSame(2500.0, $job->getSalaryRangeMax());
        self::assertFalse($job->getHasSalaryRange());
        self::assertTrue($job->getHasSalaryInformation());
    }

    #[Test]
    public function getSalaryRangeMinAndMaxWithoutAnySalaryInformationReturnZero(): void
    {
        $job = $this->findJobByUid(5);

        self::assertSame(0.0, $job->getSalaryRangeMin());
        self::assertSame(0.0, $job->getSalaryRangeMax());
        self::assertFalse($job->getHasSalaryRange());
        self::assertFalse($job->getHasSalaryInformation());
    }
}
