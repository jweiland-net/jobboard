<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Builds the options of the search form from all jobs matching a job query
 * (list or search), without loading these jobs.
 */
interface JobSearchOptionsServiceInterface
{
    /**
     * "jobAreas", "jobRoles" and "jobTypes" have the UID as key and the
     * (translated) title as value. With $showJobAreaCount, the title of a job
     * area contains the number of jobs, e.g. "Warehouse (3)". "jobLocations"
     * has the city as key and value.
     *
     * Job areas outside $jobAreaRestriction are neither offered nor counted.
     *
     * @return array<string, array<int|string, string>>
     */
    public function getSearchOptions(
        QueryInterface $query,
        JobAreaRestriction $jobAreaRestriction,
        bool $showJobAreaCount,
    ): array;
}
