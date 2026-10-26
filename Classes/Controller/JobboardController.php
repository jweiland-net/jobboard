<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Controller;

use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\SalaryRequirement;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\Event\PostProcessFluidVariablesEvent;
use JWeiland\Jobboard\Service\JobSearchOptionsServiceInterface;
use JWeiland\Jobboard\Service\RememberedJobsServiceInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * "list" and "detail" are cached. They must not render anything of the
 * current visitor: remembered jobs are highlighted by JavaScript (see
 * RememberJobMiddleware) and listed by the uncached "rememberedJobs" action.
 */
class JobboardController extends ActionController
{
    public function __construct(
        protected JobRepository $jobRepository,
        protected RememberedJobsServiceInterface $rememberedJobsService,
        protected JobSearchOptionsServiceInterface $jobSearchOptionsService,
    ) {}

    public function listAction(): ResponseInterface
    {
        $jobs = $this->jobRepository->findBySettings($this->settings, $this->getMaxEntries(), $this->isSalaryRequired());

        $fluidVariables = ['jobs' => $jobs, 'addressUids' => $this->getAddressUidsForMap($jobs)];
        ArrayUtility::mergeRecursiveWithOverrule($fluidVariables, $this->getSearchOptions($jobs));
        $this->assignPostProcessedFluidVariables($fluidVariables);

        return $this->htmlResponse();
    }

    public function searchAction(
        ?JobArea $jobArea = null,
        ?JobRole $jobRole = null,
        ?JobType $jobType = null,
        string $address = '',
        string $searchWord = '',
    ): ResponseInterface {
        $search = new Search($this->getAllowedJobArea($jobArea), $jobRole, $jobType, $address, $searchWord);
        $jobs = $this->jobRepository->findBySearch(
            $search,
            $this->getMaxEntries(),
            $this->settings,
            $this->isSalaryRequired(),
        );

        $fluidVariables = ['jobs' => $jobs, 'addressUids' => $this->getAddressUidsForMap($jobs)];
        ArrayUtility::mergeRecursiveWithOverrule($fluidVariables, $search->getSelectedValues());
        ArrayUtility::mergeRecursiveWithOverrule($fluidVariables, $this->getSearchOptions($jobs));
        $this->assignPostProcessedFluidVariables($fluidVariables);

        return $this->htmlResponse();
    }

    /**
     * Not cacheable: lists the jobs remembered by the current visitor, which
     * are still listed in the frontend, ordered like the job list.
     * "rememberedJobs" has the UIDs as keys for the checkboxes.
     */
    public function rememberedJobsAction(): ResponseInterface
    {
        $rememberedJobs = $this->rememberedJobsService->getRememberedJobs($this->request);

        $this->view->assignMultiple([
            'jobs' => $rememberedJobs->isEmpty()
                ? []
                : $this->jobRepository->findByUids($rememberedJobs->getUids(), $this->isSalaryRequired()),
            'rememberedJobs' => $rememberedJobs->getUidsAsKeys(),
        ]);

        return $this->htmlResponse();
    }

    /**
     * Listeners of PostProcessFluidVariablesEvent can add or replace variables
     * before they are assigned, e.g. AddPaginatorEventListener paginates the
     * jobs (a QueryResultInterface) in the database. The search options are
     * built from all matching jobs, not only the jobs of the current page.
     *
     * @param array<string, mixed> $fluidVariables
     */
    private function assignPostProcessedFluidVariables(array $fluidVariables): void
    {
        $event = new PostProcessFluidVariablesEvent($this->request, $this->settings, $fluidVariables);
        $this->eventDispatcher->dispatch($event);

        $this->view->assignMultiple($event->getFluidVariables());
    }

    /**
     * Limits the query in the database. A limited list is never paginated,
     * see AddPaginatorEventListener.
     */
    private function getMaxEntries(): int
    {
        return max((int)($this->settings['maxEntries'] ?? 0), 0);
    }

    /**
     * A job area outside the job areas configured in the plugin is ignored,
     * as if no job area was selected at all.
     */
    private function getAllowedJobArea(?JobArea $jobArea): ?JobArea
    {
        if ($jobArea instanceof JobArea
            && JobAreaRestriction::fromSettings($this->settings)->allows((int)$jobArea->getUid())
        ) {
            return $jobArea;
        }

        return null;
    }

    /**
     * Aggregated in the database with the constraints of the job query.
     *
     * @param QueryResultInterface<int, Job> $jobs
     * @return array<string, array<int|string, string>>
     */
    private function getSearchOptions(QueryResultInterface $jobs): array
    {
        return $this->jobSearchOptionsService->getSearchOptions(
            $jobs->getQuery(),
            JobAreaRestriction::fromSettings($this->settings),
            $this->isJobAreaCountEnabled(),
        );
    }

    /**
     * Addresses of all matching jobs for the map in Partials/SearchForm.html,
     * independent of the page browser, which paginates "jobs" only. Built
     * from the job query in the database, no job is loaded. The map needs
     * EXT:maps2, without it no query is needed.
     *
     * @return int[]
     */
    private function getAddressUidsForMap(QueryResultInterface $jobs): array
    {
        if (!ExtensionManagementUtility::isLoaded('maps2')) {
            return [];
        }

        return $this->jobSearchOptionsService->getAddressUids($jobs->getQuery());
    }

    /**
     * Only "1" enables the count. The FlexForm uses "-1" to disable it, as an
     * empty string or "0" makes Extbase fall back to the TypoScript value
     * (see "ignoreFlexFormSettingsIfEmpty").
     */
    private function isJobAreaCountEnabled(): bool
    {
        return (int)($this->settings['showJobAreaCount'] ?? 0) === 1;
    }

    /**
     * Site setting "jobboard.salary.required" of the site of the request,
     * see SalaryRequirement. The same for every plugin of the site.
     */
    private function isSalaryRequired(): bool
    {
        return SalaryRequirement::fromRequest($this->request)->isRequired();
    }

    /**
     * Uses the precomputed effective salary of the job, the same rule as in
     * the job list (see JobRepository). No salary grade has to be loaded.
     * Only checked, if the site requires salary information.
     *
     * @throws PageNotFoundException
     */
    public function detailAction(Job $job): ResponseInterface
    {
        if ($this->isSalaryRequired() && !$job->getHasEffectiveSalaryInformation()) {
            throw new PageNotFoundException(
                'This job does not have any resolvable salary information and must not be displayed.',
                1753868400,
            );
        }

        $this->view->assign('job', $job);
        $this->view->assign('relatedJobs', $this->getRelatedJobsToLink($job));
        $this->view->assign('settings', $this->settings);

        return $this->htmlResponse();
    }

    /**
     * Related jobs link to their detail view. If the site requires salary
     * information, related jobs without it are skipped: their detail view
     * would not be found (see detailAction()).
     *
     * @return Job[]
     */
    private function getRelatedJobsToLink(Job $job): array
    {
        $relatedJobs = array_values($job->getRelatedJobs()->toArray());
        if (!$this->isSalaryRequired()) {
            return $relatedJobs;
        }

        return array_values(array_filter(
            $relatedJobs,
            static fn(Job $relatedJob): bool => $relatedJob->getHasEffectiveSalaryInformation(),
        ));
    }
}
