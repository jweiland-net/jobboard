<?php

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Jobboard\Middleware\AddressSearchMiddleware;

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
    ],
];
