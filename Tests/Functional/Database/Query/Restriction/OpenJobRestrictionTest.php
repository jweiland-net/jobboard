<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Database\Query\Restriction;

use JWeiland\Jobboard\Database\Query\Restriction\OpenJobRestriction;
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * OpenJobRestriction is the QueryBuilder twin of
 * JobRepository::buildAndConstraintForOpenJobs(): both must select the same
 * jobs. The fixture of JobRepositorySearchTest is extended with the edge
 * cases of each rule (UIDs 100+).
 */
final class OpenJobRestrictionTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    private const TABLE = 'tx_jobboard_domain_model_job';

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../../Fixtures/JobSearchResults.csv');
        $this->insertEdgeCases();
    }

    private function insertEdgeCases(): void
    {
        $today = (new \DateTimeImmutable('today'))->getTimestamp();
        $yesterday = (new \DateTimeImmutable('yesterday'))->getTimestamp();
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->getTimestamp();
        $edgeCases = [
            100 => ['application_deadline' => $today],
            101 => ['application_deadline' => $yesterday],
            102 => ['application_deadline' => $tomorrow],
            103 => ['ending_date' => $today],
            104 => ['ending_date' => $yesterday],
            105 => ['ending_date' => $tomorrow],
            106 => ['effective_salary_min' => 0.0, 'effective_salary_max' => 0.0],
            107 => ['effective_salary_min' => 0.0, 'effective_salary_max' => 0.01],
            108 => ['effective_salary_min' => 0.01, 'effective_salary_max' => 0.0],
            109 => ['title' => ''],
            110 => ['title' => ' '],
            111 => ['application_deadline' => $today - 1],
            112 => ['ending_date' => $today - 1],
            113 => ['application_deadline' => $tomorrow - 1, 'ending_date' => $today],
        ];

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE);
        foreach ($edgeCases as $uid => $values) {
            $connection->insert(self::TABLE, array_replace([
                'uid' => $uid,
                'pid' => self::STORAGE_PAGE,
                'title' => 'Edge case ' . $uid,
                'effective_salary_min' => 3000.0,
                'effective_salary_max' => 3000.0,
                'application_deadline' => 0,
                'ending_date' => 0,
            ], $values));
        }
    }

    /**
     * @return int[]
     */
    private function findUidsByRepository(bool $salaryRequired = true): array
    {
        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);
        $jobRepository = $this->get(JobRepository::class);
        $jobRepository->setDefaultQuerySettings($querySettings);

        return array_map(
            static fn(Job $job): int => (int)$job->getUid(),
            iterator_to_array($jobRepository->findBySettings([], 0, $salaryRequired)),
        );
    }

    private function createQueryBuilder(OpenJobRestriction $restriction): QueryBuilder
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->setRestrictions(new FrontendRestrictionContainer());
        $queryBuilder->getRestrictions()->add($restriction);

        return $queryBuilder;
    }

    /**
     * @return int[]
     */
    private function findUidsByRestriction(?OpenJobRestriction $restriction = null): array
    {
        $queryBuilder = $this->createQueryBuilder($restriction ?? OpenJobRestriction::forToday());

        return array_map(intval(...), $queryBuilder
            ->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(self::STORAGE_PAGE, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter([0, -1], Connection::PARAM_INT_ARRAY),
                ),
            )
            ->orderBy('application_deadline', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn());
    }

    #[Test]
    public function restrictionSelectsTheSameJobsAsJobRepository(): void
    {
        $repositoryUids = $this->findUidsByRepository();

        self::assertNotSame([], $repositoryUids);
        self::assertSame($repositoryUids, $this->findUidsByRestriction());
    }

    #[Test]
    public function restrictionListsJobsThroughTheEndOfTheirDeadlineOrEndingDay(): void
    {
        $uids = $this->findUidsByRestriction();

        foreach ([100, 102, 103, 105, 113] as $listedUid) {
            self::assertContains($listedUid, $uids, 'Job ' . $listedUid . ' must be listed');
        }
        foreach ([101, 104, 111, 112] as $notListedUid) {
            self::assertNotContains($notListedUid, $uids, 'Job ' . $notListedUid . ' must not be listed');
        }
    }

    #[Test]
    public function restrictionRequiresEffectiveSalaryMinimumOrMaximum(): void
    {
        $uids = $this->findUidsByRestriction();

        self::assertNotContains(106, $uids);
        self::assertContains(107, $uids);
        self::assertContains(108, $uids);
    }

    #[Test]
    public function restrictionWithoutSalaryRequirementSelectsTheSameJobsAsJobRepository(): void
    {
        $repositoryUids = $this->findUidsByRepository(false);

        self::assertContains(106, $repositoryUids);
        self::assertSame($repositoryUids, $this->findUidsByRestriction(OpenJobRestriction::forToday(false)));
    }

    #[Test]
    public function restrictionWithoutSalaryRequirementListsJobsWithoutEffectiveSalary(): void
    {
        $uids = $this->findUidsByRestriction(OpenJobRestriction::forToday(false));

        self::assertContains(106, $uids);
        self::assertContains(107, $uids);
        self::assertContains(108, $uids);
    }

    #[Test]
    public function restrictionWithoutSalaryRequirementKeepsAllOtherRules(): void
    {
        $uids = $this->findUidsByRestriction(OpenJobRestriction::forToday(false));

        foreach ([101, 104, 109, 111, 112, 6] as $notListedUid) {
            self::assertNotContains($notListedUid, $uids, 'Job ' . $notListedUid . ' must not be listed');
        }
        foreach ([100, 102, 103, 105, 113] as $listedUid) {
            self::assertContains($listedUid, $uids, 'Job ' . $listedUid . ' must be listed');
        }
    }

    #[Test]
    public function restrictionRequiresSalaryByDefault(): void
    {
        self::assertSame($this->findUidsByRestriction(), $this->findUidsByRestriction(new OpenJobRestriction(
            (new \DateTimeImmutable('today'))->getTimestamp(),
        )));
        self::assertNotContains(106, $this->findUidsByRestriction(OpenJobRestriction::forToday(true)));
    }

    #[Test]
    public function restrictionRequiresATitle(): void
    {
        $uids = $this->findUidsByRestriction();

        self::assertNotContains(109, $uids);
        self::assertNotContains(6, $uids);
    }

    #[Test]
    public function restrictionAndRepositoryTreatTitleWithWhitespaceOnlyTheSame(): void
    {
        self::assertSame(
            in_array(110, $this->findUidsByRepository(), true),
            in_array(110, $this->findUidsByRestriction(), true),
        );
    }

    #[Test]
    public function restrictionUsesGivenStartOfToday(): void
    {
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->getTimestamp();

        $uids = $this->findUidsByRestriction(new OpenJobRestriction($tomorrow));

        self::assertNotContains(100, $uids);
        self::assertContains(102, $uids);
        self::assertNotContains(103, $uids);
        self::assertContains(105, $uids);
    }

    #[Test]
    public function restrictionIsAppliedToAliasOfJobTable(): void
    {
        $queryBuilder = $this->createQueryBuilder(OpenJobRestriction::forToday());
        $uids = array_map(intval(...), $queryBuilder
            ->select('job.uid')
            ->from(self::TABLE, 'job')
            ->join(
                'job',
                'tt_address',
                'address',
                $queryBuilder->expr()->eq('address.uid', $queryBuilder->quoteIdentifier('job.address')),
            )
            ->where($queryBuilder->expr()->eq('address.uid', $queryBuilder->createNamedParameter(6, Connection::PARAM_INT)))
            ->orderBy('job.uid', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn());

        // Address 6: 12 (deadline passed), 13 (open), 14 (employment ended)
        self::assertSame([13], $uids);
    }

    #[Test]
    public function restrictionIsNotAppliedToOtherTables(): void
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tt_address');
        $queryBuilder->getRestrictions()->removeAll()->add(OpenJobRestriction::forToday());

        // tt_address has neither "title" nor "effective_salary_min" columns
        self::assertSame(6, (int)$queryBuilder->count('uid')->from('tt_address')->executeQuery()->fetchOne());
    }
}
