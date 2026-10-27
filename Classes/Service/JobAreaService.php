<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This service handles data for table tx_jobboard_domain_model_jobarea
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
class JobAreaService
{
    private const TABLE = 'tx_jobboard_domain_model_jobarea';

    public function __construct(
        private readonly TitleRecordService $titleRecordService,
    ) {}

    /**
     * Resolves one or more job area titles (divided by "\n", as delivered by
     * multi-select fields of the XML API) to a comma-separated list of job
     * area uids, as expected by DataHandler for the MM relation "job_areas".
     * Unknown titles are skipped.
     */
    public function getJobAreaUidList(string $jobAreas): string
    {
        return $this->buildUidList($jobAreas, $this->getJobAreaUid(...));
    }

    /**
     * Like getJobAreaUidList(), but unknown job areas are created on the
     * storage page, so the job areas of the XML API are never lost.
     */
    public function getOrCreateJobAreaUidList(string $jobAreas, int $storagePid): string
    {
        return $this->buildUidList(
            $jobAreas,
            fn(string $jobArea): int => $this->titleRecordService->getOrCreateUidByTitle(self::TABLE, $jobArea, $storagePid),
        );
    }

    /**
     * Returns the UID of the job area in the default language, also if the
     * title matches a translation
     */
    public function getJobAreaUid(string $jobArea): int
    {
        return $this->titleRecordService->findUidByTitle(self::TABLE, $jobArea);
    }

    /**
     * @param callable(string): int $getUid
     */
    private function buildUidList(string $jobAreas, callable $getUid): string
    {
        $jobAreaUids = [];
        foreach (GeneralUtility::trimExplode("\n", $jobAreas, true) as $jobArea) {
            $jobAreaUid = $getUid($jobArea);
            if ($jobAreaUid > 0) {
                $jobAreaUids[$jobAreaUid] = $jobAreaUid;
            }
        }

        return implode(',', $jobAreaUids);
    }
}
