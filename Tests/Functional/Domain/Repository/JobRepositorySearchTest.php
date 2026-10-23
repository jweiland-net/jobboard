<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Domain\Repository;

use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Covers JobRepository::findBySearch(): the "title is not empty", "application
 * deadline not passed" and "employment not ended" base filters, the job area
 * filter (matching any of the MM related
 * job areas of a job), the job role/job type equality filters, the zip
 * (exact) / city (LIKE) address filter, and the free text search matching
 * title, address or Job RTE fields word by word, combined with logical AND.
 * Additionally covers the job area filter of JobRepository::findBySettings(),
 * the salary filter by the precomputed effective salary, the default
 * ordering, the limit in the database and JobRepository::findByUids().
 *
 * JobArea/JobRole/JobType have no dedicated repository (they are plain lookup
 * entities only ever read as a relation of Job), so single entities are
 * fetched directly via PersistenceManagerInterface::getObjectByIdentifier()
 * instead.
 */
class JobRepositorySearchTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected JobRepository $subject;

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/JobSearchResults.csv');

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);

        $this->subject = $this->get(JobRepository::class);
        $this->subject->setDefaultQuerySettings($querySettings);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    /**
     * @return string[]
     */
    private function findJobTitles(Search $search): array
    {
        return array_map(
            static fn(Job $job): string => $job->getTitle(),
            iterator_to_array($this->subject->findBySearch($search)),
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @return string[]
     */
    private function findJobTitlesBySearchAndSettings(Search $search, array $settings): array
    {
        return array_map(
            static fn(Job $job): string => $job->getTitle(),
            iterator_to_array($this->subject->findBySearch($search, settings: $settings)),
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @return string[]
     */
    private function findJobTitlesBySettings(array $settings): array
    {
        return array_map(
            static fn(Job $job): string => $job->getTitle(),
            iterator_to_array($this->subject->findBySettings($settings)),
        );
    }

    #[Test]
    public function findBySearchWithoutAnyCriteriaReturnsAllActiveJobsWithATitle(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with same city but different zip',
                'Job in a different city',
                'Job in city name containing search term',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job area',
                'Job in Pforzheim but other job type',
                'Job in Pforzheim but other job role',
                'Job with matching zip in another city',
                'Job with future application deadline',
            ],
            $this->findJobTitles(new Search(null, null, null, '', '')),
        );
    }

    #[Test]
    public function findBySearchByExactZipOnlyMatchesAddressesWithThatExactZip(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job area',
                'Job in Pforzheim but other job type',
                'Job in Pforzheim but other job role',
            ],
            $this->findJobTitles(new Search(null, null, null, '76133', '')),
        );
    }

    #[Test]
    public function findBySearchByCityUsesLikeMatchAndMatchesPartialCityNames(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with same city but different zip',
                'Job in city name containing search term',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job area',
                'Job in Pforzheim but other job type',
                'Job in Pforzheim but other job role',
            ],
            $this->findJobTitles(new Search(null, null, null, 'Pforzheim', '')),
        );
    }

    #[Test]
    public function findBySearchByAutoCompleteZipAndCityFormatCombinesBothWithLogicalOr(): void
    {
        // "76199 - Pforzheim" is the exact format the frontend autocomplete sends.
        // Zip 76199 belongs to Illingen, so this proves zip and city are
        // OR-combined, not AND-combined - both the exact zip match in Illingen
        // and every partial city match in Pforzheim are returned.
        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with same city but different zip',
                'Job in city name containing search term',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job area',
                'Job in Pforzheim but other job type',
                'Job in Pforzheim but other job role',
                'Job with matching zip in another city',
            ],
            $this->findJobTitles(new Search(null, null, null, '76199 - Pforzheim', '')),
        );
    }

    #[Test]
    public function findBySearchByJobAreaOnlyReturnsJobsOfThatJobArea(): void
    {
        $jobArea = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(1, JobArea::class);

        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with same city but different zip',
                'Job in a different city',
                'Job in city name containing search term',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job type',
                'Job in Pforzheim but other job role',
                'Job with matching zip in another city',
            ],
            $this->findJobTitles(new Search($jobArea, null, null, '', '')),
        );
    }

    #[Test]
    public function findBySearchByJobAreaMatchesJobsHavingThatJobAreaAmongOthers(): void
    {
        // "Job in a different city" references job areas 2 and 1
        $jobArea = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(2, JobArea::class);

        self::assertEqualsCanonicalizing(
            [
                'Job in a different city',
                'Job in Pforzheim but other job area',
                'Job with future application deadline',
            ],
            $this->findJobTitles(new Search($jobArea, null, null, '', '')),
        );
    }

    #[Test]
    public function findBySearchWithJobAreaListFromFlexFormReturnsJobsHavingAnyOfThem(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'Job in a different city',
                'Job in Pforzheim but other job area',
                'Job with future application deadline',
            ],
            $this->findJobTitlesBySearchAndSettings(new Search(null, null, null, '', ''), ['jobAreas' => '2']),
        );
    }

    #[Test]
    public function findBySearchWithJobAreaArrayFromTypoScriptReturnsJobsHavingAnyOfThem(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'Job in a different city',
                'Job in Pforzheim but other job area',
                'Job with future application deadline',
            ],
            $this->findJobTitlesBySearchAndSettings(new Search(null, null, null, '', ''), ['jobAreas' => ['2']]),
        );
    }

    #[Test]
    public function findBySearchCombinesJobAreaFromFlexFormWithOtherCriteria(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job in Pforzheim but other job area'],
            $this->findJobTitlesBySearchAndSettings(new Search(null, null, null, '76133', ''), ['jobAreas' => '2']),
        );
    }

    #[Test]
    public function findBySearchWithEmptyJobAreasFromFlexFormIsNotRestricted(): void
    {
        self::assertCount(
            10,
            $this->findJobTitlesBySearchAndSettings(new Search(null, null, null, '', ''), ['jobAreas' => '']),
        );
    }

    #[Test]
    public function findBySettingsWithoutJobAreasReturnsAllActiveJobsWithATitle(): void
    {
        self::assertCount(10, $this->findJobTitlesBySettings([]));
    }

    #[Test]
    public function findBySettingsWithJobAreaListFromFlexFormReturnsJobsHavingAnyOfThem(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'Job in a different city',
                'Job in Pforzheim but other job area',
                'Job with future application deadline',
            ],
            $this->findJobTitlesBySettings(['jobAreas' => '2']),
        );
    }

    #[Test]
    public function findBySettingsWithJobAreaArrayReturnsEveryMatchingJobOnlyOnce(): void
    {
        self::assertCount(10, $this->findJobTitlesBySettings(['jobAreas' => ['1', '2']]));
    }

    #[Test]
    public function findBySettingsHidesJobWithPassedApplicationDeadlineEvenIfEmploymentEndsInFuture(): void
    {
        self::assertNotContains(
            'Job with passed application deadline',
            $this->findJobTitlesBySettings([]),
        );
    }

    #[Test]
    public function findBySettingsListsJobWithEmploymentEndDateInFuture(): void
    {
        self::assertContains(
            'Job with far future ending date in Pforzheim',
            $this->findJobTitlesBySettings([]),
        );
    }

    #[Test]
    public function findBySettingsHidesJobWithEndedEmploymentEvenIfApplicationDeadlineIsInFuture(): void
    {
        $titles = $this->findJobTitlesBySettings([]);

        self::assertNotContains('Job with future application deadline but ended employment', $titles);
        self::assertNotContains('Expired job in Pforzheim', $titles);
    }

    #[Test]
    public function findBySettingsListsJobOnTheDayOfItsApplicationDeadline(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->update(
                'tx_jobboard_domain_model_job',
                ['application_deadline' => (new \DateTime('today'))->getTimestamp()],
                ['uid' => 12],
            );

        self::assertContains(
            'Job with passed application deadline',
            $this->findJobTitlesBySettings([]),
        );
    }

    #[Test]
    public function findBySettingsSortsJobsByApplicationDeadline(): void
    {
        $titles = $this->findJobTitlesBySettings(['jobAreas' => '2']);

        self::assertSame('Job with future application deadline', end($titles));
    }

    #[Test]
    public function findBySearchByJobRoleOnlyReturnsJobsOfThatJobRole(): void
    {
        $jobRole = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(1, JobRole::class);

        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with same city but different zip',
                'Job in a different city',
                'Job in city name containing search term',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job area',
                'Job in Pforzheim but other job type',
                'Job with matching zip in another city',
            ],
            $this->findJobTitles(new Search(null, $jobRole, null, '', '')),
        );
    }

    #[Test]
    public function findBySearchByJobTypeOnlyReturnsJobsOfThatJobType(): void
    {
        $jobType = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(1, JobType::class);

        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job in a different city',
                'Job in city name containing search term',
                'Job with far future ending date in Pforzheim',
                'Job in Pforzheim but other job area',
                'Job in Pforzheim but other job role',
                'Job with matching zip in another city',
            ],
            $this->findJobTitles(new Search(null, null, $jobType, '', '')),
        );
    }

    #[Test]
    public function findBySearchCombinesJobAreaJobRoleJobTypeAndAddressWithLogicalAnd(): void
    {
        $jobArea = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(1, JobArea::class);
        $jobRole = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(1, JobRole::class);
        $jobType = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier(1, JobType::class);

        self::assertEqualsCanonicalizing(
            [
                'Job with exact zip match',
                'Job with far future ending date in Pforzheim',
            ],
            $this->findJobTitles(new Search($jobArea, $jobRole, $jobType, '76133', '')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesTitle(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job with exact zip match'],
            $this->findJobTitles(new Search(null, null, null, '', 'exact')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesAddressStreet(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job in a different city'],
            $this->findJobTitles(new Search(null, null, null, '', 'Marktplatz')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesAddressCity(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job with matching zip in another city'],
            $this->findJobTitles(new Search(null, null, null, '', 'Illingen')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesDescription(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job with same city but different zip'],
            $this->findJobTitles(new Search(null, null, null, '', 'Flexible')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesOffer(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job in a different city'],
            $this->findJobTitles(new Search(null, null, null, '', 'Firmenwagen')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesRequirements(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job in city name containing search term'],
            $this->findJobTitles(new Search(null, null, null, '', 'Führerschein')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesFurtherInformation(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job with far future ending date in Pforzheim'],
            $this->findJobTitles(new Search(null, null, null, '', 'Weiterbildung')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordMatchesApplicationGuidelines(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job in Pforzheim but other job area'],
            $this->findJobTitles(new Search(null, null, null, '', 'Bewerbung')),
        );
    }

    #[Test]
    public function findBySearchBySearchWordWithMultipleWordsCombinesThemWithLogicalAnd(): void
    {
        self::assertEqualsCanonicalizing(
            ['Job with exact zip match'],
            $this->findJobTitles(new Search(null, null, null, '', 'Pforzheim exact')),
        );
    }

    /**
     * @param iterable<Job> $jobs
     * @return int[]
     */
    private function getUids(iterable $jobs): array
    {
        $uids = [];
        foreach ($jobs as $job) {
            $uids[] = (int)$job->getUid();
        }

        return $uids;
    }

    /**
     * @param array<string, float> $effectiveSalary
     */
    private function updateJob(int $jobUid, array $effectiveSalary): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->update('tx_jobboard_domain_model_job', $effectiveSalary, ['uid' => $jobUid]);
    }

    /**
     * @return array<string, array{array<string, float>, bool}>
     */
    public static function effectiveSalaryDataProvider(): array
    {
        return [
            'no effective salary' => [['effective_salary_min' => 0.0, 'effective_salary_max' => 0.0], false],
            'effective minimum only' => [['effective_salary_min' => 2500.0, 'effective_salary_max' => 0.0], true],
            'effective maximum only' => [['effective_salary_min' => 0.0, 'effective_salary_max' => 3200.0], true],
        ];
    }

    /**
     * The salary is filtered by the precomputed effective salary only, the
     * free entry salary of the job ("salary_min" = 3000) is not considered.
     *
     * @param array<string, float> $effectiveSalary
     */
    #[Test]
    #[DataProvider('effectiveSalaryDataProvider')]
    public function findBySettingsAndFindBySearchFilterByEffectiveSalaryInDatabase(
        array $effectiveSalary,
        bool $expectedListed,
    ): void {
        $this->updateJob(1, $effectiveSalary);

        self::assertSame($expectedListed, in_array(1, $this->getUids($this->subject->findBySettings([])), true));
        self::assertSame(
            $expectedListed,
            in_array(1, $this->getUids($this->subject->findBySearch(new Search(null, null, null, '', ''))), true),
        );
    }

    #[Test]
    public function findBySettingsSortsByApplicationDeadlineAndUid(): void
    {
        $this->updateJob(1, ['application_deadline' => 4102444800]);

        $jobs = $this->subject->findBySettings([]);

        self::assertSame([2, 3, 4, 7, 8, 9, 10, 11, 1, 13], $this->getUids($jobs));
        self::assertSame(
            [
                'applicationDeadline' => QueryInterface::ORDER_ASCENDING,
                'uid' => QueryInterface::ORDER_ASCENDING,
            ],
            $jobs->getQuery()->getOrderings(),
        );
    }

    #[Test]
    public function findBySettingsAppliesLimitInDatabaseAfterSalaryFilter(): void
    {
        // The first two jobs in default order are jobs 1 and 2
        $this->updateJob(1, ['effective_salary_min' => 0.0, 'effective_salary_max' => 0.0]);

        $jobs = $this->subject->findBySettings([], 2);

        self::assertSame(2, $jobs->getQuery()->getLimit());
        self::assertSame([2, 3], $this->getUids($jobs));
    }

    #[Test]
    public function findBySettingsReturnsUnexecutedQueryResult(): void
    {
        $jobs = $this->subject->findBySettings([]);

        self::assertInstanceOf(QueryResultInterface::class, $jobs);
        self::assertSame(10, $jobs->count());
    }

    #[Test]
    public function findBySearchAppliesLimitInDatabase(): void
    {
        $jobs = $this->subject->findBySearch(new Search(null, null, null, '76133', ''), 2);

        self::assertSame(2, $jobs->getQuery()->getLimit());
        self::assertSame([1, 7], $this->getUids($jobs));
    }

    #[Test]
    public function findByUidsReturnsListedJobsOnlyOrderedLikeTheList(): void
    {
        $this->updateJob(3, ['effective_salary_min' => 0.0, 'effective_salary_max' => 0.0]);

        // 3: no salary, 5: ended employment, 6: no title, 12: deadline
        // passed, 999: does not exist
        $jobs = $this->subject->findByUids([13, 999, 12, 6, 5, 3, 2, 1]);

        self::assertSame([1, 2, 13], $this->getUids($jobs));
    }

    #[Test]
    public function findByUidsWithoutUidsReturnsNoJobs(): void
    {
        self::assertSame([], $this->getUids($this->subject->findByUids([])));
    }
}
