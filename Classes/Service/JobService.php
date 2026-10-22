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
use JWeiland\Jobboard\ApiModel\ApiMapping;
use JWeiland\Jobboard\ApiModel\ApiModelInterface;
use JWeiland\Jobboard\ApiModel\JobModel;
use JWeiland\Jobboard\Configuration\ImportConfiguration;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * This service handles data for table tx_jobboard_domain_model_job
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
readonly class JobService
{
    use ConnectionPoolTrait;

    private const TABLE = 'tx_jobboard_domain_model_job';

    public function __construct(
        private JobAreaService $jobAreaService,
        private JobTypeService $jobTypeService,
    ) {}

    /**
     * @param string $vacancyId ID of the job in the source, without prefix
     * @return int|string INT, if a record was found. STRING: creates NEW[hash] to store new record
     */
    public function getJobUid(string $vacancyId, ApiModelInterface $apiModel): string|int
    {
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);

        try {
            $jobRecord = $queryBuilder
                ->select('uid')
                ->from(self::TABLE)->where($queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($apiModel->getStoragePid(), Connection::PARAM_INT),
                ), $queryBuilder->expr()->eq(
                    'vacancy_id',
                    $queryBuilder->createNamedParameter($apiModel->getName() . '_' . $vacancyId),
                ))->executeQuery()
                ->fetchAssociative();
        } catch (\Doctrine\DBAL\Exception) {
            $jobRecord = false;
        }

        return $jobRecord === false
            ? StringUtility::getUniqueId('NEW')
            : (int)$jobRecord['uid'];
    }

    /**
     * Returns the UIDs of all jobs in the storage folder of the API model,
     * which were imported from this API model ("<NAME>_<vacancy ID>").
     *
     * @return int[]
     */
    public function getJobUidsOfApiModel(ApiModelInterface $apiModel): array
    {
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);
        $vacancyIdPrefix = $queryBuilder->escapeLikeWildcards($apiModel->getName() . '_');

        try {
            $jobUids = $queryBuilder
                ->select('uid')
                ->from(self::TABLE)
                ->where(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter($apiModel->getStoragePid(), Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->like(
                        'vacancy_id',
                        $queryBuilder->createNamedParameter($vacancyIdPrefix . '%'),
                    ),
                )
                ->executeQuery()
                ->fetchFirstColumn();
        } catch (\Doctrine\DBAL\Exception) {
            return [];
        }

        return array_map(intval(...), $jobUids);
    }

    public function getPreparedJobByImportedJob(JobModel $importedJob, ApiModelInterface $apiModel): array
    {
        $preparedJob = [
            'pid' => $apiModel->getStoragePid(),
            'is_import' => true,
            'job_areas' => $this->jobAreaService->getJobAreaUidList(
                $importedJob->getValueByPath('custom_select_4/de'),
            ),
            'job_type' => $this->jobTypeService->getJobTypeUid(
                $importedJob->getValueByPath('custom_select_2/de'),
            ),
        ];

        foreach ($apiModel->getMapping() as $dbColumn => $apiMapping) {
            $preparedJob[$dbColumn] = $this->getMappedValue($importedJob, $apiMapping);
        }

        return $preparedJob;
    }

    /**
     * The "default" of the mapping is used as it is, if the XML element is
     * missing or its value can not be converted (e.g. a date in an unknown
     * format). An existing, but empty XML element is not missing.
     */
    private function getMappedValue(JobModel $importedJob, ApiMapping $apiMapping): int|string
    {
        if (!$importedJob->hasPath($apiMapping->getApiPath())) {
            return $apiMapping->getDefault();
        }

        try {
            $valueFromApi = $importedJob->getValueByPath($apiMapping->getApiPath());
            if ($apiMapping->isDate()) {
                $valueFromApi = $this->getDateAsTimestamp($valueFromApi);
            }
            if ($apiMapping->getPrefix() !== '') {
                $valueFromApi = $apiMapping->getPrefix() . '_' . $valueFromApi;
            }
        } catch (\RuntimeException|\InvalidArgumentException) {
            $valueFromApi = $apiMapping->getDefault();
        }

        return $valueFromApi;
    }

    /**
     * XML API delivers the date in format: '2024-01-09 00:00:00 +0100'
     * This method converts it into unix timestamp
     */
    private function getDateAsTimestamp(string $date): int
    {
        if ($date === '' || $date === '0') {
            return 0;
        }

        $dateFormats = [
            'Y-m-d H:i:s O',
            'Y-m-d H:i:s',
            'Y-m-d',
        ];

        foreach ($dateFormats as $dateFormat) {
            $dateTime = \DateTime::createFromFormat($dateFormat, $date);
            if ($dateTime instanceof \DateTime) {
                break;
            }
        }

        if ($dateTime === false) {
            throw new \InvalidArgumentException('Format of date ' . $date . ' could not be detected', 1898516498);
        }

        return (int)$dateTime->format('U');
    }

    /**
     * This method returns an array with the following structure:
     *
     * Array:
     * 1 => false,
     * 3 => false,
     * 14 => false,
     *
     * With each import we set the related array entry to "true". So, after import we can filter that array for
     * "false" entries and mark them as removed (deleted) in database.
     */
    public function getInitialImportStatusOfAlreadyImportedJobs(ImportConfiguration $importConfiguration): array
    {
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);

        $statement = $queryBuilder
            ->select('uid')
            ->from(self::TABLE)->where($queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($importConfiguration->getStorage(), Connection::PARAM_INT),
            ), $queryBuilder->expr()->neq(
                'vacancy_id',
                $queryBuilder->createNamedParameter(''),
            ))->executeQuery();

        $alreadyImportedJobs = [];
        try {
            while ($alreadyImportedJob = $statement->fetchAssociative()) {
                $alreadyImportedJobs[$alreadyImportedJob['uid']] = false;
            }
        } catch (Exception) {
        }

        return $alreadyImportedJobs;
    }
}
