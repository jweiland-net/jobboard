<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Database\Query;

use JWeiland\Jobboard\Database\Query\JobLanguageConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class JobLanguageConstraintTest extends UnitTestCase
{
    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function rowDataProvider(): array
    {
        return [
            'record of default language' => [['uid' => 3, 'l10n_parent' => 0], 3],
            'translation' => [['uid' => 13, 'l10n_parent' => 3], 3],
            'translation with parent as string' => [['uid' => '13', 'l10n_parent' => '3'], 3],
            'floating translation' => [['uid' => 15, 'l10n_parent' => 0], 15],
            'record of language "all"' => [['uid' => 6, 'l10n_parent' => 0], 6],
            'without column l10n_parent' => [['uid' => 7], 7],
            'negative l10n_parent' => [['uid' => 7, 'l10n_parent' => -1], 7],
            'without columns' => [[], 0],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[Test]
    #[DataProvider('rowDataProvider')]
    public function getDefaultLanguageUidReturnsUidOfDefaultRecord(array $row, int $expectedUid): void
    {
        self::assertSame($expectedUid, JobLanguageConstraint::getDefaultLanguageUid($row));
    }
}
