<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Service;

use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use JWeiland\Jobboard\Service\RememberedJobsService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RememberedJobsServiceTest extends UnitTestCase
{
    protected RememberedJobsService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new RememberedJobsService();
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    private function buildRequest(FrontendUserAuthentication $frontendUser): ServerRequest
    {
        return (new ServerRequest())->withAttribute('frontend.user', $frontendUser);
    }

    #[Test]
    public function getRememberedJobsWithoutFrontendUserReturnsEmptyList(): void
    {
        self::assertTrue($this->subject->getRememberedJobs(new ServerRequest())->isEmpty());
    }

    #[Test]
    public function getRememberedJobsReadsSessionData(): void
    {
        $frontendUser = $this->createMock(FrontendUserAuthentication::class);
        $frontendUser
            ->expects($this->once())
            ->method('getKey')
            ->with('ses', RememberedJobsService::SESSION_KEY)
            ->willReturn([3, 'invalid', 1]);

        self::assertSame([3, 1], $this->subject->getRememberedJobs($this->buildRequest($frontendUser))->getUids());
    }

    #[Test]
    public function storeRememberedJobsWritesListOfUids(): void
    {
        $frontendUser = $this->createMock(FrontendUserAuthentication::class);
        $frontendUser
            ->expects($this->once())
            ->method('setAndSaveSessionData')
            ->with(RememberedJobsService::SESSION_KEY, [3, 1]);

        $this->subject->storeRememberedJobs(
            $this->buildRequest($frontendUser),
            RememberedJobs::fromSessionData([3, 1]),
        );
    }

    #[Test]
    public function storeRememberedJobsRemovesKeyForEmptyList(): void
    {
        $frontendUser = $this->createMock(FrontendUserAuthentication::class);
        $frontendUser
            ->expects($this->once())
            ->method('setAndSaveSessionData')
            ->with(RememberedJobsService::SESSION_KEY, null);

        $this->subject->storeRememberedJobs($this->buildRequest($frontendUser), RememberedJobs::createEmpty());
    }

    #[Test]
    public function storeRememberedJobsWithoutFrontendUserDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        $this->subject->storeRememberedJobs(new ServerRequest(), RememberedJobs::fromSessionData([1]));
    }
}
