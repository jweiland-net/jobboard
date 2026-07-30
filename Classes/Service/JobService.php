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
use JWeiland\Jobfair2\ApiModel\ApiModelInterface;
use JWeiland\Jobfair2\ApiModel\JobModel;
use JWeiland\Jobfair2\Configuration\ImportConfiguration;
use JWeiland\Jobfair2\Traits\ConnectionPoolTrait;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * This service handles data for table tx_jobfair2_domain_model_job
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
readonly class JobService
{
    use ConnectionPoolTrait;

    private const TABLE = 'tx_jobfair2_domain_model_job';

    public function __construct(
        private JobAreaService $jobAreaService,
        private JobTypeService $jobTypeService,
    ) {}

    /**
     * @return int|string INT, if a record was found. STRING: creates NEW[hash] to store new record
     */
    public function getJobUid(int $vacancyId, ApiModelInterface $apiModel): string|int
    {
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);

        try {
            $jobRecord = $queryBuilder
                ->select('uid')
                ->from(self::TABLE)->where($queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($apiModel->getStoragePid(), \TYPO3\CMS\Core\Database\Connection::PARAM_INT),
                ), $queryBuilder->expr()->eq(
                    'vacancy_id',
                    $queryBuilder->createNamedParameter($apiModel->getName() . '_' . $vacancyId),
                ))->executeQuery()
                ->fetchAssociative();
        } catch (\Doctrine\DBAL\Exception $e) {
            $jobRecord = false;
        }

        return $jobRecord === false
            ? StringUtility::getUniqueId('NEW')
            : (int)$jobRecord['uid'];
    }

    public function getPreparedJobByImportedJob(JobModel $importedJob, ApiModelInterface $apiModel): array
    {
        $preparedJob = [
            'pid' => $apiModel->getStoragePid(),
            'is_import' => true,
            'job_area' => $this->jobAreaService->getJobAreaUid(
                $importedJob->getValueByPath('custom_select_4/de'),
            ),
            'job_type' => $this->jobTypeService->getJobTypeUid(
                $importedJob->getValueByPath('custom_select_2/de'),
            ),
            'is_internal' => 0,
        ];

        foreach ($apiModel->getMapping() as $dbColumn => $apiMapping) {
            try {
                $valueFromAPI = $importedJob->getValueByPath($apiMapping->getApiPath());
                if ($apiMapping->isDate()) {
                    $valueFromAPI = $this->getDateAsTimestamp($valueFromAPI);
                }
                if ($apiMapping->getPrefix() !== '') {
                    $valueFromAPI = $apiMapping->getPrefix() . '_' . $valueFromAPI;
                }
                $preparedJob[$dbColumn] = $valueFromAPI;
            } catch (\RuntimeException|\InvalidArgumentException) {
                $preparedJob[$dbColumn] = $apiMapping->getDefault();
            }
        }

        return $preparedJob;
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
                $queryBuilder->createNamedParameter($importConfiguration->getStorage(), \TYPO3\CMS\Core\Database\Connection::PARAM_INT),
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
