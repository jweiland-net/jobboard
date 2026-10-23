<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Recalculates the effective salary of jobs and writes changed values
 * through DataHandler.
 */
interface EffectiveSalaryUpdaterInterface
{
    public const TABLE_JOB = 'tx_jobboard_domain_model_job';

    public const TABLE_SALARY_GRADE = 'tx_jobboard_domain_model_salarygrade';

    public const TABLE_SALARY_STEP = 'tx_jobboard_domain_model_salarystep';

    public const TABLE_SALARY_TABLE = 'tx_jobboard_domain_model_salarytable';

    /**
     * Recalculates all jobs related to the given records: jobs (also
     * translations), salary grades, salary steps and salary tables.
     *
     * @param array<string, int[]> $uidsByTable Table name as key
     * @return int Number of jobs with changed values
     */
    public function updateByRecords(array $uidsByTable, ?BackendUserAuthentication $backendUser = null): int;

    /**
     * @param int[] $jobUids Jobs of any language
     * @return int Number of jobs with changed values
     */
    public function updateJobs(array $jobUids, ?BackendUserAuthentication $backendUser = null): int;
}
