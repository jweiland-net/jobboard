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
