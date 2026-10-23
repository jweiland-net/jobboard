<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\JobLink;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class JobLinkTest extends UnitTestCase
{
    #[Test]
    public function gettersReturnValuesOfConstructor(): void
    {
        $jobLink = new JobLink(12, 'Example job', '/jobs/example-job', false);

        self::assertSame(12, $jobLink->getUid());
        self::assertSame('Example job', $jobLink->getTitle());
        self::assertSame('/jobs/example-job', $jobLink->getUri());
        self::assertFalse($jobLink->getIsExternal());
    }

    #[Test]
    public function getIsExternalReturnsTrueForExternalLink(): void
    {
        self::assertTrue((new JobLink(12, 'Example job', 'https://example.org/', true))->getIsExternal());
    }
}
