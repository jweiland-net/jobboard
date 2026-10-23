<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Storage pages of the jobs for code running without Extbase (middlewares,
 * event listeners), which has to show the same jobs as the plugin.
 */
interface StoragePidResolverInterface
{
    /**
     * Same depth as "plugin.tx_jobboard.persistence.recursive" of the site set
     */
    public const STORAGE_DEPTH = 3;

    /**
     * Pages of the site setting "jobboard.storagePid" of the site of the
     * request, including sub pages like the plugin does. An empty array, if
     * the request has no site or no storage page is configured.
     *
     * @return int[]
     */
    public function getStoragePids(ServerRequestInterface $request): array;
}
