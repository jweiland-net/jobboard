<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Builds the markers of the map of EXT:maps2 above the job list.
 */
interface MapPoiCollectionServiceInterface
{
    /**
     * PoiCollections of EXT:maps2 of the locations of all jobs matching the
     * job query (list or search), independent of the page browser: one
     * marker per location, ascending by UID. Without EXT:maps2 the
     * ObjectStorage is empty.
     *
     * @return ObjectStorage<object>
     */
    public function getPoiCollections(QueryInterface $jobQuery): ObjectStorage;
}
