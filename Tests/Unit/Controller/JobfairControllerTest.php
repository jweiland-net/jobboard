<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Tests\Unit\Controller;

use JWeiland\Jobfair2\Controller\JobfairController;
use JWeiland\Jobfair2\Domain\Model\Job;
use JWeiland\Jobfair2\Domain\Repository\JobAreaRepository;
use JWeiland\Jobfair2\Domain\Repository\JobRepository;
use JWeiland\Jobfair2\Domain\Repository\JobTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * detailAction() must reject a job without resolvable salary information
 * before it ever reaches the view - a job without salary must not be
 * displayed, whether the URL is guessed directly or reached through a link
 * that predates the salary grade/steps expiring.
 */
class JobfairControllerTest extends UnitTestCase
{
    protected JobfairController $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new JobfairController(
            self::createStub(JobRepository::class),
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
     * @param iterable<Job> $jobs
     * @return Job[]
     */
    private function excludeJobsWithoutSalaryInformation(iterable $jobs, int $limit = 0): array
    {
        $method = new \ReflectionMethod($this->subject, 'excludeJobsWithoutSalaryInformation');
        $method->setAccessible(true);

        return $method->invoke($this->subject, $jobs, $limit);
    }

    private function buildJobWithSalaryInformation(string $title): Job
    {
        $job = new Job();
        $job->setTitle($title);
        $job->setSalaryMode(1);
        $job->setSalaryMin(2500.0);

        return $job;
    }

    private function buildJobWithoutSalaryInformation(string $title): Job
    {
        $job = new Job();
        $job->setTitle($title);
        $job->setSalaryMode(1);

        return $job;
    }

    #[Test]
    public function excludeJobsWithoutSalaryInformationRemovesJobsWithoutSalary(): void
    {
        $jobWithSalary = $this->buildJobWithSalaryInformation('With salary');
        $jobWithoutSalary = $this->buildJobWithoutSalaryInformation('Without salary');

        $filtered = $this->excludeJobsWithoutSalaryInformation([$jobWithSalary, $jobWithoutSalary]);

        self::assertSame(
            [$jobWithSalary],
            $filtered,
        );
    }

    #[Test]
    public function excludeJobsWithoutSalaryInformationKeepsOrderOfEligibleJobs(): void
    {
        $firstJob = $this->buildJobWithSalaryInformation('First');
        $secondJob = $this->buildJobWithSalaryInformation('Second');

        $filtered = $this->excludeJobsWithoutSalaryInformation([$firstJob, $secondJob]);

        self::assertSame(
            [$firstJob, $secondJob],
            $filtered,
        );
    }

    #[Test]
    public function excludeJobsWithoutSalaryInformationWithoutLimitReturnsAllEligibleJobs(): void
    {
        $jobs = [
            $this->buildJobWithSalaryInformation('First'),
            $this->buildJobWithSalaryInformation('Second'),
            $this->buildJobWithSalaryInformation('Third'),
        ];

        self::assertCount(
            3,
            $this->excludeJobsWithoutSalaryInformation($jobs),
        );
    }

    #[Test]
    public function excludeJobsWithoutSalaryInformationAppliesLimitAfterFiltering(): void
    {
        // Only 2 of these 3 have salary information at all - a limit of 2 must still
        // return exactly 2 eligible jobs, not fewer just because one was ineligible.
        $jobs = [
            $this->buildJobWithoutSalaryInformation('Ineligible'),
            $this->buildJobWithSalaryInformation('First eligible'),
            $this->buildJobWithSalaryInformation('Second eligible'),
        ];

        $filtered = $this->excludeJobsWithoutSalaryInformation($jobs, 2);

        self::assertCount(
            2,
            $filtered,
        );
        self::assertSame(
            'First eligible',
            $filtered[0]->getTitle(),
        );
        self::assertSame(
            'Second eligible',
            $filtered[1]->getTitle(),
        );
    }

    #[Test]
    public function excludeJobsWithoutSalaryInformationStopsIteratingOnceLimitIsReached(): void
    {
        $jobs = (function (): \Generator {
            yield $this->buildJobWithSalaryInformation('First');
            yield $this->buildJobWithSalaryInformation('Second');

            throw new \RuntimeException('Must not be reached - the limit was already satisfied.');
        })();

        $filtered = $this->excludeJobsWithoutSalaryInformation($jobs, 2);

        self::assertCount(
            2,
            $filtered,
        );
    }

    #[Test]
    public function detailActionThrowsPageNotFoundExceptionForJobWithoutSalaryInformation(): void
    {
        $job = new Job();
        $job->setSalaryMode(1);

        $this->expectException(PageNotFoundException::class);

        $this->subject->detailAction($job);
    }

    #[Test]
    public function detailActionDoesNotThrowPageNotFoundExceptionForJobWithSalaryInformation(): void
    {
        $job = new Job();
        $job->setSalaryMode(1);
        $job->setSalaryMin(2500.0);

        try {
            $this->subject->detailAction($job);
        } catch (PageNotFoundException) {
            self::fail('detailAction() must not reject a job with resolvable salary information.');
        } catch (\Throwable) {
            // Any other exception originates from the view not being initialized in
            // this isolated unit test context, not from the code under test.
        }
    }
}
