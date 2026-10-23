<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ViewHelpers;

use JWeiland\Jobboard\Domain\Model\Address;
use JWeiland\Jobboard\Domain\Model\Job;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collects the PoiCollections of EXT:maps2 of the addresses of all given
 * jobs to show them as markers on a map: one marker per job location, even
 * if several jobs share the same address. EXT:maps2 is optional: without it,
 * no address has a PoiCollection and an empty ObjectStorage is returned.
 *
 * The PoiCollections are returned unchanged: EXT:maps2 synchronizes their
 * title from the address (see TCA override of tt_address) and the info
 * window lists the jobs of the location, see
 * RenderJobLocationInfoWindowContentEventListener.
 */
final class JobPoiCollectionViewHelper extends AbstractViewHelper
{
    /**
     * As string: the class only exists with EXT:maps2
     */
    private const POI_COLLECTION_CLASS = 'JWeiland\\Maps2\\Domain\\Model\\PoiCollection';

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'jobs',
            \Traversable::class,
            'Jobs',
        );
    }

    public function render(): ObjectStorage
    {
        $poiCollections = new ObjectStorage();
        $poiCollectionUids = [];
        foreach ($this->arguments['jobs'] ?? [] as $job) {
            $poiCollection = $job instanceof Job ? $this->getPoiCollection($job->getAddress()) : null;
            if (!$poiCollection instanceof DomainObjectInterface) {
                continue;
            }

            // Not yet persisted PoiCollections have no UID. The ObjectStorage
            // still attaches each object only once.
            $uid = $poiCollection->getUid();
            if ($uid !== null) {
                if (isset($poiCollectionUids[$uid])) {
                    continue;
                }
                $poiCollectionUids[$uid] = true;
            }

            $poiCollections->attach($poiCollection);
        }

        return $poiCollections;
    }

    private function getPoiCollection(?Address $address): ?DomainObjectInterface
    {
        if ($address === null) {
            return null;
        }

        return $address->getTxMaps2Uid() ?? $this->getPoiCollectionOfDefaultLanguage($address);
    }

    /**
     * EXT:maps2 writes the relation of a new PoiCollection into the address
     * of the default language only. DataHandler copies it into the
     * translations ("l10n_mode = exclude") with the next save of the address.
     * Until then, the PoiCollection of the default address is used.
     */
    private function getPoiCollectionOfDefaultLanguage(Address $address): ?DomainObjectInterface
    {
        $defaultUid = (int)$address->getUid();
        if ($defaultUid === 0
            || (int)$address->_getProperty(AbstractDomainObject::PROPERTY_LOCALIZED_UID) === $defaultUid
            || !isset($GLOBALS['TCA']['tt_address']['columns']['tx_maps2_uid'])
        ) {
            return null;
        }

        $poiCollectionUid = $this->getPoiCollectionUidOfAddress($defaultUid);
        $poiCollection = $poiCollectionUid > 0
            ? GeneralUtility::makeInstance(PersistenceManagerInterface::class)
                ->getObjectByIdentifier($poiCollectionUid, self::POI_COLLECTION_CLASS)
            : null;

        return $poiCollection instanceof DomainObjectInterface ? $poiCollection : null;
    }

    private function getPoiCollectionUidOfAddress(int $addressUid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_address');

        return (int)$queryBuilder
            ->select('tx_maps2_uid')
            ->from('tt_address')
            ->where($queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($addressUid, Connection::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchOne();
    }
}
