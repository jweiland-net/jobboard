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
use PHPUnit\Framework\Attributes\DataProvider;
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
 * The FAL (sys_file_reference) migration is covered for the renamed file
 * columns only. This does not cover the MySQL AUTO_INCREMENT fix, which
 * is already a no-op on the sqlite platform this test runs against.
 */
final class JobfairToJobboardMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function renamedFileColumnDataProvider(): array
    {
        return [
            'header images' => ['header_logo', 'header_logos'],
            'employer logos' => ['employer_logo', 'employer_logos'],
            'tender files' => ['tender_file', 'tender_files'],
        ];
    }

    #[Test]
    #[DataProvider('renamedFileColumnDataProvider')]
    public function executeUpdateMovesFilesToRenamedColumn(string $legacyColumn, string $column): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_jobboard_domain_model_job');
        $connection->executeStatement('ALTER TABLE tx_jobfair2_domain_model_job ADD COLUMN ' . $legacyColumn . ' INTEGER');
        $connection->update('tx_jobfair2_domain_model_job', [$legacyColumn => 1], ['uid' => 1]);
        $this->get(ConnectionPool::class)->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', [
            'uid' => 1,
            'pid' => 2,
            'uid_local' => 1,
            'uid_foreign' => 1,
            'tablenames' => 'tx_jobfair2_domain_model_job',
            'fieldname' => $legacyColumn,
        ]);

        $this->subject->executeUpdate();

        self::assertSame(
            1,
            (int)$connection->select([$column], 'tx_jobboard_domain_model_job', ['uid' => 1])->fetchOne(),
        );
        self::assertSame(
            ['tablenames' => 'tx_jobboard_domain_model_job', 'fieldname' => $column],
            $this->get(ConnectionPool::class)
                ->getConnectionForTable('sys_file_reference')
                ->select(['tablenames', 'fieldname'], 'sys_file_reference', ['uid' => 1])
                ->fetchAssociative(),
        );
    }
}
