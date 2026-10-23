<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Jobboard\Middleware\AddressSearchMiddleware;
use JWeiland\Jobboard\Middleware\RememberJobMiddleware;

return [
    'frontend' => [
        'jweiland/jobboard-address-search' => [
            'target' => AddressSearchMiddleware::class,
            'after' => [
                // Must be loaded after "frontend.user" aspect (Context API) was initialized.
                // Needed for FrontendUserRestrictionContainer of QueryBuilder.
                'typo3/cms-frontend/authentication',
            ],
        ],
        'jweiland/jobboard-remember-job' => [
            'target' => RememberJobMiddleware::class,
            'after' => [
                // Needs the request attribute "frontend.user" (session of the
                // visitor) and the "frontend.user" aspect for the restrictions
                // of the QueryBuilder.
                'typo3/cms-frontend/authentication',
                // Needs the request attribute "site" for "jobboard.storagePid".
                'typo3/cms-frontend/site',
            ],
            'before' => [
                // Needs neither the page nor TypoScript, so answer early.
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
