<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\SalaryGradeToSalaryGradesMigration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class SalaryGradeToSalaryGradesMigrationTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private SalaryGradeToSalaryGradesMigration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // The legacy column is neither part of TCA nor of ext_tables.sql anymore,
        // so it has to be created manually, as in a not yet updated database.
        $this->addLegacyColumn();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SalaryGradeToSalaryGradesMigration.csv');

        $this->subject = GeneralUtility::makeInstance(
            SalaryGradeToSalaryGradesMigration::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenLegacySalaryGradesExist(): void
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
    public function executeUpdateMovesPlainAndTablePrefixedUidsIntoMmTable(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([1], $this->getSalaryGradeUidsOfJob(1));
        self::assertSame([2], $this->getSalaryGradeUidsOfJob(2));
        self::assertSame([2], $this->getSalaryGradeUidsOfJob(6));
        self::assertSame(1, $this->getJobColumn(1, 'salary_grades'));
        self::assertNull($this->getJobColumn(1, 'salary_grade'));
    }

    #[Test]
    public function executeUpdateKeepsExistingMmRelationsAndEmptiesLegacyColumn(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([1], $this->getSalaryGradeUidsOfJob(4));
        self::assertSame(1, $this->getJobColumn(4, 'salary_grades'));
        self::assertNull($this->getJobColumn(4, 'salary_grade'));
    }

    #[Test]
    public function executeUpdateIgnoresJobsWithoutUsableLegacyValue(): void
    {
        $this->subject->executeUpdate();

        self::assertSame([], $this->getSalaryGradeUidsOfJob(3));
        self::assertSame([], $this->getSalaryGradeUidsOfJob(5));
        self::assertSame(0, $this->getJobColumn(5, 'salary_grades'));
        self::assertNull($this->getJobColumn(5, 'salary_grade'));
    }

    /**
     * @return int[]
     */
    private function getSalaryGradeUidsOfJob(int $jobUid): array
    {
        $rows = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_job_salarygrade_mm')
            ->select(['uid_foreign'], 'tx_jobboard_job_salarygrade_mm', ['uid_local' => $jobUid], [], ['sorting' => 'ASC'])
            ->fetchFirstColumn();

        return array_map(intval(...), $rows);
    }

    private function getJobColumn(int $jobUid, string $column): mixed
    {
        $value = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->select([$column], 'tx_jobboard_domain_model_job', ['uid' => $jobUid])
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
            ->hasColumn('salary_grade');
    }

    /**
     * Non-sqlite DBMS keep the schema between test methods, so the column
     * may already exist.
     */
    private function addLegacyColumn(): void
    {
        if (!$this->hasLegacyColumn()) {
            $this->getJobConnection()->executeStatement(
                'ALTER TABLE tx_jobboard_domain_model_job ADD COLUMN salary_grade TEXT',
            );
        }
    }

    private function dropLegacyColumn(): void
    {
        if ($this->hasLegacyColumn()) {
            $this->getJobConnection()->executeStatement(
                'ALTER TABLE tx_jobboard_domain_model_job DROP COLUMN salary_grade',
            );
        }
    }
}
