<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Tests\Functional\Domain\Repository;

use JWeiland\Jobfair2\Domain\Model\Job;
use JWeiland\Jobfair2\Domain\Repository\JobRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * A job without resolvable salary information must never reach the frontend -
 * this is a legal requirement, not a cosmetic one. Covers every way the
 * salary information behind a job can become unresolvable through TYPO3's
 * own access restrictions (hidden/starttime/endtime on the salary grade or
 * its steps), not just the "editor never filled it in" case.
 */
class JobVisibilityBySalaryInformationTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected JobRepository $subject;

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobfair2',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/JobVisibilityBySalaryInformation.csv');

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);

        $this->subject = GeneralUtility::makeInstance(JobRepository::class);
        $this->subject->setDefaultQuerySettings($querySettings);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    private function findVisibleJobTitles(): array
    {
        return array_map(
            static fn(Job $job): string => $job->getTitle(),
            $this->subject->findBySearchCriteria([]),
        );
    }

    #[Test]
    public function findBySearchCriteriaExcludesJobWithExpiredSalaryGrade(): void
    {
        self::assertNotContains(
            'Job with expired grade',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaExcludesJobWithHiddenSalaryGrade(): void
    {
        self::assertNotContains(
            'Job with hidden grade',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaExcludesJobWithNotYetStartedSalaryGrade(): void
    {
        self::assertNotContains(
            'Job with not yet started grade',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaExcludesJobWhoseSalaryGradeStepsAreAllExpired(): void
    {
        self::assertNotContains(
            'Job with all steps expired',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaExcludesJobWithoutAnySalaryGradeSelected(): void
    {
        self::assertNotContains(
            'Job without any salary grade selected',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaExcludesFreeEntryJobWithoutAnyAmount(): void
    {
        self::assertNotContains(
            'Job with free entry but no amount at all',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaIncludesJobWithVisibleSteppedSalaryGrade(): void
    {
        self::assertContains(
            'Job with visible stepped grade',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaIncludesFreeEntryJobWithARange(): void
    {
        self::assertContains(
            'Job with free entry range',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaIncludesFreeEntryJobWithASingleAmount(): void
    {
        self::assertContains(
            'Job with a single free entry amount',
            $this->findVisibleJobTitles(),
        );
    }

    #[Test]
    public function findBySearchCriteriaAppliesLimitAfterFilteringOutJobsWithoutSalaryInformation(): void
    {
        // Without excluded jobs there are only 3 jobs eligible in total (see setUp fixture):
        // "Job with visible stepped grade", "Job with free entry range" and
        // "Job with a single free entry amount". A limit of 2 must return exactly 2 of
        // those - never fewer just because ineligible jobs were skipped internally.
        $jobs = $this->subject->findBySearchCriteria([], 2);

        self::assertCount(
            2,
            $jobs,
        );
    }
}
