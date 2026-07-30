<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Tests\Functional\Controller;

use JWeiland\Jobfair2\Controller\JobfairController;
use JWeiland\Jobfair2\Domain\Model\Job;
use JWeiland\Jobfair2\Domain\Repository\JobAreaRepository;
use JWeiland\Jobfair2\Domain\Repository\JobRepository;
use JWeiland\Jobfair2\Domain\Repository\JobTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * A job without resolvable salary information must never reach the frontend -
 * this is a legal requirement, not a cosmetic one. Covers every way the salary
 * information behind a job can become unresolvable through TYPO3's own access
 * restrictions (hidden/starttime/endtime on the salary grade or its steps),
 * not just the "editor never filled it in" case - using real records loaded
 * through JobRepository, not manually assembled Job objects, so the fixture
 * also proves the grade/step relations actually stop resolving once expired.
 */
class JobfairControllerTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected JobfairController $subject;

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobfair2',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobVisibilityBySalaryInformation.csv');

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);

        $jobRepository = $this->get(JobRepository::class);
        $jobRepository->setDefaultQuerySettings($querySettings);

        $this->subject = new JobfairController(
            $jobRepository,
            self::createStub(JobAreaRepository::class),
            self::createStub(JobTypeRepository::class),
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    /**
     * @return string[]
     */
    private function findEligibleJobTitles(int $limit = 0): array
    {
        $jobRepository = $this->get(JobRepository::class);

        $method = new \ReflectionMethod($this->subject, 'excludeJobsWithoutSalaryInformation');
        $method->setAccessible(true);

        /** @var Job[] $eligibleJobs */
        $eligibleJobs = $method->invoke($this->subject, $jobRepository->findBySearchCriteria([]), $limit);

        return array_map(
            static fn(Job $job): string => $job->getTitle(),
            $eligibleJobs,
        );
    }

    #[Test]
    public function excludesJobWithExpiredSalaryGrade(): void
    {
        self::assertNotContains(
            'Job with expired grade',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function excludesJobWithHiddenSalaryGrade(): void
    {
        self::assertNotContains(
            'Job with hidden grade',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function excludesJobWithNotYetStartedSalaryGrade(): void
    {
        self::assertNotContains(
            'Job with not yet started grade',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function excludesJobWhoseSalaryGradeStepsAreAllExpired(): void
    {
        self::assertNotContains(
            'Job with all steps expired',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function excludesJobWithoutAnySalaryGradeSelected(): void
    {
        self::assertNotContains(
            'Job without any salary grade selected',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function excludesFreeEntryJobWithoutAnyAmount(): void
    {
        self::assertNotContains(
            'Job with free entry but no amount at all',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function includesJobWithVisibleSteppedSalaryGrade(): void
    {
        self::assertContains(
            'Job with visible stepped grade',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function includesFreeEntryJobWithARange(): void
    {
        self::assertContains(
            'Job with free entry range',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function includesFreeEntryJobWithASingleAmount(): void
    {
        self::assertContains(
            'Job with a single free entry amount',
            $this->findEligibleJobTitles(),
        );
    }

    #[Test]
    public function appliesLimitAfterFilteringOutJobsWithoutSalaryInformation(): void
    {
        // Only 3 jobs are eligible in total (see the fixture): "Job with visible
        // stepped grade", "Job with free entry range" and "Job with a single free
        // entry amount". A limit of 2 must return exactly 2 of those - never fewer
        // just because ineligible jobs were skipped internally.
        self::assertCount(
            2,
            $this->findEligibleJobTitles(2),
        );
    }
}
