<?php

use JWeiland\Jobfair2\Middleware\AddressSearchMiddleware;

return [
    'frontend' => [
        'jweiland/jobfair2-address-search' => [
            'target' => AddressSearchMiddleware::class,
            'after' => [
                // Must be loaded after "frontend.user" aspect (Context API) was initialized.
                // Needed for FrontendUserRestrictionContainer of QueryBuilder.
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
];
