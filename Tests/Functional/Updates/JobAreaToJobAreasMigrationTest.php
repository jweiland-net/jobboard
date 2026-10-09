<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\JobAreaToJobAreasMigration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobAreaToJobAreasMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    private JobAreaToJobAreasMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // The legacy column is neither part of TCA nor of ext_tables.sql anymore,
        // so it has to be created manually, as in a not yet updated database.
        $this->addLegacyColumn();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobAreaToJobAreasMigration.csv');

        $this->subject = GeneralUtility::makeInstance(
            JobAreaToJobAreasMigration::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenLegacyJobAreasExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWithoutLegacyColumn(): void
    {
        $this->dropLegacyColumn();

        self::assertFalse($this->subject->updateNecessary());
        self::assertTrue($this->subject->executeUpdate());
    }

    #[Test]
    public function updateNecessaryReturnsFalseOnceMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMovesLegacyJobAreaIntoMmTable(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([1], $this->getJobAreaUidsOfJob(1));
        self::assertSame(1, $this->getJobColumn(1, 'job_areas'));
        self::assertSame(0, $this->getJobColumn(1, 'job_area'));
    }

    #[Test]
    public function executeUpdateAlsoMigratesDeletedJobs(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([2], $this->getJobAreaUidsOfJob(4));
        self::assertSame(0, $this->getJobColumn(4, 'job_area'));
    }

    #[Test]
    public function executeUpdateKeepsExistingMmRelationsAndResetsLegacyColumn(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([1, 2], $this->getJobAreaUidsOfJob(3));
        self::assertSame(2, $this->getJobColumn(3, 'job_areas'));
        self::assertSame(0, $this->getJobColumn(3, 'job_area'));
    }

    #[Test]
    public function executeUpdateIgnoresJobsWithoutLegacyJobArea(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([], $this->getJobAreaUidsOfJob(2));
        self::assertSame(0, $this->getJobColumn(2, 'job_areas'));
    }

    /**
     * @return int[]
     */
    private function getJobAreaUidsOfJob(int $jobUid): array
    {
        $rows = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_job_jobarea_mm')
            ->select(['uid_foreign'], 'tx_jobboard_job_jobarea_mm', ['uid_local' => $jobUid], [], ['sorting' => 'ASC'])
            ->fetchFirstColumn();

        return array_map(intval(...), $rows);
    }

    private function getJobColumn(int $jobUid, string $column): mixed
    {
        // Without restrictions, as deleted jobs are migrated, too
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();
        $value = $queryBuilder
            ->select($column)
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        return is_numeric($value) ? (int)$value : $value;
    }

    private function getJobConnection(): Connection
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('tx_jobboard_domain_model_job');
    }

    private function hasLegacyColumn(): bool
    {
        return $this->getJobConnection()
            ->createSchemaManager()
            ->introspectTable('tx_jobboard_domain_model_job')
            ->hasColumn('job_area');
    }

    /**
     * Non-sqlite DBMS keep the schema between test methods, so the column
     * may already exist.
     */
    private function addLegacyColumn(): void
    {
        if (!$this->hasLegacyColumn()) {
            $this->getJobConnection()->executeStatement(
                'ALTER TABLE tx_jobboard_domain_model_job ADD COLUMN job_area INTEGER DEFAULT 0 NOT NULL',
            );
        }
    }

    private function dropLegacyColumn(): void
    {
        if ($this->hasLegacyColumn()) {
            $this->getJobConnection()->executeStatement(
                'ALTER TABLE tx_jobboard_domain_model_job DROP COLUMN job_area',
            );
        }
    }
}
