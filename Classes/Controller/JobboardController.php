<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Controller;

use JWeiland\Jobboard\Domain\Model\Address;
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\Event\PostProcessFluidVariablesEvent;
use JWeiland\Jobboard\Service\RememberedJobsServiceInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class JobboardController extends ActionController
{
    public function __construct(
        protected JobRepository $jobRepository,
        protected RememberedJobsServiceInterface $rememberedJobsService,
    ) {}

    public function listAction(): ResponseInterface
    {
        $jobs = $this->excludeJobsWithoutSalaryInformation(
            $this->jobRepository->findBySettings($this->settings),
            (int)($this->settings['maxEntries'] ?? 0),
        );

        $fluidVariables = $this->getJobVariables($jobs);
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

        $jobs = $this->excludeJobsWithoutSalaryInformation(
            $this->jobRepository->findBySearch($search, settings: $this->settings),
            (int)($this->settings['maxEntries'] ?? 0),
        );

        $fluidVariables = $this->getJobVariables($jobs);
        ArrayUtility::mergeRecursiveWithOverrule($fluidVariables, $search->getSelectedValues());
        ArrayUtility::mergeRecursiveWithOverrule($fluidVariables, $this->getSearchOptions($jobs));
        $this->assignPostProcessedFluidVariables($fluidVariables);

        return $this->htmlResponse();
    }

    /**
     * Listeners of PostProcessFluidVariablesEvent can add or replace variables
     * before they are assigned, e.g. AddPaginatorEventListener paginates the jobs.
     * The search options are built from all jobs, not only the jobs of the
     * current page.
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
     * "jobs" are all jobs with the jobs remembered by the visitor first,
     * "rememberedJobs" has the UIDs of the remembered jobs as keys for the
     * checkbox and the highlight in the template.
     *
     * @param Job[] $jobs
     * @return array<string, mixed>
     */
    private function getJobVariables(array $jobs): array
    {
        $rememberedJobs = $this->rememberedJobsService->getRememberedJobs($this->request);

        return [
            'jobs' => $this->sortRememberedJobsFirst($jobs, $rememberedJobs),
            'rememberedJobs' => $rememberedJobs->getUidsAsKeys(),
        ];
    }

    /**
     * Moves the remembered jobs to the top before the jobs are paginated, so
     * they are on the first page. Both groups keep the order of the query.
     * The limit "maxEntries" was applied before, so remembering a job never
     * changes which jobs are listed, only their order.
     *
     * @param Job[] $jobs
     * @return Job[]
     */
    private function sortRememberedJobsFirst(array $jobs, RememberedJobs $rememberedJobs): array
    {
        if ($rememberedJobs->isEmpty()) {
            return $jobs;
        }

        $firstJobs = [];
        $otherJobs = [];
        foreach ($jobs as $job) {
            if ($rememberedJobs->contains((int)$job->getUid())) {
                $firstJobs[] = $job;
            } else {
                $otherJobs[] = $job;
            }
        }

        return [...$firstJobs, ...$otherJobs];
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
     * @param Job[] $jobs
     * @return array<string, array<int|string, string>>
     */
    private function getSearchOptions(array $jobs): array
    {
        return [
            'jobAreas' => $this->getJobAreas($jobs),
            'jobRoles' => $this->getJobRoles($jobs),
            'jobTypes' => $this->getJobTypes($jobs),
            'jobLocations' => $this->getJobLocations($jobs),
        ];
    }

    /**
     * Jobs without resolvable salary information must never reach the frontend - this
     * also covers a salary grade or all of its steps having expired/hidden via TYPO3's
     * own access restrictions in the meantime. Filtering happens here (list/search),
     * not in JobRepository, so it stays a display rule instead of a data access one.
     *
     * Applies the limit only after filtering, so a page never shows fewer jobs than
     * requested just because some of the matches turned out to be ineligible - and
     * stops iterating as soon as the limit is reached instead of always walking every
     * matched job.
     *
     * @param iterable<Job> $jobs
     * @return Job[]
     */
    private function excludeJobsWithoutSalaryInformation(iterable $jobs, int $limit = 0): array
    {
        $eligibleJobs = [];
        foreach ($jobs as $job) {
            if (!$job->getHasSalaryInformation()) {
                continue;
            }

            $eligibleJobs[] = $job;

            if ($limit > 0 && count($eligibleJobs) >= $limit) {
                break;
            }
        }

        return $eligibleJobs;
    }

    /**
     * With "settings.showJobAreaCount" enabled, each option label contains the
     * number of jobs having that job area among their job areas, e.g.
     * "Warehouse (3)". As a job can have several job areas, the numbers may add
     * up to more than the total number of jobs.
     *
     * A job may have further job areas besides the job areas configured in
     * the plugin ("settings.jobAreas"). These are neither offered nor counted.
     *
     * @param Job[] $jobs
     * @return array<int, string>
     */
    protected function getJobAreas(array $jobs): array
    {
        $jobAreaRestriction = JobAreaRestriction::fromSettings($this->settings);
        $titles = [];
        $numberOfJobs = [];
        foreach ($jobs as $job) {
            foreach ($job->getJobAreas() as $jobArea) {
                if (!$jobAreaRestriction->allows((int)$jobArea->getUid())) {
                    continue;
                }
                $titles[$jobArea->getUid()] = $jobArea->getTitle();
                $numberOfJobs[$jobArea->getUid()] = ($numberOfJobs[$jobArea->getUid()] ?? 0) + 1;
            }
        }

        if (!$this->isJobAreaCountEnabled()) {
            return $titles;
        }

        $jobAreas = [];
        foreach ($titles as $jobAreaUid => $title) {
            $jobAreas[$jobAreaUid] = sprintf('%s (%d)', $title, $numberOfJobs[$jobAreaUid]);
        }

        return $jobAreas;
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
     * @param Job[] $jobs
     */
    protected function getJobRoles(array $jobs): array
    {
        $jobRoles = [];
        foreach ($jobs as $job) {
            if ($job->getJobRole() instanceof JobRole) {
                $jobRoles[$job->getJobRole()->getUid()] = $job->getJobRole()->getTitle();
            }
        }

        return $jobRoles;
    }

    /**
     * @param Job[] $jobs
     */
    protected function getJobTypes(array $jobs): array
    {
        $jobTypes = [];
        foreach ($jobs as $job) {
            if ($job->getJobType() instanceof JobType) {
                $jobTypes[$job->getJobType()->getUid()] = $job->getJobType()->getTitle();
            }
        }

        return $jobTypes;
    }

    /**
     * @param Job[] $jobs
     */
    protected function getJobLocations(array $jobs): array
    {
        $jobLocations = [];
        foreach ($jobs as $job) {
            if ($job->getAddress() instanceof Address) {
                $jobLocations[$job->getAddress()->getCity()] = $job->getAddress()->getCity();
            }
        }

        return $jobLocations;
    }

    /**
     * @throws PageNotFoundException
     */
    public function detailAction(Job $job): ResponseInterface
    {
        if (!$job->getHasSalaryInformation()) {
            // A job without resolvable salary information must not be displayed - this
            // also covers a salary grade or all of its steps having expired/hidden via
            // TYPO3's own access restrictions in the meantime.
            throw new PageNotFoundException(
                'This job does not have any resolvable salary information and must not be displayed.',
                1753868400,
            );
        }

        $this->view->assign('job', $job);
        $this->view->assign('settings', $this->settings);

        return $this->htmlResponse();
    }
}
