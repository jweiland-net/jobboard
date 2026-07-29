<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Service;

use Doctrine\DBAL\Driver\Exception;
use JWeiland\Jobfair2\Traits\ConnectionPoolTrait;

/**
 * This service handles data for table tx_jobfair2_domain_model_jobarea
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
class JobAreaService
{
    use ConnectionPoolTrait;

    private const TABLE = 'tx_jobfair2_domain_model_jobarea';

    /**
     * The job_area in XML API is located in "custom_select_multi_4".
     * These values are divided by "\n".
     * As our database can only handle one record we just check first value.
     */
    public function getJobAreaUid(string $jobArea): int
    {
        if ($jobArea === '') {
            return 0;
        }

        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);

        try {
            $jobAreaRecord = $queryBuilder
                ->select('uid')
                ->from(self::TABLE)->where($queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($jobArea),
                ))->executeQuery()
                ->fetchAssociative();
        } catch (Exception) {
            $jobAreaRecord = false;
        }

        return (int)(is_array($jobAreaRecord) ? $jobAreaRecord['uid'] : 0);
    }
}
