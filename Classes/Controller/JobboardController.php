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
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\PageTitle\JobPageTitleProvider;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class JobboardController extends ActionController
{
    public function __construct(
        protected JobRepository $jobRepository,
        protected JobPageTitleProvider $jobPageTitleProvider,
    ) {}

    public function listAction(): ResponseInterface
    {
        $jobs = $this->excludeJobsWithoutSalaryInformation(
            $this->jobRepository->findBySettings($this->settings),
            (int)($this->settings['maxEntries'] ?? 0),
        );

        $this->view->assignMultiple([
            'jobs' => $jobs,
            'jobAreas' => $this->getJobAreas($jobs),
            'jobRoles' => $this->getJobRoles($jobs),
            'jobTypes' => $this->getJobTypes($jobs),
            'jobLocations' => $this->getJobLocations($jobs),
        ]);

        return $this->htmlResponse();
    }

    public function searchAction(
        ?JobArea $jobArea = null,
        ?JobRole $jobRole = null,
        ?JobType $jobType = null,
        string $address = '',
        string $searchWord = '',
    ): ResponseInterface {
        $search = new Search($jobArea, $jobRole, $jobType, $address, $searchWord);

        $jobs = $this->excludeJobsWithoutSalaryInformation(
            $this->jobRepository->findBySearch($search),
            (int)($this->settings['maxEntries'] ?? 0),
        );

        $this->view->assignMultiple($search->getSelectedValues());
        $this->view->assignMultiple([
            'jobs' => $jobs,
            'jobAreas' => $this->getJobAreas($jobs),
            'jobRoles' => $this->getJobRoles($jobs),
            'jobTypes' => $this->getJobTypes($jobs),
            'jobLocations' => $this->getJobLocations($jobs),
        ]);

        return $this->htmlResponse();
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
     * @param Job[] $jobs
     * @return array<int, string>
     */
    protected function getJobAreas(array $jobs): array
    {
        $titles = [];
        $numberOfJobs = [];
        foreach ($jobs as $job) {
            foreach ($job->getJobAreas() as $jobArea) {
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

        $this->jobPageTitleProvider->setTitle($job->getTitle());

        $this->view->assign('job', $job);
        $this->view->assign('settings', $this->settings);

        return $this->htmlResponse();
    }
}
