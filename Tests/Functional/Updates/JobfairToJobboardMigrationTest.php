<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\JobfairToJobboardMigration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * The old "tx_jobfair2_domain_model_*" tables belong to no loaded extension,
 * so they are created manually in setUp() via raw SQL instead of a CSV
 * fixture, which requires the target table to already exist in the schema.
 * This does not cover the FAL (sys_file_reference) migration or the MySQL
 * AUTO_INCREMENT fix - both are guarded by their own preconditions
 * (JOB_FAL_FIELDS never populated here, and the AUTO_INCREMENT fix is
 * already a no-op on the sqlite platform this test runs against).
 */
final class JobfairToJobboardMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    private JobfairToJobboardMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_jobboard_domain_model_job');

        // Manually created tables are not part of the schema TYPO3's testing framework
        // manages, so they are not guaranteed to be dropped between test methods on
        // every DBMS (unlike sqlite, PostgreSQL keeps them around) - drop first.
        $connection->executeStatement('DROP TABLE IF EXISTS tx_jobfair2_domain_model_jobarea');
        $connection->executeStatement('DROP TABLE IF EXISTS tx_jobfair2_domain_model_job');

        $connection->executeStatement(
            'CREATE TABLE tx_jobfair2_domain_model_jobarea (uid INTEGER PRIMARY KEY, pid INTEGER, title VARCHAR(255))',
        );
        $connection->insert('tx_jobfair2_domain_model_jobarea', ['uid' => 1, 'pid' => 2, 'title' => 'IT']);

        $connection->executeStatement(
            'CREATE TABLE tx_jobfair2_domain_model_job (uid INTEGER PRIMARY KEY, pid INTEGER, title VARCHAR(255), job_area INTEGER)',
        );
        $connection->insert('tx_jobfair2_domain_model_job', [
            'uid' => 1,
            'pid' => 2,
            'title' => 'Legacy job without salary_mode',
            'job_area' => 1,
        ]);

        $this->subject = GeneralUtility::makeInstance(
            JobfairToJobboardMigration::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenOldJobsAreNotYetMigrated(): void
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
    public function executeUpdateCopiesJobAreaWithIdenticalUid(): void
    {
        $this->subject->executeUpdate();

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_jobboard_domain_model_jobarea');

        self::assertSame(
            'IT',
            $connection->select(['title'], 'tx_jobboard_domain_model_jobarea', ['uid' => 1])->fetchOne(),
        );
    }

    #[Test]
    public function executeUpdateCopiesJobWithIdenticalUidAndDefaultsMissingSalaryModeToFreeEntry(): void
    {
        $this->subject->executeUpdate();

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_jobboard_domain_model_job');
        $row = $connection->select(
            ['title', 'salary_mode', 'salary_min', 'salary_max'],
            'tx_jobboard_domain_model_job',
            ['uid' => 1],
        )->fetchAssociative();

        self::assertSame('Legacy job without salary_mode', $row['title']);
        self::assertSame(1, (int)$row['salary_mode']);
        self::assertSame(0.0, (float)$row['salary_min']);
        self::assertSame(0.0, (float)$row['salary_max']);
    }
}
