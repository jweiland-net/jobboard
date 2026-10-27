<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use GuzzleHttp\Exception\GuzzleException;
use JWeiland\Jobboard\ApiModel\ApiModelInterface;
use JWeiland\Jobboard\ApiModel\JobModel;
use JWeiland\Jobboard\Client\XmlClient;
use JWeiland\Jobboard\Configuration\ImportConfiguration;
use JWeiland\Jobboard\Domain\Model\ImportResult;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use JWeiland\Jobboard\Traits\DataHandlerTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * This service imports jobs from the XML API endpoints of all registered
 * API models (services tagged with "api.model").
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

    /**
     * Imported jobs, which were not delivered by their API model anymore,
     * are deleted after all API models have been processed. If the jobs of
     * an API model could not be fetched completely, all its jobs are kept.
     */
    public function import(ImportConfiguration $importConfiguration): ImportResult
    {
        $importStatus = $this->jobService->getInitialImportStatusOfAlreadyImportedJobs($importConfiguration);

        $importResult = new ImportResult();
        foreach ($this->getApiModels($importConfiguration) as $apiModel) {
            $importResult = $importResult->merge($this->importApiModel($apiModel));
        }

        foreach ($importResult->getJobUids() as $jobUid) {
            $importStatus[$jobUid] = true;
        }

        $this->deleteAlreadyImportedJobsNotInImport($this->getNotImportedVacancyIds($importStatus));

        // Cache Clear is not needed as we are using DataHandler to store the
        // records. Configure TCEMAIN.clearCacheCmd for the storage folder.

        return $importResult;
    }

    private function importApiModel(ApiModelInterface $apiModel): ImportResult
    {
        $jobModels = $this->getJobModelsFromApiEndpoint($apiModel);
        if ($jobModels === null) {
            return new ImportResult($this->jobService->getJobUidsOfApiModel($apiModel), 0, 1);
        }

        return $this->importJobModels($jobModels, $apiModel);
    }

    /**
     * The result contains the UIDs of all jobs delivered by the API model.
     * That includes existing jobs, which could not be updated, as they still
     * exist in the API and must not be deleted.
     *
     * @param JobModel[] $jobModels
     */
    private function importJobModels(array $jobModels, ApiModelInterface $apiModel): ImportResult
    {
        $importResult = new ImportResult();
        foreach ($jobModels as $jobModel) {
            if ($jobModel->getVacancyId() === '') {
                $this->logger->warning(sprintf('Job without vacancy ID of API model "%s" skipped', $apiModel->getName()));
                continue;
            }

            $jobUidOrNew = $this->jobService->getJobUid($jobModel->getVacancyId(), $apiModel);
            $jobUid = $this->saveImportedJob($jobModel, $jobUidOrNew, $apiModel);
            if ($jobUid !== null) {
                $importResult = $importResult->withJobUid($jobUid);
                continue;
            }

            $importResult = $importResult->withFailedJob();
            if (is_int($jobUidOrNew)) {
                $importResult = $importResult->withJobUid($jobUidOrNew);
            }
        }

        return $importResult;
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

    private function saveImportedJob(JobModel $importedJob, string|int $jobUidOrNew, ApiModelInterface $apiModel): ?int
    {
        $dataHandler = $this->getDataHandler();

        try {
            $dataHandler->start(...$this->prepareArgumentsForDataHandler($importedJob, $jobUidOrNew, $apiModel));
            $dataHandler->process_datamap();
            $dataHandler->process_cmdmap();

            if ($dataHandler->errorLog !== []) {
                foreach ($dataHandler->errorLog as $errorLog) {
                    $this->logger->error(sprintf(
                        'Internal DataHandler error with vacancy ID "%s": %s',
                        $importedJob->getVacancyId(),
                        $errorLog,
                    ));
                }

                return null;
            }

            return (int)($dataHandler->substNEWwithIDs[$jobUidOrNew] ?? $jobUidOrNew);
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Error while processing via DataHandler for job with vacancy ID "%s": %s',
                    $importedJob->getVacancyId(),
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
                'tx_jobboard_domain_model_job' => [
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
            'tx_jobboard_domain_model_job' => $cmdMap,
        ]);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    private function getNotImportedVacancyIds(array $importStatus): array
    {
        return array_keys(array_filter($importStatus, static fn($value): bool => $value === false));
    }

    /**
     * Returns null, if the jobs could not be fetched completely. An empty
     * array means: the API model delivered valid XML without any job.
     *
     * @return JobModel[]|null
     */
    private function getJobModelsFromApiEndpoint(ApiModelInterface $apiModel): ?array
    {
        $xml = $this->fetchXml($apiModel);
        if (!$xml instanceof \SimpleXMLElement) {
            return null;
        }

        $jobModels = [];
        foreach ($xml->children() as $publication) {
            $jobModels[] = new JobModel($publication);
        }

        return $jobModels;
    }

    private function fetchXml(ApiModelInterface $apiModel): ?\SimpleXMLElement
    {
        try {
            $response = $this->xmlClient->sendRequest($apiModel->getApiEndpoint());
        } catch (GuzzleException $exception) {
            $this->logFetchError($apiModel, 'Request failed: ' . $exception->getMessage());
            return null;
        }

        if (!$response instanceof ResponseInterface) {
            $this->logFetchError($apiModel, 'API endpoint is not a valid URL');
            return null;
        }

        $xml = $this->parseXml((string)$response->getBody());
        if (!$xml instanceof \SimpleXMLElement) {
            $this->logFetchError($apiModel, 'Response is not valid XML');
        }

        return $xml;
    }

    private function parseXml(string $xmlString): ?\SimpleXMLElement
    {
        $useInternalErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        return $xml instanceof \SimpleXMLElement ? $xml : null;
    }

    private function logFetchError(ApiModelInterface $apiModel, string $reason): void
    {
        $this->logger->error(sprintf(
            'Jobs of API model "%s" could not be fetched from "%s". Its jobs are kept. %s',
            $apiModel->getName(),
            $apiModel->getApiEndpoint(),
            $reason,
        ));
    }
}
