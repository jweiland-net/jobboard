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
use JWeiland\Jobboard\ApiModel\ApiModelInterface;
use JWeiland\Jobboard\ApiModel\JobModel;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * This service handles data for table tt_address
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
readonly class TtAddressService
{
    use ConnectionPoolTrait;

    private const TABLE = 'tt_address';

    public function getPreparedAddressByImportedJob(JobModel $importedJob, ApiModelInterface $apiModel): array
    {
        $primaryLocation = $importedJob->getPrimaryLocation();

        // "technical_name" is a unique identifier of XML API endpoint
        return [
            'uid' => $this->getAddressUid(
                $primaryLocation->getValueByPath('technical_name'),
                $apiModel->getStoragePid(),
            ),
            'pid' => $apiModel->getStoragePid(),
            'name' => $importedJob->getValueByPath('manager_name'),
            'email' => $importedJob->getValueByPath('manager_email'),
            'company' => $primaryLocation->getValueByPath('de'),
            'address' => $primaryLocation->getValueByPath('street'),
            'zip' => $primaryLocation->getValueByPath('zipcode'),
            'city' => $primaryLocation->getValueByPath('city'),
            'import_key' => $primaryLocation->getValueByPath('technical_name'),
        ];
    }

    /**
     * GeneralUtility::xml2array assigns a single array element directly to its parent:
     * job -> locations -> location
     *
     * But if there are multiple locations, xml2array assigns the arrays by key:
     * job -> locations -> 0 -> location
     * --> Not tested, but MHM HR told me that it is possible to define multiple locations
     */
    private function getFirstLocation(array $locations): array
    {
        return array_key_exists('location', $locations) ? $locations['location'] : current($locations);
    }

    private function getAddressUid(string $technicalName, int $storagePid): int|string
    {
        $queryBuilder = $this->getQueryBuilderForTable('tt_address');

        try {
            $addressRecord = $queryBuilder
                ->select('uid')
                ->from('tt_address')->where($queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($storagePid, Connection::PARAM_INT),
                ), $queryBuilder->expr()->eq(
                    'import_key',
                    $queryBuilder->createNamedParameter($technicalName),
                ))->executeQuery()
                ->fetchAssociative();
        } catch (Exception) {
            $addressRecord = false;
        }

        return $addressRecord === false
            ? StringUtility::getUniqueId('NEW')
            : (int)$addressRecord['uid'];
    }
}
