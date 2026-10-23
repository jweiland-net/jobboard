<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;

/**
 * Calculates the effective salary of jobs from the database, see
 * EffectiveSalary for the rules.
 */
interface EffectiveSalaryCalculatorInterface
{
    /**
     * Look-ahead ("prevention") in seconds: a salary grade or step, whose
     * endtime is within this period, counts as invalid already. Gives
     * editors time to assign new grades/steps before a job disappears and
     * allows to recalculate once a day only. No look-ahead for starttime.
     */
    public const LOOK_AHEAD = 86400;

    /**
     * Jobs, which do not exist (or are deleted), are missing in the result.
     *
     * @param int[] $jobUids UIDs of jobs in the default language
     * @return array<int, EffectiveSalary> Job UID as key
     */
    public function calculateForJobs(array $jobUids, int $now): array;
}
