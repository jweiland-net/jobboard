<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Middleware;

use JWeiland\Jobboard\Database\Query\JobLanguageConstraint;
use JWeiland\Jobboard\Database\Query\Restriction\OpenJobRestriction;
use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use JWeiland\Jobboard\Domain\Model\SalaryRequirement;
use JWeiland\Jobboard\Service\RememberedJobsServiceInterface;
use JWeiland\Jobboard\Service\StoragePidResolverInterface;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Remembers a job for the current visitor or forgets it again. Answers POST
 * requests with the header "jobboard-remember-job" and the form fields "job"
 * (job UID of the default language) and "remember" ("1" or "0") with the
 * UIDs of all remembered jobs as JSON: {"rememberedJobs": [12, 7]}.
 *
 * A POST request with the header and an empty body only reads: it answers
 * the remembered jobs the same way, but never creates or writes a session.
 * The cached job list uses it to check the remembered jobs by JavaScript.
 *
 * Only jobs that exist and are listed in the pages of the site setting
 * "jobboard.storagePid" (see StoragePidResolver) in the site language of the
 * request can be remembered. The jobs
 * are stored in the session of the TYPO3 frontend user, see
 * RememberedJobsService. Does not use Extbase.
 */
#[Autoconfigure(public: true)]
final readonly class RememberJobMiddleware implements MiddlewareInterface
{
    use ConnectionPoolTrait;

    public const HEADER_NAME = 'jobboard-remember-job';

    private const TABLE = 'tx_jobboard_domain_model_job';

    public function __construct(
        private RememberedJobsServiceInterface $rememberedJobsService,
        private StoragePidResolverInterface $storagePidResolver,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader(self::HEADER_NAME)) {
            return $handler->handle($request);
        }

        // Reject before the session is read: no remembered jobs for other methods
        if ($request->getMethod() !== 'POST') {
            return (new JsonResponse(['error' => 'methodNotAllowed'], 405))
                ->withHeader('Allow', 'POST')
                ->withHeader('Cache-Control', 'no-store');
        }

        // @todo TYPO3 14: limit requests per IP with the native
        //       RateLimiterFactoryInterface::createRequestBasedLimiter()
        $rememberedJobs = $this->rememberedJobsService->getRememberedJobs($request);
        if ($this->getParsedBody($request) === []) {
            // Never writes the session, but answers the listed jobs only, so
            // the count badge matches the remembered jobs view
            return $this->createResponse($this->getVisibleRememberedJobs($request, $rememberedJobs));
        }

        $jobUid = $this->getJobUidFromRequest($request);
        $remember = $this->getRememberFromRequest($request);
        if ($jobUid === 0 || $remember === null) {
            return $this->createResponse($rememberedJobs, 400, 'invalidArguments');
        }

        return $remember
            ? $this->rememberJob($request, $rememberedJobs, $jobUid)
            : $this->storeAndRespond(
                $request,
                $rememberedJobs,
                $this->getVisibleRememberedJobs($request, $rememberedJobs->without($jobUid)),
            );
    }

    /**
     * Without jobs, which are not listed anymore (e.g. the application
     * deadline has passed). Same visibility as the remembered jobs view.
     */
    private function getVisibleRememberedJobs(ServerRequestInterface $request, RememberedJobs $rememberedJobs): RememberedJobs
    {
        $uids = $rememberedJobs->getUids();

        return $uids === [] ? $rememberedJobs : $rememberedJobs->intersect($this->getVisibleJobUids($request, $uids));
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

        // Data of the current visitor: must never be stored by any cache
        return (new JsonResponse($data, $status))->withHeader('Cache-Control', 'no-store');
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
     * Returns the given job UIDs (default language), whose jobs are listed in
     * the site language of the request: the same jobs as
     * JobRepository::findByUids() returns for the remembered jobs view. The
     * job rows are selected with the language statement of Extbase
     * (JobLanguageConstraint) in the storage pages of the current site.
     * Hidden, deleted, scheduled and access-restricted jobs are excluded by
     * the frontend restrictions of the QueryBuilder, expired jobs and jobs
     * without salary information (if required by the site setting
     * "jobboard.salary.required", see SalaryRequirement) by
     * OpenJobRestriction - both checked on the selected row, i.e. on the
     * translation, like Extbase does.
     *
     * @param int[] $jobUids
     * @return int[]
     */
    private function getVisibleJobUids(ServerRequestInterface $request, array $jobUids): array
    {
        $storagePids = $this->storagePidResolver->getStoragePids($request);
        if ($storagePids === []) {
            return [];
        }

        $languageConstraint = JobLanguageConstraint::fromRequest($request, $this->tcaSchemaFactory->get(self::TABLE));
        $rows = $this->buildVisibleJobsQuery($request, $languageConstraint, $jobUids, $storagePids)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_unique(array_map($languageConstraint->getDefaultLanguageUid(...), $rows)));
    }

    /**
     * @param int[] $jobUids
     * @param int[] $storagePids
     */
    private function buildVisibleJobsQuery(
        ServerRequestInterface $request,
        JobLanguageConstraint $languageConstraint,
        array $jobUids,
        array $storagePids,
    ): QueryBuilder {
        $parentField = $languageConstraint->getTranslationParentField();
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->add(
            OpenJobRestriction::forToday(SalaryRequirement::fromRequest($request)->isRequired()),
        );

        return $queryBuilder
            ->select('uid', $parentField)
            ->from(self::TABLE)
            ->where(
                $this->buildUidConstraint($queryBuilder, $parentField, $jobUids),
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY),
                ),
                $languageConstraint->buildExpression($queryBuilder),
            );
    }

    /**
     * The default record (uid) or its translations (translation parent field,
     * TCA ctrl "transOrigPointerField"), like JobRepository::findByUids().
     * The language constraint selects one row per job at most.
     *
     * @param int[] $jobUids
     */
    private function buildUidConstraint(QueryBuilder $queryBuilder, string $parentField, array $jobUids): CompositeExpression
    {
        $jobUidsParameter = $queryBuilder->createNamedParameter($jobUids, Connection::PARAM_INT_ARRAY);

        return $queryBuilder->expr()->or(
            $queryBuilder->expr()->in('uid', $jobUidsParameter),
            $queryBuilder->expr()->in($parentField, $jobUidsParameter),
        );
    }
}
