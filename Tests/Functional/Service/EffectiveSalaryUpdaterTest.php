<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\EffectiveSalaryUpdaterInterface;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * "now" is fixed to 2026-01-01 00:00:00 UTC by the date aspect of the Context.
 */
final class EffectiveSalaryUpdaterTest extends FunctionalTestCase
{
    private const NOW = 1767225600;

    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private EffectiveSalaryUpdaterInterface $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectiveSalaryUpdater.csv');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . self::NOW)));

        $this->subject = $this->get(EffectiveSalaryUpdaterInterface::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    /**
     * @return array{float, float, int}
     */
    private function fetchEffectiveSalary(int $jobUid): array
    {
        $row = $this->fetchJob($jobUid);

        return [
            (float)$row['effective_salary_min'],
            (float)$row['effective_salary_max'],
            (int)$row['effective_salary_valid_until'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJob(int $jobUid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_JOB);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->eq('uid', $jobUid))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row);

        return $row;
    }

    private function updateRecord(string $table, int $uid, array $values): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable($table)->update($table, $values, ['uid' => $uid]);
    }

    #[Test]
    public function updateJobsWritesCalculatedValuesAndReturnsNumberOfChangedJobs(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(1, $this->subject->updateJobs([1, 4]));
        self::assertSame([2500.00, 3200.00, 0], $this->fetchEffectiveSalary(1));
        self::assertSame([4000.00, 4000.00, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function updateJobsWritesReadOnlyFieldsThroughDataHandler(): void
    {
        // The fields are "readOnly" in FormEngine only, DataHandler writes them
        // (tstamp is set by DataHandler, not by raw SQL)
        $jobSchema = $this->get(TcaSchemaFactory::class)->get(self::TABLE_JOB);
        self::assertTrue($jobSchema->getField('effective_salary_min')->getConfiguration()['readOnly']);
        self::assertTrue($jobSchema->getField('effective_salary_max')->getConfiguration()['readOnly']);
        self::assertTrue($jobSchema->getField('effective_salary_valid_until')->getConfiguration()['readOnly']);
        $this->setUpBackendUser(1);

        $this->subject->updateJobs([9]);

        self::assertSame([1000.00, 1000.00, 1767398400], $this->fetchEffectiveSalary(9));
        self::assertGreaterThan(1, (int)$this->fetchJob(9)['tstamp']);
    }

    #[Test]
    public function updateJobsWithUpToDateValuesDoesNotWriteAnything(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(0, $this->subject->updateJobs([4]));
        self::assertSame(1, (int)$this->fetchJob(4)['tstamp']);
        self::assertSame(1, (int)$this->fetchJob(5)['tstamp']);
    }

    #[Test]
    public function updateJobsTwiceWritesOnlyOnce(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(1, $this->subject->updateJobs([1]));
        self::assertSame(0, $this->subject->updateJobs([1]));
    }

    #[Test]
    public function updateJobsCopiesValuesIntoTranslations(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(1, $this->subject->updateJobs([7]));
        self::assertSame([2000.00, 2000.00, 0], $this->fetchEffectiveSalary(7));
        self::assertSame([2000.00, 2000.00, 0], $this->fetchEffectiveSalary(8));
    }

    #[Test]
    public function updateJobsWithUidOfTranslationCalculatesJobOfDefaultLanguage(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(1, $this->subject->updateJobs([8]));
        self::assertSame([2000.00, 2000.00, 0], $this->fetchEffectiveSalary(7));
        self::assertSame([2000.00, 2000.00, 0], $this->fetchEffectiveSalary(8));
    }

    #[Test]
    public function updateJobsRepairsOutdatedTranslationOfUpToDateJob(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(1, $this->subject->updateJobs([2]));
        self::assertSame([3000.00, 3500.00, 0], $this->fetchEffectiveSalary(2));
        self::assertSame([3000.00, 3500.00, 0], $this->fetchEffectiveSalary(3));
    }

    #[Test]
    public function updateJobsIgnoresDeletedAndUnknownJobs(): void
    {
        $this->setUpBackendUser(1);

        self::assertSame(0, $this->subject->updateJobs([6, 999]));
        self::assertSame([0.00, 0.00, 0], $this->fetchEffectiveSalary(6));
    }

    #[Test]
    public function updateJobsBypassesAccessChecksOfBackendUser(): void
    {
        // The editor has neither table, page nor language permissions
        $editor = $this->setUpBackendUser(2);

        self::assertSame(2, $this->subject->updateJobs([1, 7], $editor));
        self::assertSame([2500.00, 3200.00, 0], $this->fetchEffectiveSalary(1));
        self::assertSame([2000.00, 2000.00, 0], $this->fetchEffectiveSalary(8));
    }

    #[Test]
    public function updateJobsDoesNotWriteSystemLog(): void
    {
        $this->setUpBackendUser(1);

        $this->subject->updateJobs([1, 7]);

        self::assertSame(0, $this->countRows('sys_log', ['tablename' => self::TABLE_JOB]));
    }

    /**
     * DataHandler::$enableLogging only suppresses sys_log. sys_history is
     * written on purpose, so editors can see why the effective salary of a
     * job has changed.
     */
    #[Test]
    public function updateJobsWritesHistoryOfChangedJobs(): void
    {
        $this->setUpBackendUser(1);

        $this->subject->updateJobs([1, 7]);

        self::assertGreaterThan(0, $this->countRows('sys_history', ['tablename' => self::TABLE_JOB]));
    }

    /**
     * @param array<string, string> $identifiers
     */
    private function countRows(string $table, array $identifiers): int
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable($table)->count('*', $table, $identifiers);
    }

    #[Test]
    public function updateJobsUpdatesMoreJobsThanOneChunk(): void
    {
        $this->setUpBackendUser(1);
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE_JOB);
        $jobUids = range(100, 349);
        foreach ($jobUids as $jobUid) {
            $connection->insert(self::TABLE_JOB, [
                'uid' => $jobUid,
                'pid' => 2,
                'title' => 'Job ' . $jobUid,
                'salary_mode' => 1,
                'salary_min' => $jobUid,
            ]);
        }

        self::assertSame(count($jobUids), $this->subject->updateJobs($jobUids));
        self::assertSame([100.00, 100.00, 0], $this->fetchEffectiveSalary(100));
        self::assertSame([349.00, 349.00, 0], $this->fetchEffectiveSalary(349));
    }

    #[Test]
    public function updateByRecordsWithSalaryStepUpdatesJobsOfItsSalaryGrade(): void
    {
        $this->setUpBackendUser(1);
        $this->updateRecord('tx_jobboard_domain_model_salarystep', 1, ['amount' => 3100.0]);

        self::assertSame(
            1,
            $this->subject->updateByRecords([EffectiveSalaryUpdaterInterface::TABLE_SALARY_STEP => [1]]),
        );
        self::assertSame([3100.00, 3500.00, 0], $this->fetchEffectiveSalary(2));
        self::assertSame([3100.00, 3500.00, 0], $this->fetchEffectiveSalary(3));
        self::assertSame(1, (int)$this->fetchJob(4)['tstamp']);
    }

    #[Test]
    public function updateByRecordsWithSalaryGradeUpdatesItsJobs(): void
    {
        $this->setUpBackendUser(1);
        $this->updateRecord('tx_jobboard_domain_model_salarygrade', 2, ['hidden' => 1]);

        self::assertSame(
            1,
            $this->subject->updateByRecords([EffectiveSalaryUpdaterInterface::TABLE_SALARY_GRADE => [2]]),
        );
        self::assertSame([0.00, 0.00, 0], $this->fetchEffectiveSalary(4));
        self::assertSame([0.00, 0.00, 0], $this->fetchEffectiveSalary(5));
    }

    #[Test]
    public function updateByRecordsWithSalaryTableUpdatesJobsOfItsSalaryGrades(): void
    {
        $this->setUpBackendUser(1);
        $this->updateRecord('tx_jobboard_domain_model_salarygrade', 1, ['deleted' => 1]);

        self::assertSame(
            1,
            $this->subject->updateByRecords([EffectiveSalaryUpdaterInterface::TABLE_SALARY_TABLE => [1]]),
        );
        self::assertSame([0.00, 0.00, 0], $this->fetchEffectiveSalary(2));
    }

    #[Test]
    public function updateByRecordsWithJobsAndSalaryRecordsUpdatesAllOfThem(): void
    {
        $this->setUpBackendUser(1);
        $this->updateRecord('tx_jobboard_domain_model_salarygrade', 2, ['flat_amount' => 4100.0]);

        self::assertSame(2, $this->subject->updateByRecords([
            EffectiveSalaryUpdaterInterface::TABLE_JOB => [1],
            EffectiveSalaryUpdaterInterface::TABLE_SALARY_GRADE => [2],
        ]));
        self::assertSame([2500.00, 3200.00, 0], $this->fetchEffectiveSalary(1));
        self::assertSame([4100.00, 4100.00, 0], $this->fetchEffectiveSalary(4));
    }
}
