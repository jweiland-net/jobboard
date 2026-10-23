<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\JobLocation;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Finds the job location (tt_address) of a map marker of EXT:maps2 with its
 * open jobs, without Extbase.
 */
interface JobLocationServiceInterface
{
    /**
     * Returns null, if no visible address is linked to the PoiCollection or
     * none of the linked addresses has an open job, which is listed in the
     * frontend.
     *
     * @param int $poiCollectionUid UID of the PoiCollection in the default language
     */
    public function findByPoiCollection(int $poiCollectionUid, ServerRequestInterface $request): ?JobLocation;
}
