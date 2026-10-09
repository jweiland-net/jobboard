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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This service handles data for table tx_jobboard_domain_model_jobarea
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
class JobAreaService
{
    use ConnectionPoolTrait;

    private const TABLE = 'tx_jobboard_domain_model_jobarea';

    /**
     * Resolves one or more job area titles (divided by "\n", as delivered by
     * multi-select fields of the XML API) to a comma-separated list of job
     * area uids, as expected by DataHandler for the MM relation "job_areas".
     * Unknown titles are skipped.
     */
    public function getJobAreaUidList(string $jobAreas): string
    {
        $jobAreaUids = [];
        foreach (GeneralUtility::trimExplode("\n", $jobAreas, true) as $jobArea) {
            $jobAreaUid = $this->getJobAreaUid($jobArea);
            if ($jobAreaUid > 0) {
                $jobAreaUids[$jobAreaUid] = $jobAreaUid;
            }
        }

        return implode(',', $jobAreaUids);
    }

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
