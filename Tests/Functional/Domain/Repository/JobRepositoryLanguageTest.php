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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * JobRepository in site language 1 with the overlay types of the fallback
 * types "fallback" (OVERLAYS_MIXED), "strict" (OVERLAYS_ON) and "free"
 * (OVERLAYS_OFF). Default jobs 1 to 4 (4 expired), translations 11, 12 and
 * 14 (of 1, 2 and 4). Job 3 has no translation. Job areas, roles and types
 * have translations (3, 4), the translated jobs reference the default UIDs.
 */
final class JobRepositoryLanguageTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/JobRepositoryLanguage.csv');
    }

    /**
     * Like a frontend request of site language 1: the Context (used for the
     * related objects and the overlays) and the query settings.
     */
    private function createSubject(int $languageId, string $overlayType = LanguageAspect::OVERLAYS_MIXED): JobRepository
    {
        $languageAspect = new LanguageAspect($languageId, $languageId, $overlayType, $languageId > 0 ? [0] : []);
        $this->get(Context::class)->setAspect('language', $languageAspect);

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);
        $querySettings->setLanguageAspect($languageAspect);

        $subject = $this->get(JobRepository::class);
        $subject->setDefaultQuerySettings($querySettings);

        return $subject;
    }

    /**
     * @param iterable<Job> $jobs
     * @return array<int, string> default UID => title
     */
    private function getTitles(iterable $jobs): array
    {
        $titles = [];
        foreach ($jobs as $job) {
            $titles[(int)$job->getUid()] = $job->getTitle();
        }
        ksort($titles);

        return $titles;
    }

    /**
     * @template T of AbstractDomainObject
     * @param class-string<T> $className
     * @return T
     */
    private function getObject(int $uid, string $className): AbstractDomainObject
    {
        $object = $this->get(PersistenceManagerInterface::class)->getObjectByIdentifier($uid, $className);
        self::assertInstanceOf($className, $object);

        return $object;
    }

    #[Test]
    public function relatedObjectsAreTranslatedInLanguage1(): void
    {
        $this->createSubject(1);

        $jobRole = $this->getObject(1, JobRole::class);

        // Precondition of the search tests: the localized UID differs
        self::assertSame('Fachkraft', $jobRole->getTitle());
        self::assertSame(3, $jobRole->_getProperty(AbstractDomainObject::PROPERTY_LOCALIZED_UID));
    }

    #[Test]
    public function findByUidsReturnsTranslatedJobsForUidsOfDefaultLanguageWithFallbackTypeFallback(): void
    {
        $jobs = $this->createSubject(1)->findByUids([1, 2, 3, 4]);

        self::assertSame(
            [
                1 => 'Fachkraft in Vollzeit in der IT',
                2 => 'Auszubildender in Teilzeit im Handwerk',
                3 => 'Part-time specialist in IT without translation',
            ],
            $this->getTitles($jobs),
        );
    }

    #[Test]
    public function findByUidsReturnsTranslatedJobsWithLocalizedUid(): void
    {
        $jobs = iterator_to_array($this->createSubject(1)->findByUids([1]));

        self::assertCount(1, $jobs);
        self::assertSame(11, $jobs[0]->_getProperty(AbstractDomainObject::PROPERTY_LOCALIZED_UID));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function overlayTypeDataProvider(): array
    {
        return [
            'fallback type "fallback"' => [LanguageAspect::OVERLAYS_MIXED],
            'fallback type "strict"' => [LanguageAspect::OVERLAYS_ON],
            'fallback type "free"' => [LanguageAspect::OVERLAYS_OFF],
        ];
    }

    /**
     * The remembered jobs view must not show other jobs than the job list
     * of the same language: with "strict" and "free", the list hides jobs
     * without translation (job 3).
     */
    #[Test]
    #[DataProvider('overlayTypeDataProvider')]
    public function findByUidsReturnsTheJobsOfFindBySettingsInLanguage1(string $overlayType): void
    {
        $subject = $this->createSubject(1, $overlayType);

        self::assertSame(
            $this->getTitles($subject->findBySettings([])),
            $this->getTitles($subject->findByUids([1, 2, 3, 4])),
        );
    }

    #[Test]
    public function findByUidsReturnsJobsOfDefaultLanguage(): void
    {
        self::assertSame(
            [
                1 => 'Full-time specialist in IT',
                2 => 'Part-time apprentice in crafts',
                3 => 'Part-time specialist in IT without translation',
            ],
            $this->getTitles($this->createSubject(0)->findByUids([1, 2, 3, 4])),
        );
    }

    /**
     * @return array<string, array{int[], int, string}>
     */
    public static function uidsWithoutPositiveUidDataProvider(): array
    {
        $uidLists = [
            'no UIDs' => [],
            'UID 0' => [0],
            'negative UID' => [-1],
            'UID 0 and negative UIDs' => [0, -1, -4],
        ];
        $dataSets = [];
        foreach ($uidLists as $label => $uids) {
            $dataSets[$label . ', language 0'] = [$uids, 0, LanguageAspect::OVERLAYS_MIXED];
            foreach (self::overlayTypeDataProvider() as $overlayLabel => [$overlayType]) {
                $dataSets[$label . ', language 1, ' . $overlayLabel] = [$uids, 1, $overlayType];
            }
        }

        return $dataSets;
    }

    /**
     * Regression: "l10n_parent IN (0)" matches every record of the default
     * language, so an empty list or UID 0 must not match anything.
     *
     * @param int[] $uids
     */
    #[Test]
    #[DataProvider('uidsWithoutPositiveUidDataProvider')]
    public function findByUidsWithoutPositiveUidReturnsNoJobs(array $uids, int $languageId, string $overlayType): void
    {
        $jobs = $this->createSubject($languageId, $overlayType)->findByUids($uids);

        self::assertSame([], $this->getTitles($jobs));
        self::assertSame(0, $jobs->count());
    }

    /**
     * @return array<string, array{int, string, int[]}>
     */
    public static function uidsWithNonPositiveUidDataProvider(): array
    {
        return [
            'language 0' => [0, LanguageAspect::OVERLAYS_MIXED, [2, 3]],
            'language 1, fallback type "fallback"' => [1, LanguageAspect::OVERLAYS_MIXED, [2, 3]],
            'language 1, fallback type "strict"' => [1, LanguageAspect::OVERLAYS_ON, [2]],
            'language 1, fallback type "free"' => [1, LanguageAspect::OVERLAYS_OFF, [2]],
        ];
    }

    /**
     * Non-positive UIDs next to valid ones are ignored, they do not widen
     * the result to every record of the default language.
     *
     * @param int[] $expectedUids
     */
    #[Test]
    #[DataProvider('uidsWithNonPositiveUidDataProvider')]
    public function findByUidsIgnoresNonPositiveUids(int $languageId, string $overlayType, array $expectedUids): void
    {
        $jobs = $this->createSubject($languageId, $overlayType)->findByUids([0, 2, -1, 3]);

        self::assertSame($expectedUids, array_keys($this->getTitles($jobs)));
    }

    /**
     * @return array<string, array{string, int, int[]}>
     */
    public static function relationDataProvider(): array
    {
        return [
            'job role 1' => [JobRole::class, 1, [1, 3]],
            'job role 2' => [JobRole::class, 2, [2]],
            'job type 1' => [JobType::class, 1, [1]],
            'job type 2' => [JobType::class, 2, [2, 3]],
            'job area 1' => [JobArea::class, 1, [1, 3]],
            'job area 2' => [JobArea::class, 2, [2]],
        ];
    }

    /**
     * @param class-string<AbstractDomainObject> $className
     */
    private function createSearch(string $className, int $uid): Search
    {
        $object = $this->getObject($uid, $className);

        return new Search(
            $object instanceof JobArea ? $object : null,
            $object instanceof JobRole ? $object : null,
            $object instanceof JobType ? $object : null,
            '',
            '',
        );
    }

    /**
     * @param class-string<AbstractDomainObject> $className
     * @param int[] $expectedJobUids
     */
    #[Test]
    #[DataProvider('relationDataProvider')]
    public function findBySearchByRelationReturnsJobsInDefaultLanguage(string $className, int $uid, array $expectedJobUids): void
    {
        $subject = $this->createSubject(0);

        $jobs = $subject->findBySearch($this->createSearch($className, $uid));

        self::assertSame($expectedJobUids, array_keys($this->getTitles($jobs)));
    }

    /**
     * Typo3DbQueryParser binds "_localizedUid" of a translated domain object,
     * the translated jobs reference the default UIDs: same jobs as language 0.
     *
     * @param class-string<AbstractDomainObject> $className
     * @param int[] $expectedJobUids
     */
    #[Test]
    #[DataProvider('relationDataProvider')]
    public function findBySearchByTranslatedRelationReturnsSameJobsWithFallbackTypeFallback(
        string $className,
        int $uid,
        array $expectedJobUids,
    ): void {
        $subject = $this->createSubject(1);

        $jobs = $subject->findBySearch($this->createSearch($className, $uid));

        self::assertSame($expectedJobUids, array_keys($this->getTitles($jobs)));
    }

    #[Test]
    public function findBySearchByTranslatedJobRoleReturnsTranslatedJobs(): void
    {
        $subject = $this->createSubject(1);

        self::assertSame(
            [
                1 => 'Fachkraft in Vollzeit in der IT',
                3 => 'Part-time specialist in IT without translation',
            ],
            $this->getTitles($subject->findBySearch($this->createSearch(JobRole::class, 1))),
        );
    }

    #[Test]
    public function findBySearchByJobRoleTypeAndAreaCombinedReturnsSameJobsInLanguage1(): void
    {
        $subject = $this->createSubject(1);
        $search = new Search(
            $this->getObject(1, JobArea::class),
            $this->getObject(1, JobRole::class),
            $this->getObject(2, JobType::class),
            '',
            '',
        );

        self::assertSame([3], array_keys($this->getTitles($subject->findBySearch($search))));
    }

    #[Test]
    public function findBySettingsWithJobAreaOfPluginReturnsSameJobsInLanguage1(): void
    {
        self::assertSame([2], array_keys($this->getTitles($this->createSubject(1)->findBySettings(['jobAreas' => '2']))));
    }
}
