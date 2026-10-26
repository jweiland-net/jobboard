<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Called by the Extbase controller. No job is loaded: the addresses of all
 * matching jobs come from the job query (GROUP BY, see
 * JobSearchOptionsServiceInterface::getAddressUids()), one query reads the
 * PoiCollection UIDs of the visible addresses, then only these
 * PoiCollections are loaded.
 *
 * A localized address without relation uses the PoiCollection of its default
 * address: EXT:maps2 writes the relation of a new PoiCollection into the
 * default address only.
 */
final readonly class MapPoiCollectionService implements MapPoiCollectionServiceInterface
{
    /**
     * As string: the class only exists with EXT:maps2
     */
    private const POI_COLLECTION_CLASS = 'JWeiland\\Maps2\\Domain\\Model\\PoiCollection';

    private const TABLE_ADDRESS = 'tt_address';

    private const FIELD_POI_COLLECTION = 'tx_maps2_uid';

    public function __construct(
        private JobSearchOptionsServiceInterface $jobSearchOptionsService,
        private ConnectionPool $connectionPool,
        private PersistenceManagerInterface $persistenceManager,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    public function getPoiCollections(QueryInterface $jobQuery): ObjectStorage
    {
        $poiCollections = new ObjectStorage();
        // The column only exists with EXT:maps2, see TCA override of tt_address
        if (!$this->tcaSchemaFactory->get(self::TABLE_ADDRESS)->hasField(self::FIELD_POI_COLLECTION)) {
            return $poiCollections;
        }

        $addressUids = $this->jobSearchOptionsService->getAddressUids($jobQuery);
        foreach ($this->getPoiCollectionUids($addressUids) as $poiCollectionUid) {
            $poiCollection = $this->persistenceManager->getObjectByIdentifier($poiCollectionUid, self::POI_COLLECTION_CLASS);
            if ($poiCollection instanceof DomainObjectInterface) {
                $poiCollections->attach($poiCollection);
            }
        }

        return $poiCollections;
    }

    /**
     * Each PoiCollection UID once, in ascending order.
     *
     * @param int[] $addressUids
     * @return int[]
     */
    private function getPoiCollectionUids(array $addressUids): array
    {
        $parentField = $this->getTranslationParentField();
        $poiCollectionUids = [];
        $defaultAddressUids = [];
        foreach ($this->fetchAddresses($addressUids, $parentField) as $address) {
            if ((int)$address[self::FIELD_POI_COLLECTION] > 0) {
                $poiCollectionUids[] = (int)$address[self::FIELD_POI_COLLECTION];
            } elseif ((int)$address[$parentField] > 0) {
                $defaultAddressUids[] = (int)$address[$parentField];
            }
        }

        foreach ($this->fetchAddresses($defaultAddressUids, $parentField) as $defaultAddress) {
            $poiCollectionUids[] = (int)$defaultAddress[self::FIELD_POI_COLLECTION];
        }

        $poiCollectionUids = array_unique(array_filter($poiCollectionUids, static fn(int $uid): bool => $uid > 0));
        sort($poiCollectionUids);

        return $poiCollectionUids;
    }

    /**
     * TCA ctrl "transOrigPointerField" of tt_address (usually "l10n_parent")
     */
    private function getTranslationParentField(): string
    {
        return $this->tcaSchemaFactory
            ->get(self::TABLE_ADDRESS)
            ->getCapability(TcaSchemaCapability::Language)
            ->getTranslationOriginPointerField()
            ->getName();
    }

    /**
     * Visible addresses only (frontend restrictions), as Extbase relations.
     *
     * @param int[] $addressUids
     * @return array<int, array<string, mixed>>
     */
    private function fetchAddresses(array $addressUids, string $parentField): array
    {
        $addressUids = array_values(array_filter($addressUids, static fn(int $uid): bool => $uid > 0));
        if ($addressUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_ADDRESS);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        return $queryBuilder
            ->select($parentField, self::FIELD_POI_COLLECTION)
            ->from(self::TABLE_ADDRESS)
            ->where($queryBuilder->expr()->in(
                'uid',
                $queryBuilder->createNamedParameter($addressUids, Connection::PARAM_INT_ARRAY),
            ))
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
