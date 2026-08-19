<?php

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Maps2\Tca\ForeignColumnResolveTypeEnum;
use JWeiland\Maps2\Tca\Maps2Registry;

if (!defined('TYPO3')) {
    die('Access denied.');
}

Maps2Registry::getInstance()->add(
    'tt_address',
    'tt_address',
    [
        'addressColumns' => ['address', 'zip', 'city'],
        'countryColumn' => 'country',
        'synchronizeColumns' => [
            [
                'foreignColumnName' => [
                    'type' => 'coalesce',
                    'columns' => [
                        'company',
                        [
                            'type' => 'concat',
                            'columns' => [
                                'first_name',
                                'last_name',
                            ],
                            'glue' => ' ',
                        ],
                    ],
                ],
                'poiCollectionColumnName' => 'title',
            ],
        ],
    ],
);
