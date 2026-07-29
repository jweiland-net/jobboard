<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Service;

use GuzzleHttp\Exception\GuzzleException;
use JWeiland\Jobfair2\ApiModel\ApiModelInterface;
use JWeiland\Jobfair2\ApiModel\JobModel;
use JWeiland\Jobfair2\Client\XmlClient;
use JWeiland\Jobfair2\Configuration\ImportConfiguration;
use JWeiland\Jobfair2\Traits\ConnectionPoolTrait;
use JWeiland\Jobfair2\Traits\DataHandlerTrait;
use Psr\Log\LoggerInterface;

/**
 * This service imports jobs from the XML API endpoint of MHM HR.
 */
readonly class ImportService
{
    use ConnectionPoolTrait;
    use DataHandlerTrait;

    public function __construct(
        private XmlClient $xmlClient,
        private JobService $jobService,
        private TtAddressService $ttAddressService,
        private iterable $apiModels,
        private LoggerInterface $logger,
    ) {}

    public function import(ImportConfiguration $importConfiguration): bool
    {
        $initialImportStatusOfAlreadyImportedJobs = $this->jobService->getInitialImportStatusOfAlreadyImportedJobs(
            $importConfiguration,
        );

        foreach ($this->getApiModels($importConfiguration) as $apiModel) {
            $jobModelsFromApiEndpoint = $this->getJobModelsFromApiEndpoint($apiModel->getApiEndpoint());
            if ($jobModelsFromApiEndpoint === []) {
                continue;
            }

            foreach ($jobModelsFromApiEndpoint as $jobModel) {
                $jobUidOrNew = $this->jobService->getJobUid(
                    $jobModel->getValueByPath('vacancy_id', 'int', 0),
                    $apiModel,
                );

                if (($realJobUid = $this->saveImportedJob($jobModel, $jobUidOrNew, $apiModel)) === null) {
                    continue;
                }

                $initialImportStatusOfAlreadyImportedJobs[$realJobUid] = true;
            }
        }

        $this->deleteAlreadyImportedJobsNotInImport(
            $this->getNotImportedVacancyIds($initialImportStatusOfAlreadyImportedJobs),
        );

        // Cache Clear is not needed as we are using DataHandler to store the records,
        // and on page 28535 we have added a TCEMAIN.clearCacheCmd to Page TSconfig.

        return true;
    }

    /**
     * @return ApiModelInterface[]
     */
    private function getApiModels(ImportConfiguration $importConfiguration): array
    {
        $apiModels = [];
        foreach ($this->apiModels as $apiModel) {
            if ($apiModel instanceof ApiModelInterface) {
                $apiModels[] = $apiModel->withStoragePid($importConfiguration->getStorage());
            }
        }

        return $apiModels;
    }

    private function saveImportedJob(JobModel $importedJob, $jobUidOrNew, ApiModelInterface $apiModel): ?int
    {
        $dataHandler = $this->getDataHandler();

        try {
            $dataHandler->start(...$this->prepareArgumentsForDataHandler($importedJob, $jobUidOrNew, $apiModel));
            $dataHandler->process_datamap();
            $dataHandler->process_cmdmap();

            if ($dataHandler->errorLog !== []) {
                foreach ($dataHandler->errorLog as $errorLog) {
                    $this->logger->error(sprintf(
                        'Internal DataHandler error with vacancy_id %d: %s',
                        $importedJob['vacancy_id'] ?? 0,
                        $errorLog,
                    ));
                }

                return null;
            }

            return (int)($dataHandler->substNEWwithIDs[$jobUidOrNew] ?? $jobUidOrNew);
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Error while processing via DataHandler for job with vacancy ID: %d: %s',
                    $importedJob->getValueByPath('vacancy_id', 'int', 0),
                    $exception->getMessage(),
                ),
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
            );
        }

        return null;
    }

    /**
     * @param int|string $jobUid int if a job exists, string (NEW) if not
     */
    private function prepareArgumentsForDataHandler(JobModel $importedJob, int|string $jobUid, ApiModelInterface $apiModel): array
    {
        $migratedAddressRecord = $this->ttAddressService->getPreparedAddressByImportedJob($importedJob, $apiModel);
        $addressUid = $migratedAddressRecord['uid'];
        unset($migratedAddressRecord['uid']);

        $migratedJobRecord = $this->jobService->getPreparedJobByImportedJob($importedJob, $apiModel);
        $migratedJobRecord['address'] = $addressUid;

        return [
            [
                'tt_address' => [
                    $addressUid => $migratedAddressRecord,
                ],
                'tx_jobfair2_domain_model_job' => [
                    $jobUid => $migratedJobRecord,
                ],
            ],
            [],
        ];
    }

    private function deleteAlreadyImportedJobsNotInImport(array $notImportedVacancyIds): void
    {
        // Do not start slow DataHandler if there is nothing to delete
        if ($notImportedVacancyIds === []) {
            return;
        }

        $cmdMap = [];
        foreach ($notImportedVacancyIds as $notImportedVacancyId) {
            $cmdMap[$notImportedVacancyId] = [
                'delete' => 1,
            ];
        }

        // We use DataHandler here to allow modifications via other extensions (HOOK). Needed for solr updates
        $dataHandler = $this->getDataHandler();
        $dataHandler->start([], [
            'tx_jobfair2_domain_model_job' => $cmdMap,
        ]);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    private function getNotImportedVacancyIds(array $importStatus): array
    {
        return array_keys(array_filter($importStatus, static fn($value): bool => $value === false));
    }

    /**
     * @return JobModel[]
     */
    private function getJobModelsFromApiEndpoint(string $endpoint): array
    {
        try {
            $response = $this->xmlClient->sendRequest($endpoint);

            $xml = simplexml_load_string((string)$response->getBody());
            if ($xml === false) {
                return [];
            }

            $publications = [];
            foreach ($xml->children() as $publication) {
                $publications[] = new JobModel($publication);
            }

            return $publications;
        } catch (GuzzleException) {
        }

        return [];
    }
}
