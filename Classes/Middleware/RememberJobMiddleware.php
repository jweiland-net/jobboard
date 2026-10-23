<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Middleware;

use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use JWeiland\Jobboard\Service\RememberedJobsServiceInterface;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Remembers a job for the current visitor or forgets it again. Answers POST
 * requests with the header "jobboard-remember-job" and the form fields "job"
 * (job UID of the default language) and "remember" ("1" or "0") with the
 * UIDs of all remembered jobs as JSON: {"rememberedJobs": [12, 7]}.
 *
 * Only jobs that exist and are visible in the pages of the site setting
 * "jobboard.storagePid" can be remembered. The jobs are stored in the session
 * of the TYPO3 frontend user, see RememberedJobsService. Does not use Extbase.
 */
#[Autoconfigure(public: true)]
final readonly class RememberJobMiddleware implements MiddlewareInterface
{
    use ConnectionPoolTrait;

    public const HEADER_NAME = 'jobboard-remember-job';

    private const TABLE = 'tx_jobboard_domain_model_job';

    /**
     * Same depth as "plugin.tx_jobboard.persistence.recursive" of the site set
     */
    private const STORAGE_DEPTH = 3;

    public function __construct(
        private RememberedJobsServiceInterface $rememberedJobsService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader(self::HEADER_NAME)) {
            return $handler->handle($request);
        }

        // Reject before the session is read: no remembered jobs for other methods
        if ($request->getMethod() !== 'POST') {
            return (new JsonResponse(['error' => 'methodNotAllowed'], 405))->withHeader('Allow', 'POST');
        }

        // @todo TYPO3 14: limit requests per IP with the native
        //       RateLimiterFactoryInterface::createRequestBasedLimiter()
        $rememberedJobs = $this->rememberedJobsService->getRememberedJobs($request);
        $jobUid = $this->getJobUidFromRequest($request);
        $remember = $this->getRememberFromRequest($request);
        if ($jobUid === 0 || $remember === null) {
            return $this->createResponse($rememberedJobs, 400, 'invalidArguments');
        }

        return $remember
            ? $this->rememberJob($request, $rememberedJobs, $jobUid)
            : $this->storeAndRespond($request, $rememberedJobs, $rememberedJobs->without($jobUid));
    }

    /**
     * Removes remembered jobs, which are not visible anymore, so they do not
     * count against the maximum number of remembered jobs.
     */
    private function rememberJob(
        ServerRequestInterface $request,
        RememberedJobs $rememberedJobs,
        int $jobUid,
    ): ResponseInterface {
        $visibleJobUids = $this->getVisibleJobUids($request, [...$rememberedJobs->getUids(), $jobUid]);
        if (!in_array($jobUid, $visibleJobUids, true)) {
            return $this->createResponse($rememberedJobs, 404, 'unknownJob');
        }

        $visibleRememberedJobs = $rememberedJobs->intersect($visibleJobUids);
        if ($visibleRememberedJobs->isFull() && !$visibleRememberedJobs->contains($jobUid)) {
            return $this->createResponse($rememberedJobs, 409, 'limitReached');
        }

        return $this->storeAndRespond($request, $rememberedJobs, $visibleRememberedJobs->with($jobUid));
    }

    /**
     * Writes the session only if something has changed. This way, a request
     * that changes nothing never creates a session for an anonymous visitor.
     */
    private function storeAndRespond(
        ServerRequestInterface $request,
        RememberedJobs $currentRememberedJobs,
        RememberedJobs $newRememberedJobs,
    ): ResponseInterface {
        if (!$newRememberedJobs->equals($currentRememberedJobs)) {
            $this->rememberedJobsService->storeRememberedJobs($request, $newRememberedJobs);
        }

        return $this->createResponse($newRememberedJobs);
    }

    private function createResponse(RememberedJobs $rememberedJobs, int $status = 200, string $error = ''): JsonResponse
    {
        $data = ['rememberedJobs' => $rememberedJobs->getUids()];
        if ($error !== '') {
            $data['error'] = $error;
        }

        return new JsonResponse($data, $status);
    }

    /**
     * "job" comes straight from the request body of an anonymous frontend
     * request. Only a string of digits is accepted, nothing else is cast.
     * Returns 0 for an invalid value.
     */
    private function getJobUidFromRequest(ServerRequestInterface $request): int
    {
        $jobUid = $this->getParsedBody($request)['job'] ?? '';
        if (!is_string($jobUid) || preg_match('/^[1-9]\d{0,9}$/', $jobUid) !== 1) {
            return 0;
        }

        return (int)$jobUid;
    }

    /**
     * Returns null, if "remember" is neither "1" nor "0".
     */
    private function getRememberFromRequest(ServerRequestInterface $request): ?bool
    {
        return match ($this->getParsedBody($request)['remember'] ?? null) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * @return array<mixed>
     */
    private function getParsedBody(ServerRequestInterface $request): array
    {
        $parsedBody = $request->getParsedBody();

        return is_array($parsedBody) ? $parsedBody : [];
    }

    /**
     * Returns the given job UIDs, which belong to visible jobs of the default
     * language in the storage pages of the current site. Hidden, deleted,
     * scheduled and access-restricted jobs are excluded by the frontend
     * restrictions of the QueryBuilder.
     *
     * @param int[] $jobUids
     * @return int[]
     */
    private function getVisibleJobUids(ServerRequestInterface $request, array $jobUids): array
    {
        $storagePids = $this->getStoragePids($request);
        if ($storagePids === []) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($jobUids, Connection::PARAM_INT_ARRAY),
                ),
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY),
                ),
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter([0, -1], Connection::PARAM_INT_ARRAY),
                ),
            );

        return array_map(intval(...), $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * Reads "jobboard.storagePid" from the Site Settings instead of accepting it
     * from the request, so the pages of remembered jobs can not be widened by a
     * manipulated request. Includes sub pages like the plugin does.
     *
     * @return int[]
     */
    private function getStoragePids(ServerRequestInterface $request): array
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return [];
        }

        $storagePids = array_filter(
            GeneralUtility::intExplode(',', (string)$site->getSettings()->get('jobboard.storagePid', '0'), true),
            static fn(int $storagePid): bool => $storagePid > 0,
        );

        return GeneralUtility::makeInstance(PageRepository::class)
            ->getPageIdsRecursive(array_values($storagePids), self::STORAGE_DEPTH);
    }
}
