<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\Service\JobSearchOptionsService;
use JWeiland\Jobboard\Service\JobSearchOptionsServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Uses the job queries of JobRepository, like the list and search action.
 * Listed jobs of the fixture (without any criteria): 1, 2, 3, 4, 7, 8, 9,
 * 10, 11 and 13.
 */
final class JobSearchOptionsServiceTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private JobSearchOptionsServiceInterface $subject;

    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobSearchResults.csv');

        $this->subject = $this->get(JobSearchOptionsServiceInterface::class);
        $this->jobRepository = $this->get(JobRepository::class);
        $this->setLanguageAspect(new LanguageAspect());
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
            $this->jobRepository,
        );

        parent::tearDown();
    }

    private function setLanguageAspect(LanguageAspect $languageAspect): void
    {
        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);
        $querySettings->setLanguageAspect($languageAspect);
        $this->jobRepository->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, array<int|string, string>>
     */
    private function getSearchOptions(QueryInterface $query, array $settings = [], bool $showJobAreaCount = false): array
    {
        $searchOptions = $this->subject->getSearchOptions(
            $query,
            JobAreaRestriction::fromSettings($settings),
            $showJobAreaCount,
        );

        // Sorting is done in Fluid, the order of the options is irrelevant
        foreach ($searchOptions as &$options) {
            ksort($options);
        }

        return $searchOptions;
    }

    #[Test]
    public function serviceIsRegisteredForInterface(): void
    {
        self::assertInstanceOf(JobSearchOptionsService::class, $this->subject);
    }

    #[Test]
    public function getSearchOptionsReturnsOptionsOfAllListedJobs(): void
    {
        self::assertSame(
            [
                'jobAreas' => [1 => 'IT', 2 => 'Handwerk'],
                'jobRoles' => [1 => 'Specialist', 2 => 'Apprentice'],
                'jobTypes' => [1 => 'Vollzeit', 2 => 'Teilzeit'],
                'jobLocations' => [
                    'Bad Wildbad' => 'Bad Wildbad',
                    'Illingen' => 'Illingen',
                    'Pforzheim' => 'Pforzheim',
                    'Pforzheim-Eutingen' => 'Pforzheim-Eutingen',
                    'Sample Town' => 'Sample Town',
                ],
            ],
            $this->getSearchOptions($this->jobRepository->findBySettings([])->getQuery()),
        );
    }

    #[Test]
    public function getSearchOptionsCountsListedJobsPerJobArea(): void
    {
        // Job 3 has both job areas. Closed jobs (5, 12, 14) and the job
        // without title (6) are not counted.
        self::assertSame(
            [1 => 'IT (8)', 2 => 'Handwerk (3)'],
            $this->getSearchOptions($this->jobRepository->findBySettings([])->getQuery(), [], true)['jobAreas'],
        );
    }

    #[Test]
    public function getSearchOptionsDoesNotCountJobsWithoutEffectiveSalary(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->update(
                'tx_jobboard_domain_model_job',
                ['effective_salary_min' => 0.0, 'effective_salary_max' => 0.0],
                ['uid' => 13],
            );

        $searchOptions = $this->getSearchOptions($this->jobRepository->findBySettings([])->getQuery(), [], true);

        self::assertSame([1 => 'IT (8)', 2 => 'Handwerk (2)'], $searchOptions['jobAreas']);
        self::assertArrayNotHasKey('Sample Town', $searchOptions['jobLocations']);
    }

    #[Test]
    public function getSearchOptionsCountsJobsWithoutEffectiveSalaryIfSalaryIsNotRequired(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->update(
                'tx_jobboard_domain_model_job',
                ['effective_salary_min' => 0.0, 'effective_salary_max' => 0.0],
                ['uid' => 13],
            );

        $query = $this->jobRepository->findBySettings([], 0, false)->getQuery();
        $searchOptions = $this->getSearchOptions($query, [], true);

        self::assertSame([1 => 'IT (8)', 2 => 'Handwerk (3)'], $searchOptions['jobAreas']);
        self::assertArrayHasKey('Sample Town', $searchOptions['jobLocations']);
    }

    #[Test]
    public function getSearchOptionsOnlyContainsJobAreasOfPluginRestriction(): void
    {
        // Jobs 3, 8 and 13. Job 3 also has job area 1, which is not offered.
        $settings = ['jobAreas' => '2'];

        self::assertSame(
            [
                'jobAreas' => [2 => 'Handwerk (3)'],
                'jobRoles' => [1 => 'Specialist', 2 => 'Apprentice'],
                'jobTypes' => [1 => 'Vollzeit', 2 => 'Teilzeit'],
                'jobLocations' => [
                    'Bad Wildbad' => 'Bad Wildbad',
                    'Pforzheim' => 'Pforzheim',
                    'Sample Town' => 'Sample Town',
                ],
            ],
            $this->getSearchOptions($this->jobRepository->findBySettings($settings)->getQuery(), $settings, true),
        );
    }

    #[Test]
    public function getSearchOptionsUsesSearchCriteria(): void
    {
        // Jobs 1, 7, 8, 9 and 11 have the zip 76133
        $query = $this->jobRepository->findBySearch(new Search(null, null, null, '76133', ''))->getQuery();

        self::assertSame(
            [
                'jobAreas' => [1 => 'IT (4)', 2 => 'Handwerk (1)'],
                'jobRoles' => [1 => 'Specialist', 2 => 'Apprentice'],
                'jobTypes' => [1 => 'Vollzeit', 2 => 'Teilzeit'],
                'jobLocations' => ['Pforzheim' => 'Pforzheim'],
            ],
            $this->getSearchOptions($query, [], true),
        );
    }

    #[Test]
    public function getSearchOptionsWithLimitOnlyContainsOptionsOfLimitedJobs(): void
    {
        // "maxEntries" 2: jobs 1 and 2
        self::assertSame(
            [
                'jobAreas' => [1 => 'IT (2)'],
                'jobRoles' => [1 => 'Specialist'],
                'jobTypes' => [1 => 'Vollzeit', 2 => 'Teilzeit'],
                'jobLocations' => ['Pforzheim' => 'Pforzheim'],
            ],
            $this->getSearchOptions($this->jobRepository->findBySettings([], 2)->getQuery(), [], true),
        );
    }

    #[Test]
    public function getSearchOptionsWithoutMatchingJobsReturnsEmptyOptions(): void
    {
        $query = $this->jobRepository->findBySearch(new Search(null, null, null, '', 'nothing matches'))->getQuery();

        self::assertSame(
            ['jobAreas' => [], 'jobRoles' => [], 'jobTypes' => [], 'jobLocations' => []],
            $this->getSearchOptions($query, [], true),
        );
    }

    #[Test]
    public function getSearchOptionsDoesNotOfferHiddenTitles(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_jobrole')
            ->update('tx_jobboard_domain_model_jobrole', ['hidden' => 1], ['uid' => 2]);

        self::assertSame(
            [1 => 'Specialist'],
            $this->getSearchOptions($this->jobRepository->findBySettings([])->getQuery())['jobRoles'],
        );
    }

    #[Test]
    public function getSearchOptionsTranslatesTitlesIntoLanguageOfQuery(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_jobarea')
            ->insert('tx_jobboard_domain_model_jobarea', [
                'uid' => 10,
                'pid' => self::STORAGE_PAGE,
                'sys_language_uid' => 1,
                'l10n_parent' => 1,
                'title' => 'Informationstechnik',
            ]);
        $this->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_MIXED));

        self::assertSame(
            [1 => 'Informationstechnik (8)', 2 => 'Handwerk (3)'],
            $this->getSearchOptions($this->jobRepository->findBySettings([])->getQuery(), [], true)['jobAreas'],
        );
    }

    /**
     * @return int[]
     */
    private function getAddressUids(QueryInterface $query): array
    {
        $addressUids = $this->subject->getAddressUids($query);
        sort($addressUids);

        return $addressUids;
    }

    #[Test]
    public function getAddressUidsReturnsEachAddressOfAllListedJobsOnce(): void
    {
        // Several jobs share address 1, see getSearchOptionsUsesSearchCriteria()
        self::assertSame(
            [1, 2, 3, 4, 5, 6],
            $this->getAddressUids($this->jobRepository->findBySettings([])->getQuery()),
        );
    }

    #[Test]
    public function getAddressUidsUsesSearchCriteria(): void
    {
        // Jobs 1, 7, 8, 9 and 11 have the zip 76133, all of them address 1
        $query = $this->jobRepository->findBySearch(new Search(null, null, null, '76133', ''))->getQuery();

        self::assertSame([1], $this->getAddressUids($query));
    }

    #[Test]
    public function getAddressUidsWithLimitOnlyContainsAddressesOfLimitedJobs(): void
    {
        // "maxEntries" 2: jobs 1 and 2
        self::assertSame([1, 2], $this->getAddressUids($this->jobRepository->findBySettings([], 2)->getQuery()));
    }

    #[Test]
    public function getAddressUidsWithoutMatchingJobsReturnsEmptyArray(): void
    {
        $query = $this->jobRepository->findBySearch(new Search(null, null, null, '', 'nothing matches'))->getQuery();

        self::assertSame([], $this->getAddressUids($query));
    }
}
