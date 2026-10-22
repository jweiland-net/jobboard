<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class JobAreaRestrictionTest extends UnitTestCase
{
    public static function unrestrictedSettingsDataProvider(): array
    {
        return [
            'missing' => [[]],
            'empty string' => [['jobAreas' => '']],
            'zero' => [['jobAreas' => '0']],
            'empty array' => [['jobAreas' => []]],
            'invalid type' => [['jobAreas' => new \stdClass()]],
        ];
    }

    #[Test]
    #[DataProvider('unrestrictedSettingsDataProvider')]
    public function fromSettingsWithoutJobAreasIsNotRestricted(array $settings): void
    {
        $subject = JobAreaRestriction::fromSettings($settings);

        self::assertFalse($subject->isRestricted());
        self::assertSame([], $subject->getJobAreaUids());
        self::assertTrue($subject->allows(42));
    }

    #[Test]
    public function fromSettingsWithCommaSeparatedListFromFlexFormIsRestricted(): void
    {
        $subject = JobAreaRestriction::fromSettings(['jobAreas' => '3, 1,3']);

        self::assertTrue($subject->isRestricted());
        self::assertSame([3, 1], $subject->getJobAreaUids());
    }

    #[Test]
    public function fromSettingsWithArrayFromTypoScriptIsRestricted(): void
    {
        $subject = JobAreaRestriction::fromSettings(['jobAreas' => ['1', 2, '-4']]);

        self::assertSame([1, 2], $subject->getJobAreaUids());
    }

    #[Test]
    public function allowsReturnsTrueForConfiguredJobArea(): void
    {
        self::assertTrue(JobAreaRestriction::fromSettings(['jobAreas' => '1,2'])->allows(2));
    }

    #[Test]
    public function allowsReturnsFalseForNotConfiguredJobArea(): void
    {
        self::assertFalse(JobAreaRestriction::fromSettings(['jobAreas' => '1,2'])->allows(3));
    }
}
