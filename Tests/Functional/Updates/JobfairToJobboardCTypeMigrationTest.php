<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\JobfairToJobboardCTypeMigration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobfairToJobboardCTypeMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private JobfairToJobboardCTypeMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobfairToJobboardCTypeMigration.csv');

        $this->subject = GeneralUtility::makeInstance(
            JobfairToJobboardCTypeMigration::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenOldCTypeOrPermissionsExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseOnceMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesContentElementCTypeAndLeavesUnrelatedElementsUntouched(): void
    {
        $this->subject->executeUpdate();

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');

        self::assertSame(
            'jobboard_jobboard',
            $connection->select(['CType'], 'tt_content', ['uid' => 1])->fetchOne(),
        );
        self::assertSame(
            'text',
            $connection->select(['CType'], 'tt_content', ['uid' => 2])->fetchOne(),
        );
    }

    #[Test]
    public function executeUpdateReplacesCTypeInExplicitAllowdenyAndLeavesOtherEntriesUntouched(): void
    {
        $this->subject->executeUpdate();

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');

        self::assertSame(
            'tt_content:CType:text,tt_content:CType:jobboard_jobboard,pages:doktype:1',
            $connection->select(['explicit_allowdeny'], 'be_groups', ['uid' => 1])->fetchOne(),
        );
        self::assertSame(
            'pages:doktype:1',
            $connection->select(['explicit_allowdeny'], 'be_groups', ['uid' => 2])->fetchOne(),
        );
    }
}
