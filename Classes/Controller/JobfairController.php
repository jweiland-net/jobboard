<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Controller;

use JWeiland\Jobfair2\Domain\Model\Job;
use JWeiland\Jobfair2\Domain\Model\JobArea;
use JWeiland\Jobfair2\Domain\Model\JobType;
use JWeiland\Jobfair2\Domain\Repository\JobAreaRepository;
use JWeiland\Jobfair2\Domain\Repository\JobRepository;
use JWeiland\Jobfair2\Domain\Repository\JobTypeRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * Class JobfairController
 */
class JobfairController extends ActionController
{
    public function __construct(
        protected JobRepository $jobRepository,
        protected JobAreaRepository $jobAreaRepository,
        protected JobTypeRepository $jobTypeRepository,
    ) {}

    public function listAction(): ResponseInterface
    {
        $searchCriteria = [];
        if ($this->settings['jobAreas']) {
            $searchCriteria['jobArea'] = GeneralUtility::intExplode(',', $this->settings['jobAreas']);
        }

        $this->view->assignMultiple([
            'jobs' => $this->excludeJobsWithoutSalaryInformation(
                $this->jobRepository->findBySearchCriteria($searchCriteria),
                (int)$this->settings['maxEntries'],
            ),
            'jobAreas' => $this->getJobAreas(),
            'jobTypes' => $this->jobTypeRepository->findAll(),
        ]);
        return $this->htmlResponse();
    }

    public function searchAction(
        ?JobArea $jobArea = null,
        ?JobType $jobType = null,
        string $address = '',
    ): ResponseInterface {
        $searchCriteria = [];

        if ($jobArea) {
            $searchCriteria['job_area'] = $jobArea;
        }

        if ($jobType) {
            $searchCriteria['job_type'] = $jobType;
        }

        if ($address !== '') {
            $searchCriteria['address'] = $address;
        }

        foreach ($searchCriteria as $key => $value) {
            $this->view->assign('selected_' . $key, $value);
        }

        $this->view->assignMultiple([
            'jobs' => $this->excludeJobsWithoutSalaryInformation(
                $this->jobRepository->findBySearchCriteria($searchCriteria),
                (int)$this->settings['maxEntries'],
            ),
            'jobAreas' => $this->getJobAreas(),
            'jobTypes' => $this->jobTypeRepository->findAll(),
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

    protected function getJobAreas(): QueryResultInterface
    {
        if ($this->settings['jobAreas']) {
            return $this->jobAreaRepository->findByUids(
                GeneralUtility::intExplode(',', $this->settings['jobAreas']),
            );
        }

        return $this->jobAreaRepository->findAll();
    }

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
