<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\ImportResult;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class ImportResultTest extends UnitTestCase
{
    #[Test]
    public function emptyImportResultHasNoErrors(): void
    {
        $importResult = new ImportResult();

        self::assertSame([], $importResult->getJobUids());
        self::assertFalse($importResult->hasErrors());
    }

    #[Test]
    public function withJobUidAddsJobUidWithoutChangingOriginal(): void
    {
        $importResult = new ImportResult([1]);

        self::assertSame([1, 2], $importResult->withJobUid(2)->getJobUids());
        self::assertSame([1], $importResult->getJobUids());
    }

    #[Test]
    public function withFailedJobIncreasesNumberOfFailedJobsAndHasErrors(): void
    {
        $importResult = (new ImportResult())->withFailedJob()->withFailedJob();

        self::assertSame(2, $importResult->getNumberOfFailedJobs());
        self::assertTrue($importResult->hasErrors());
    }

    #[Test]
    public function failedApiModelHasErrors(): void
    {
        self::assertTrue((new ImportResult([], 0, 1))->hasErrors());
    }

    #[Test]
    public function mergeCombinesJobUidsAndNumbersOfFailures(): void
    {
        $importResult = (new ImportResult([1], 1, 0))->merge(new ImportResult([2, 3], 2, 1));

        self::assertSame([1, 2, 3], $importResult->getJobUids());
        self::assertSame(3, $importResult->getNumberOfFailedJobs());
        self::assertSame(1, $importResult->getNumberOfFailedApiModels());
    }

    #[Test]
    public function withSkippedJobIncreasesNumberOfSkippedJobsWithoutErrors(): void
    {
        $importResult = (new ImportResult())->withSkippedJob()->withJobUid(1)->withFailedJob()->withSkippedJob();

        self::assertSame(2, $importResult->getNumberOfSkippedJobs());
        self::assertSame(1, $importResult->getNumberOfFailedJobs());
        self::assertSame([1], $importResult->getJobUids());
        self::assertFalse((new ImportResult([], 0, 0, 5))->hasErrors());
    }

    #[Test]
    public function mergeAddsNumbersOfSkippedJobs(): void
    {
        $importResult = (new ImportResult([], 0, 0, 1))->merge(new ImportResult([], 0, 0, 2));

        self::assertSame(3, $importResult->getNumberOfSkippedJobs());
    }
}
