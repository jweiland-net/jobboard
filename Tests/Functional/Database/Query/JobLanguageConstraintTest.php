<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Database\Query;

use JWeiland\Jobboard\Database\Query\JobLanguageConstraint;
use JWeiland\Jobboard\Database\Query\Restriction\OpenJobRestriction;
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\Tests\Functional\Traits\RemoveTcaCapabilityTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * JobLanguageConstraint must select the same job rows as the language
 * statement of Extbase (Typo3DbQueryParser::getLanguageStatement()), which
 * JobRepository uses. Fixture JobLanguageConstraint.csv: job 1 translated
 * (11), 2 untranslated, 3 hidden with visible translation 13, 4 with hidden
 * translation 14, floating translation 15 (no default record), 6 in all
 * languages, 7 scheduled with visible translation 17, 8 translated into
 * language 2 only (28), 9 with scheduled translation 19.
 */
final class JobLanguageConstraintTest extends FunctionalTestCase
{
    use RemoveTcaCapabilityTrait;

    private const TABLE = 'tx_jobboard_domain_model_job';

    private const STORAGE_PAGE = 2;

    protected array $coreExtensionsToLoad = [
        'typo3/cms-core',
        'typo3/cms-frontend',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/JobLanguageConstraint.csv');
    }

    protected function tearDown(): void
    {
        $this->restoreTca();

        parent::tearDown();
    }

    /**
     * Same configuration as a site config.yaml
     */
    private function createSite(string $fallbackType): Site
    {
        return new Site('main', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'base' => '/',
                    'locale' => 'en_US.UTF-8',
                ],
                [
                    'languageId' => 1,
                    'title' => 'Deutsch',
                    'base' => '/de/',
                    'locale' => 'de_DE.UTF-8',
                    'fallbackType' => $fallbackType,
                    'fallbacks' => '0',
                ],
            ],
        ]);
    }

    private function getSiteLanguage(int $languageId, string $fallbackType = 'fallback'): SiteLanguage
    {
        return $this->createSite($fallbackType)->getLanguageById($languageId);
    }

    private function getJobSchema(): TcaSchema
    {
        return $this->get(TcaSchemaFactory::class)->get(self::TABLE);
    }

    /**
     * @return int[] sorted default-language UIDs of the selected rows
     */
    private function selectWithConstraint(JobLanguageConstraint $subject): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        $queryBuilder->getRestrictions()->add(OpenJobRestriction::forToday());
        $rows = $queryBuilder
            ->select('uid', ...array_filter([$subject->getTranslationParentField()]))
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(self::STORAGE_PAGE, Connection::PARAM_INT),
                ),
                $subject->buildExpression($queryBuilder),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $uids = array_map($subject->getDefaultLanguageUid(...), $rows);
        sort($uids);

        return $uids;
    }

    /**
     * @return int[] sorted UIDs of the job list of Extbase
     */
    private function selectWithExtbase(LanguageAspect $languageAspect): array
    {
        $this->get(Context::class)->setAspect('language', $languageAspect);

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);
        $querySettings->setLanguageAspect($languageAspect);

        $repository = $this->get(JobRepository::class);
        $repository->setDefaultQuerySettings($querySettings);

        $uids = array_map(
            static fn(Job $job): int => (int)$job->getUid(),
            $repository->findBySettings([])->toArray(),
        );
        sort($uids);

        return $uids;
    }

    /**
     * @return array<string, array{int, string, int[]}>
     */
    public static function siteLanguageDataProvider(): array
    {
        return [
            'language 0, fallback type "fallback"' => [0, 'fallback', [1, 2, 4, 6, 8, 9]],
            'language 0, fallback type "strict"' => [0, 'strict', [1, 2, 4, 6, 8, 9]],
            'language 0, fallback type "free"' => [0, 'free', [1, 2, 4, 6, 8, 9]],
            // Translation 11, untranslated defaults 2, 4 (hidden translation),
            // 8 (language 2 only), 9 (scheduled translation)
            'language 1, fallback type "fallback"' => [1, 'fallback', [1, 2, 4, 6, 8, 9]],
            // Translation 11 and floating translation 15
            'language 1, fallback type "strict"' => [1, 'strict', [1, 6, 15]],
            // Visible rows of language 1, also of hidden/scheduled defaults
            'language 1, fallback type "free"' => [1, 'free', [1, 3, 6, 7, 15]],
        ];
    }

    /**
     * @param int[] $expectedUids
     */
    #[Test]
    #[DataProvider('siteLanguageDataProvider')]
    public function buildExpressionSelectsExpectedJobs(int $languageId, string $fallbackType, array $expectedUids): void
    {
        $subject = new JobLanguageConstraint(
            LanguageAspectFactory::createFromSiteLanguage($this->getSiteLanguage($languageId, $fallbackType)),
            $this->getJobSchema(),
        );

        self::assertSame($expectedUids, $this->selectWithConstraint($subject));
    }

    /**
     * The expected UIDs of the data provider are checked by
     * buildExpressionSelectsExpectedJobs already.
     *
     * @param int[] $expectedUids
     */
    #[Test]
    #[DataProvider('siteLanguageDataProvider')]
    public function buildExpressionSelectsSameJobsAsExtbase(
        int $languageId,
        string $fallbackType,
        array $expectedUids,
    ): void {
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage(
            $this->getSiteLanguage($languageId, $fallbackType),
        );

        self::assertSame(
            $this->selectWithExtbase($languageAspect),
            $this->selectWithConstraint(new JobLanguageConstraint($languageAspect, $this->getJobSchema())),
        );
    }

    /**
     * Overlay types, which no fallback type of a site creates
     *
     * @return array<string, array{string}>
     */
    public static function overlayTypeDataProvider(): array
    {
        return [
            'OVERLAYS_ON' => [LanguageAspect::OVERLAYS_ON],
            'OVERLAYS_MIXED' => [LanguageAspect::OVERLAYS_MIXED],
            'OVERLAYS_ON_WITH_FLOATING' => [LanguageAspect::OVERLAYS_ON_WITH_FLOATING],
            'OVERLAYS_OFF' => [LanguageAspect::OVERLAYS_OFF],
        ];
    }

    #[Test]
    #[DataProvider('overlayTypeDataProvider')]
    public function buildExpressionSelectsSameJobsAsExtbaseForOverlayType(string $overlayType): void
    {
        $languageAspect = new LanguageAspect(1, 1, $overlayType, [0]);

        self::assertSame(
            $this->selectWithExtbase($languageAspect),
            $this->selectWithConstraint(new JobLanguageConstraint($languageAspect, $this->getJobSchema())),
        );
    }

    #[Test]
    public function buildExpressionWithOverlaysOnSelectsTranslationsWithVisibleDefaultRecordOnly(): void
    {
        $subject = new JobLanguageConstraint(
            new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_ON, [0]),
            $this->getJobSchema(),
        );

        self::assertSame([1, 6], $this->selectWithConstraint($subject));
    }

    #[Test]
    public function buildExpressionInLanguage2SelectsItsTranslationAndUntranslatedDefaults(): void
    {
        $subject = new JobLanguageConstraint(
            new LanguageAspect(2, 2, LanguageAspect::OVERLAYS_MIXED, [0]),
            $this->getJobSchema(),
        );

        // Job 13 is no translation of language 2: job 3 stays hidden
        self::assertSame([1, 2, 4, 6, 8, 9], $this->selectWithConstraint($subject));
    }

    #[Test]
    public function fromRequestUsesSiteLanguageOfRequest(): void
    {
        $request = (new ServerRequest('https://example.com/de/', 'POST'))
            ->withAttribute('language', $this->getSiteLanguage(1, 'strict'));

        $subject = JobLanguageConstraint::fromRequest($request, $this->getJobSchema());

        self::assertSame([1, 6, 15], $this->selectWithConstraint($subject));
    }

    #[Test]
    public function fromRequestWithoutSiteLanguageUsesDefaultLanguage(): void
    {
        $request = new ServerRequest('https://example.com/de/', 'POST');

        $subject = JobLanguageConstraint::fromRequest($request, $this->getJobSchema());

        self::assertSame([1, 2, 4, 6, 8, 9], $this->selectWithConstraint($subject));
    }

    #[Test]
    public function fromRequestWithInvalidLanguageAttributeUsesDefaultLanguage(): void
    {
        $request = (new ServerRequest('https://example.com/de/', 'POST'))->withAttribute('language', 1);

        $subject = JobLanguageConstraint::fromRequest($request, $this->getJobSchema());

        self::assertSame([1, 2, 4, 6, 8, 9], $this->selectWithConstraint($subject));
    }

    #[Test]
    public function buildExpressionSelectsAtMostOneRowPerJob(): void
    {
        foreach (['fallback', 'strict', 'free'] as $fallbackType) {
            $uids = $this->selectWithConstraint(new JobLanguageConstraint(
                LanguageAspectFactory::createFromSiteLanguage($this->getSiteLanguage(1, $fallbackType)),
                $this->getJobSchema(),
            ));

            self::assertSame(array_values(array_unique($uids)), $uids, 'Fallback type ' . $fallbackType);
        }
    }

    /**
     * Without language capability (like Extbase without "languageField"),
     * there is no language constraint: every visible row is selected and
     * identified by its own UID
     */
    #[Test]
    public function buildExpressionWithoutLanguageCapabilitySelectsAllRows(): void
    {
        $this->removeLanguageCapability(self::TABLE);
        $subject = new JobLanguageConstraint(
            new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_MIXED, [0]),
            $this->getJobSchema(),
        );

        self::assertNull($subject->getTranslationParentField());
        self::assertSame([1, 2, 4, 6, 8, 9, 11, 13, 15, 17, 28], $this->selectWithConstraint($subject));
    }
}
