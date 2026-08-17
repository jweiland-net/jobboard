<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use Doctrine\DBAL\Driver\Exception;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;

/**
 * This service handles data for table tx_jobboard_domain_model_jobtype
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
class JobTypeService
{
    use ConnectionPoolTrait;

    private const TABLE = 'tx_jobboard_domain_model_jobtype';

    /**
     * The job_area in XML API is located in "custom_select_multi_4".
     * These values are divided by "\n".
     * As our database can only handle one record we just check first value.
     */
    public function getJobTypeUid(string $jobType): int
    {
        if ($jobType === '') {
            return 0;
        }

        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);

        try {
            $jobTypeRecord = $queryBuilder
                ->select('uid')
                ->from(self::TABLE)->where($queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($jobType),
                ))->executeQuery()
                ->fetchAssociative();
        } catch (Exception) {
            $jobTypeRecord = false;
        }

        return (int)(is_array($jobTypeRecord) ? $jobTypeRecord['uid'] : 0);
    }
}
