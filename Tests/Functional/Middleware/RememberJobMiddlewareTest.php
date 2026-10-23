<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Middleware;

use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use JWeiland\Jobboard\Middleware\RememberJobMiddleware;
use JWeiland\Jobboard\Service\RememberedJobsServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * The first tests send real frontend requests through the whole middleware
 * stack, so the jobs are stored in the session of the TYPO3 frontend user and
 * the session cookie is sent back. The other tests call the middleware
 * directly with an in-memory RememberedJobsServiceInterface.
 */
final class RememberJobMiddlewareTest extends FunctionalTestCase
{
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/RememberJobMiddleware.csv');

        $siteWriter = $this->get(SiteWriter::class);
        $siteWriter->write('main', [
            'rootPageId' => 1,
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'navigationTitle' => 'English',
                    'base' => '/',
                    'locale' => 'en_US.UTF-8',
                    'flag' => 'global',
                ],
            ],
        ]);
        $siteWriter->writeSettings('main', ['jobboard' => ['storagePid' => '2']]);
    }

    /**
     * @param array<string, mixed> $parsedBody
     */
    private function sendFrontendRequest(array $parsedBody, string $sessionCookie = ''): ResponseInterface
    {
        $request = (new InternalRequest('https://example.com/'))
            ->withMethod('POST')
            ->withHeader(RememberJobMiddleware::HEADER_NAME, '1')
            ->withParsedBody($parsedBody);

        if ($sessionCookie !== '') {
            $request = $request->withCookieParams(['fe_typo_user' => $sessionCookie]);
        }

        return $this->executeFrontendSubRequest($request);
    }

    private function getSessionCookie(ResponseInterface $response): string
    {
        preg_match('/^fe_typo_user=([^;]*)/', $response->getHeaderLine('Set-Cookie'), $matches);

        return $matches[1] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
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

        return [new RememberJobMiddleware($rememberedJobsService), $rememberedJobsService];
    }

    /**
     * @param array<string, mixed>|null $parsedBody
     */
    private function buildRequest(?array $parsedBody, string $method = 'POST'): ServerRequest
    {
        $site = new Site('main', 1, ['settings' => ['jobboard' => ['storagePid' => '2']]]);

        return (new ServerRequest('https://example.com/', $method))
            ->withHeader(RememberJobMiddleware::HEADER_NAME, '1')
            ->withParsedBody($parsedBody)
            ->withAttribute('site', $site);
    }

    private function createFailingHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('The middleware must answer the request itself.', 1761210101);
            }
        };
    }

    #[Test]
    public function rememberJobStoresJobInSessionAndSetsSessionCookie(): void
    {
        $response = $this->sendFrontendRequest(['job' => '1', 'remember' => '1']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['rememberedJobs' => [1]], $this->decode($response));
        self::assertNotSame('', $this->getSessionCookie($response));
    }

    #[Test]
    public function rememberJobKeepsJobsRememberedInPreviousRequests(): void
    {
        $sessionCookie = $this->getSessionCookie($this->sendFrontendRequest(['job' => '1', 'remember' => '1']));

        $response = $this->sendFrontendRequest(['job' => '3', 'remember' => '1'], $sessionCookie);

        self::assertSame(['rememberedJobs' => [1, 3]], $this->decode($response));
    }

    #[Test]
    public function forgetJobRemovesJobFromSession(): void
    {
        $sessionCookie = $this->getSessionCookie($this->sendFrontendRequest(['job' => '1', 'remember' => '1']));
        $this->sendFrontendRequest(['job' => '2', 'remember' => '1'], $sessionCookie);

        $this->sendFrontendRequest(['job' => '1', 'remember' => '0'], $sessionCookie);
        $response = $this->sendFrontendRequest(['job' => '1', 'remember' => '0'], $sessionCookie);

        self::assertSame(['rememberedJobs' => [2]], $this->decode($response));
    }

    #[Test]
    public function rejectedRequestDoesNotCreateSession(): void
    {
        $response = $this->sendFrontendRequest(['job' => '4', 'remember' => '1']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', $this->getSessionCookie($response));
    }

    #[Test]
    public function requestWithoutHeaderIsPassedToNextMiddleware(): void
    {
        [$subject] = $this->createSubject();
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(null, 204);
            }
        };

        $response = $subject->process(new ServerRequest('https://example.com/', 'POST'), $handler);

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function getRequestIsRejected(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2]);

        $response = $subject->process($this->buildRequest(null, 'GET'), $this->createFailingHandler());

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->getHeaderLine('Allow'));
        self::assertSame(['rememberedJobs' => [2], 'error' => 'methodNotAllowed'], $this->decode($response));
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    public static function invalidArgumentsDataProvider(): array
    {
        return [
            'missing job' => [['remember' => '1']],
            'job is not a number' => [['job' => 'abc', 'remember' => '1']],
            'job is a float' => [['job' => '1.5', 'remember' => '1']],
            'job is negative' => [['job' => '-1', 'remember' => '1']],
            'job is zero' => [['job' => '0', 'remember' => '1']],
            'job with leading zero' => [['job' => '01', 'remember' => '1']],
            'job with SQL' => [['job' => '1 OR 1=1', 'remember' => '1']],
            'job is an array' => [['job' => ['1'], 'remember' => '1']],
            'job is too long' => [['job' => '12345678901', 'remember' => '1']],
            'missing remember' => [['job' => '1']],
            'remember is not 0 or 1' => [['job' => '1', 'remember' => 'yes']],
            'remember is an array' => [['job' => '1', 'remember' => ['1']]],
        ];
    }

    /**
     * @param array<string, mixed> $parsedBody
     */
    #[Test]
    #[DataProvider('invalidArgumentsDataProvider')]
    public function invalidArgumentsAreRejected(array $parsedBody): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2]);

        $response = $subject->process($this->buildRequest($parsedBody), $this->createFailingHandler());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['rememberedJobs' => [2], 'error' => 'invalidArguments'], $this->decode($response));
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    public static function unknownJobDataProvider(): array
    {
        return [
            'job does not exist' => ['999'],
            'job is hidden' => ['4'],
            'job is deleted' => ['5'],
            'job is outside of the storage pages' => ['6'],
            'UID of a translation' => ['7'],
        ];
    }

    #[Test]
    #[DataProvider('unknownJobDataProvider')]
    public function unknownJobIsRejected(string $jobUid): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([2]);

        $response = $subject->process(
            $this->buildRequest(['job' => $jobUid, 'remember' => '1']),
            $this->createFailingHandler(),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['rememberedJobs' => [2], 'error' => 'unknownJob'], $this->decode($response));
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    #[Test]
    public function jobInSubFolderOfStorageCanBeRemembered(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject();

        $response = $subject->process(
            $this->buildRequest(['job' => '3', 'remember' => '1']),
            $this->createFailingHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([3], $rememberedJobsService->rememberedJobs->getUids());
    }

    #[Test]
    public function unknownJobIsRejectedWithoutSite(): void
    {
        [$subject] = $this->createSubject();
        $request = $this->buildRequest(['job' => '1', 'remember' => '1'])->withoutAttribute('site');

        $response = $subject->process($request, $this->createFailingHandler());

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function rememberJobRemovesJobsWhichAreNotVisibleAnymore(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([4, 2, 999]);

        $response = $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '1']),
            $this->createFailingHandler(),
        );

        self::assertSame(['rememberedJobs' => [2, 1]], $this->decode($response));
        self::assertSame([2, 1], $rememberedJobsService->rememberedJobs->getUids());
    }

    #[Test]
    public function rememberJobTwiceDoesNotWriteSession(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([1]);

        $response = $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '1']),
            $this->createFailingHandler(),
        );

        self::assertSame(['rememberedJobs' => [1]], $this->decode($response));
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    #[Test]
    public function forgetJobWhichIsNotRememberedDoesNotWriteSession(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([1]);

        $response = $subject->process(
            $this->buildRequest(['job' => '999', 'remember' => '0']),
            $this->createFailingHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['rememberedJobs' => [1]], $this->decode($response));
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    #[Test]
    public function forgetJobWhichIsNotVisibleAnymoreRemovesIt(): void
    {
        [$subject, $rememberedJobsService] = $this->createSubject([4, 1]);

        $subject->process($this->buildRequest(['job' => '4', 'remember' => '0']), $this->createFailingHandler());

        self::assertSame([1], $rememberedJobsService->rememberedJobs->getUids());
    }

    #[Test]
    public function rememberJobIsRejectedIfMaximumIsReached(): void
    {
        $jobUids = $this->insertVisibleJobs(RememberedJobs::MAXIMUM, 100);
        [$subject, $rememberedJobsService] = $this->createSubject($jobUids);

        $response = $subject->process(
            $this->buildRequest(['job' => '1', 'remember' => '1']),
            $this->createFailingHandler(),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('limitReached', $this->decode($response)['error']);
        self::assertSame(0, $rememberedJobsService->numberOfStoreCalls);
    }

    /**
     * @return int[]
     */
    private function insertVisibleJobs(int $numberOfJobs, int $firstUid): array
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_jobboard_domain_model_job');
        $jobUids = range($firstUid, $firstUid + $numberOfJobs - 1);
        foreach ($jobUids as $jobUid) {
            $connection->insert('tx_jobboard_domain_model_job', ['uid' => $jobUid, 'pid' => 2, 'title' => 'Job ' . $jobUid]);
        }

        return $jobUids;
    }
}
