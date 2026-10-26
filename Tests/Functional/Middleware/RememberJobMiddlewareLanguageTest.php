<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Middleware;

use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\Middleware\RememberJobMiddleware;
use JWeiland\Jobboard\Service\RememberedJobsServiceInterface;
use JWeiland\Jobboard\Service\StoragePidResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * RememberJobMiddleware in the site language of the request: the jobs it
 * answers, keeps and accepts must be exactly the jobs, which
 * JobRepository::findByUids() returns for the remembered jobs view in the
 * same language. Fixture JobLanguageConstraint.csv: job 1 translated (11),
 * 2 untranslated, 3 hidden with visible translation 13, 4 with hidden
 * translation 14, floating translation 15 (no default record), 6 in all
 * languages, 7 scheduled with visible translation 17, 8 translated into
 * language 2 only (28), 9 with scheduled translation 19.
 */
final class RememberJobMiddlewareLanguageTest extends FunctionalTestCase
{
    /**
     * Every UID of the fixture, also of translations, plus an unknown one
     */
    private const CANDIDATE_UIDS = [1, 2, 3, 4, 6, 7, 8, 9, 11, 13, 14, 15, 17, 19, 28, 999];

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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobLanguageConstraint.csv');
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function languageDataProvider(): array
    {
        return [
            'language 0, fallback type "fallback"' => [0, 'fallback'],
            'language 1, fallback type "fallback"' => [1, 'fallback'],
            'language 1, fallback type "strict"' => [1, 'strict'],
            'language 1, fallback type "free"' => [1, 'free'],
        ];
    }

    /**
     * @param int[] $rememberedJobUids
     * @return array{RememberJobMiddleware, RememberedJobsServiceInterface}
     */
    private function createSubject(array $rememberedJobUids = []): array
    {
        $rememberedJobs = RememberedJobs::fromSessionData($rememberedJobUids);
        $rememberedJobsService = new class ($rememberedJobs) implements RememberedJobsServiceInterface {
            public int $numberOfStoreCalls = 0;

            public function __construct(public RememberedJobs $rememberedJobs) {}

            public function getRememberedJobs(ServerRequestInterface $request): RememberedJobs
            {
                return $this->rememberedJobs;
            }

            public function storeRememberedJobs(ServerRequestInterface $request, RememberedJobs $rememberedJobs): void
            {
                $this->rememberedJobs = $rememberedJobs;
                $this->numberOfStoreCalls++;
            }
        };

        $subject = new RememberJobMiddleware(
            $rememberedJobsService,
            new StoragePidResolver(),
            $this->get(TcaSchemaFactory::class),
        );

        return [$subject, $rememberedJobsService];
    }

    private function createSite(string $fallbackType): Site
    {
        return new Site('main', 1, [
            'base' => 'https://example.com/',
            'settings' => ['jobboard' => ['storagePid' => (string)self::STORAGE_PAGE]],
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

    /**
     * @param array<string, mixed> $parsedBody
     */
    private function buildRequest(array $parsedBody, int $languageId, string $fallbackType): ServerRequest
    {
        $site = $this->createSite($fallbackType);

        return (new ServerRequest('https://example.com/', 'POST'))
            ->withHeader(RememberJobMiddleware::HEADER_NAME, '1')
            ->withParsedBody($parsedBody)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getLanguageById($languageId));
    }

    private function createFailingHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('The middleware must answer the request itself.', 1761310101);
            }
        };
    }

    /**
     * @return int[]
     */
    private function decodeRememberedJobs(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, \JSON_THROW_ON_ERROR)['rememberedJobs'];
    }

    /**
     * The remembered jobs view: JobRepository::findByUids() with the language
     * of the site language, like an Extbase frontend request
     *
     * @param int[] $jobUids
     * @return int[] sorted UIDs of the returned jobs
     */
    private function findByUids(array $jobUids, int $languageId, string $fallbackType): array
    {
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage(
            $this->createSite($fallbackType)->getLanguageById($languageId),
        );
        $this->get(Context::class)->setAspect('language', $languageAspect);

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([self::STORAGE_PAGE]);
        $querySettings->setLanguageAspect($languageAspect);

        $repository = $this->get(JobRepository::class);
        $repository->setDefaultQuerySettings($querySettings);

        $uids = array_map(
            static fn(Job $job): int => (int)$job->getUid(),
            $repository->findByUids($jobUids)->toArray(),
        );
        sort($uids);

        return $uids;
    }

    /**
     * @param int[] $uids
     * @return int[]
     */
    private function sorted(array $uids): array
    {
        sort($uids);

        return $uids;
    }

    #[Test]
    #[DataProvider('languageDataProvider')]
    public function readOnlyRequestAnswersJobsOfFindByUids(int $languageId, string $fallbackType): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject(self::CANDIDATE_UIDS);

        $response = $subject->process(
            $this->buildRequest([], $languageId, $fallbackType),
            $this->createFailingHandler(),
        );

        self::assertSame(
            $this->findByUids(self::CANDIDATE_UIDS, $languageId, $fallbackType),
            $this->sorted($this->decodeRememberedJobs($response)),
        );
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    #[Test]
    #[DataProvider('languageDataProvider')]
    public function forgetJobStoresJobsOfFindByUids(int $languageId, string $fallbackType): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject(self::CANDIDATE_UIDS);

        $response = $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '0'], $languageId, $fallbackType),
            $this->createFailingHandler(),
        );
        $expectedUids = array_values(array_diff(
            $this->findByUids(self::CANDIDATE_UIDS, $languageId, $fallbackType),
            [1],
        ));

        self::assertSame($expectedUids, $this->sorted($this->decodeRememberedJobs($response)));
        self::assertSame($expectedUids, $this->sorted($rememberedJobsService->rememberedJobs->getUids()));
    }

    #[Test]
    #[DataProvider('languageDataProvider')]
    public function onlyJobsOfFindByUidsCanBeRemembered(int $languageId, string $fallbackType): void
    {
        $acceptedUids = [];
        foreach (self::CANDIDATE_UIDS as $jobUid) {
            [$subject] = $this->createSubject();
            $response = $subject->process(
                $this->buildRequest(['job' => (string)$jobUid, 'remember' => '1'], $languageId, $fallbackType),
                $this->createFailingHandler(),
            );
            if ($response->getStatusCode() === 200) {
                $acceptedUids[] = $jobUid;
            } else {
                self::assertSame(404, $response->getStatusCode(), 'Job ' . $jobUid);
            }
        }

        self::assertSame($this->findByUids(self::CANDIDATE_UIDS, $languageId, $fallbackType), $acceptedUids);
    }

    /**
     * @return array<string, array{int, string, int[]}>
     */
    public static function listedJobsDataProvider(): array
    {
        return [
            'language 0, fallback type "fallback"' => [0, 'fallback', [1, 2, 4, 6, 8, 9]],
            'language 1, fallback type "fallback"' => [1, 'fallback', [1, 2, 4, 6, 8, 9]],
            'language 1, fallback type "strict"' => [1, 'strict', [1, 6, 15]],
            'language 1, fallback type "free"' => [1, 'free', [1, 3, 6, 7, 15]],
        ];
    }

    /**
     * Guards the comparison tests above against an empty or unchanged
     * result on both sides.
     *
     * @param int[] $expectedUids
     */
    #[Test]
    #[DataProvider('listedJobsDataProvider')]
    public function readOnlyRequestAnswersJobsListedInTheLanguage(
        int $languageId,
        string $fallbackType,
        array $expectedUids,
    ): void {
        [$subject] = $this->createSubject(self::CANDIDATE_UIDS);

        $response = $subject->process(
            $this->buildRequest([], $languageId, $fallbackType),
            $this->createFailingHandler(),
        );

        self::assertSame($expectedUids, $this->sorted($this->decodeRememberedJobs($response)));
    }

    #[Test]
    public function readOnlyRequestWithoutSiteLanguageAnswersJobsOfDefaultLanguage(): void
    {
        [$subject] = $this->createSubject(self::CANDIDATE_UIDS);

        $response = $subject->process(
            $this->buildRequest([], 1, 'strict')->withoutAttribute('language'),
            $this->createFailingHandler(),
        );

        self::assertSame([1, 2, 4, 6, 8, 9], $this->sorted($this->decodeRememberedJobs($response)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fallbackTypeWithoutUntranslatedJobsDataProvider(): array
    {
        return [
            'fallback type "strict"' => ['strict'],
            'fallback type "free"' => ['free'],
        ];
    }

    /**
     * Documented trade-off: job 2 (no translation) was remembered in the
     * default language. In language 1 with "strict"/"free" it is not listed,
     * so it is not counted there, but reading keeps it in the session.
     */
    #[Test]
    #[DataProvider('fallbackTypeWithoutUntranslatedJobsDataProvider')]
    public function jobRememberedInOtherLanguageIsNotCountedButKeptByReadOnlyRequest(string $fallbackType): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2, 1]);

        $response = $subject->process($this->buildRequest([], 1, $fallbackType), $this->createFailingHandler());

        self::assertSame([1], $this->decodeRememberedJobs($response));
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
        self::assertSame([2, 1], $rememberedJobsService->rememberedJobs->getUids());
    }

    /**
     * Documented trade-off: a writing request in language 1 with
     * "strict"/"free" removes job 2, remembered in the default language,
     * from the session.
     */
    #[Test]
    #[DataProvider('fallbackTypeWithoutUntranslatedJobsDataProvider')]
    public function jobRememberedInOtherLanguageIsRemovedByRememberRequest(string $fallbackType): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2]);

        $response = $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '1'], 1, $fallbackType),
            $this->createFailingHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([1], $this->decodeRememberedJobs($response));
        self::assertSame([1], $rememberedJobsService->rememberedJobs->getUids());
    }

    #[Test]
    #[DataProvider('fallbackTypeWithoutUntranslatedJobsDataProvider')]
    public function jobRememberedInOtherLanguageIsRemovedByForgetRequest(string $fallbackType): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2, 1, 6]);

        $subject->process(
            $this->buildRequest(['job' => '6', 'remember' => '0'], 1, $fallbackType),
            $this->createFailingHandler(),
        );

        self::assertSame([1], $rememberedJobsService->rememberedJobs->getUids());
    }

    #[Test]
    public function jobRememberedInOtherLanguageIsKeptWithFallbackTypeFallback(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2]);

        $response = $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '1'], 1, 'fallback'),
            $this->createFailingHandler(),
        );

        self::assertSame([2, 1], $this->decodeRememberedJobs($response));
        self::assertSame([2, 1], $rememberedJobsService->rememberedJobs->getUids());
    }

    /**
     * Floating translation 15 is listed in language 1 with "strict" only:
     * remembered there, it is pruned by a writing request in language 0.
     */
    #[Test]
    public function floatingTranslationRememberedInLanguage1IsRemovedInDefaultLanguage(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject();
        $subject->process(
            $this->buildRequest(['job' => '15', 'remember' => '1'], 1, 'strict'),
            $this->createFailingHandler(),
        );
        self::assertSame([15], $rememberedJobsService->rememberedJobs->getUids());

        $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '1'], 0, 'strict'),
            $this->createFailingHandler(),
        );

        self::assertSame([1], $rememberedJobsService->rememberedJobs->getUids());
    }
}
