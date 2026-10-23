<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RememberedJobsTest extends UnitTestCase
{
    #[Test]
    public function createEmptyCreatesEmptyList(): void
    {
        $subject = RememberedJobs::createEmpty();

        self::assertSame([], $subject->getUids());
        self::assertTrue($subject->isEmpty());
        self::assertFalse($subject->isFull());
    }

    public static function sessionDataDataProvider(): array
    {
        return [
            'no session data' => [null, []],
            'string' => ['1,2', []],
            'list of UIDs' => [[3, 1, 2], [3, 1, 2]],
            'duplicates' => [[3, 1, 3], [3, 1]],
            'invalid values' => [[1, '2', 0, -3, 4.0, [5], true, 6], [1, 6]],
            'keys are ignored' => [['a' => 7, 9 => 8], [7, 8]],
        ];
    }

    #[Test]
    #[DataProvider('sessionDataDataProvider')]
    public function fromSessionDataKeepsPositiveIntegersOnly(mixed $sessionData, array $expectedUids): void
    {
        self::assertSame($expectedUids, RememberedJobs::fromSessionData($sessionData)->getUids());
    }

    #[Test]
    public function fromSessionDataCutsListToMaximum(): void
    {
        $subject = RememberedJobs::fromSessionData(range(1, RememberedJobs::MAXIMUM + 5));

        self::assertSame(range(1, RememberedJobs::MAXIMUM), $subject->getUids());
        self::assertTrue($subject->isFull());
    }

    #[Test]
    public function getUidsAsKeysReturnsLookupForFluid(): void
    {
        self::assertSame([3 => true, 1 => true], RememberedJobs::fromSessionData([3, 1])->getUidsAsKeys());
    }

    #[Test]
    public function containsReturnsWhetherJobIsRemembered(): void
    {
        $subject = RememberedJobs::fromSessionData([3, 1]);

        self::assertTrue($subject->contains(1));
        self::assertFalse($subject->contains(2));
    }

    #[Test]
    public function withAppendsJobAndKeepsOriginalUnchanged(): void
    {
        $subject = RememberedJobs::fromSessionData([3]);

        self::assertSame([3, 1], $subject->with(1)->getUids());
        self::assertSame([3], $subject->getUids());
    }

    #[Test]
    public function withDoesNotAddJobTwice(): void
    {
        self::assertSame([3, 1], RememberedJobs::fromSessionData([3, 1])->with(3)->getUids());
    }

    #[Test]
    public function withThrowsExceptionIfMaximumIsReached(): void
    {
        $this->expectException(\OverflowException::class);
        $this->expectExceptionCode(1761210001);

        RememberedJobs::fromSessionData(range(1, RememberedJobs::MAXIMUM))->with(RememberedJobs::MAXIMUM + 1);
    }

    #[Test]
    public function withAcceptsAlreadyRememberedJobIfMaximumIsReached(): void
    {
        $subject = RememberedJobs::fromSessionData(range(1, RememberedJobs::MAXIMUM));

        self::assertTrue($subject->with(1)->equals($subject));
    }

    #[Test]
    public function withoutRemovesJob(): void
    {
        self::assertSame([3, 2], RememberedJobs::fromSessionData([3, 1, 2])->without(1)->getUids());
    }

    #[Test]
    public function withoutIgnoresJobWhichIsNotRemembered(): void
    {
        self::assertSame([3, 1], RememberedJobs::fromSessionData([3, 1])->without(2)->getUids());
    }

    #[Test]
    public function intersectKeepsGivenJobsInRememberedOrder(): void
    {
        self::assertSame([3, 2], RememberedJobs::fromSessionData([3, 1, 2])->intersect([2, 3, 4])->getUids());
    }

    #[Test]
    public function equalsComparesUidsAndOrder(): void
    {
        $subject = RememberedJobs::fromSessionData([3, 1]);

        self::assertTrue($subject->equals(RememberedJobs::fromSessionData([3, 1])));
        self::assertFalse($subject->equals(RememberedJobs::fromSessionData([1, 3])));
    }
}
