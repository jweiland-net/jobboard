<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ViewHelpers;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collects the PoiCollections of EXT:maps2 of the given addresses
 * (tt_address UIDs, see JobSearchOptionsServiceInterface::getAddressUids())
 * to show them as markers on a map: one marker per job location. No job is
 * loaded: one query reads the PoiCollection UIDs of the visible addresses,
 * then only these PoiCollections are loaded. EXT:maps2 is optional: without
 * it, an empty ObjectStorage is returned.
 *
 * Like JobPoiCollectionViewHelper, a localized address without relation
 * uses the PoiCollection of its default address: EXT:maps2 writes the
 * relation of a new PoiCollection into the default address only.
 */
final class AddressPoiCollectionViewHelper extends AbstractViewHelper
{
    /**
     * As string: the class only exists with EXT:maps2
     */
    private const POI_COLLECTION_CLASS = 'JWeiland\\Maps2\\Domain\\Model\\PoiCollection';

    private const TABLE_ADDRESS = 'tt_address';

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'addressUids',
            'array',
            'UIDs of addresses (tt_address)',
            false,
            [],
        );
    }

    public function render(): ObjectStorage
    {
        $poiCollections = new ObjectStorage();
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManagerInterface::class);
        foreach ($this->getPoiCollectionUids($this->getAddressUids()) as $poiCollectionUid) {
            $poiCollection = $persistenceManager->getObjectByIdentifier($poiCollectionUid, self::POI_COLLECTION_CLASS);
            if ($poiCollection instanceof DomainObjectInterface) {
                $poiCollections->attach($poiCollection);
            }
        }

        return $poiCollections;
    }

    /**
     * @return int[]
     */
    private function getAddressUids(): array
    {
        $addressUids = array_map(intval(...), (array)($this->arguments['addressUids'] ?? []));

        return array_values(array_unique(array_filter($addressUids, static fn(int $uid): bool => $uid > 0)));
    }

    /**
     * Each PoiCollection UID once, in ascending order.
     *
     * @param int[] $addressUids
     * @return int[]
     */
    private function getPoiCollectionUids(array $addressUids): array
    {
        if ($addressUids === [] || !isset($GLOBALS['TCA'][self::TABLE_ADDRESS]['columns']['tx_maps2_uid'])) {
            return [];
        }

        $poiCollectionUids = [];
        $defaultAddressUids = [];
        foreach ($this->fetchAddresses($addressUids) as $address) {
            if ((int)$address['tx_maps2_uid'] > 0) {
                $poiCollectionUids[] = (int)$address['tx_maps2_uid'];
            } elseif ((int)$address['l10n_parent'] > 0) {
                $defaultAddressUids[] = (int)$address['l10n_parent'];
            }
        }

        foreach ($this->fetchAddresses($defaultAddressUids) as $defaultAddress) {
            $poiCollectionUids[] = (int)$defaultAddress['tx_maps2_uid'];
        }

        $poiCollectionUids = array_unique(array_filter($poiCollectionUids, static fn(int $uid): bool => $uid > 0));
        sort($poiCollectionUids);

        return $poiCollectionUids;
    }

    /**
     * Visible addresses only (frontend restrictions), as Extbase relations.
     *
     * @param int[] $addressUids
     * @return array<int, array<string, mixed>>
     */
    private function fetchAddresses(array $addressUids): array
    {
        if ($addressUids === []) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_ADDRESS);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        return $queryBuilder
            ->select('l10n_parent', 'tx_maps2_uid')
            ->from(self::TABLE_ADDRESS)
            ->where($queryBuilder->expr()->in(
                'uid',
                $queryBuilder->createNamedParameter($addressUids, Connection::PARAM_INT_ARRAY),
            ))
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
