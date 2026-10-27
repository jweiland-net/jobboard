<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

/**
 * This service handles data for table tx_jobboard_domain_model_jobtype
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
class JobTypeService
{
    private const TABLE = 'tx_jobboard_domain_model_jobtype';

    public function __construct(
        private readonly TitleRecordService $titleRecordService,
    ) {}

    /**
     * Returns the UID of the job type in the default language, also if the
     * title matches a translation. 0, if the job type does not exist.
     */
    public function getJobTypeUid(string $jobType): int
    {
        return $this->titleRecordService->findUidByTitle(self::TABLE, trim($jobType));
    }

    /**
     * Like getJobTypeUid(), but an unknown job type is created on the storage
     * page, so the job type of the XML API is never lost.
     */
    public function getOrCreateJobTypeUid(string $jobType, int $storagePid): int
    {
        return $this->titleRecordService->getOrCreateUidByTitle(self::TABLE, trim($jobType), $storagePid);
    }
}
