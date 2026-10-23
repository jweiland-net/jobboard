<?php

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Maps2\Tca\ForeignColumnResolveTypeEnum;
use JWeiland\Maps2\Tca\Maps2Registry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

ExtensionManagementUtility::addTCAcolumns(
    'tt_address',
    [
        'import_key' => [
            'exclude' => true,
            'label' => 'Import Key',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
    ],
);
ExtensionManagementUtility::addToAllTCAtypes(
    'tt_address',
    'import_key',
    '',
    'after:country',
);

// EXT:maps2 is optional. Only if it is installed, add the column
// "tx_maps2_uid" to tt_address to show the job locations on a map.
// The title of the PoiCollection (marker) is synchronized on every save of
// the address: company, otherwise first and last name, otherwise city.
if (ExtensionManagementUtility::isLoaded('maps2')) {
    Maps2Registry::getInstance()->add(
        'tt_address',
        'tt_address',
        [
            'addressColumns' => ['address', 'zip', 'city'],
            'countryColumn' => 'country',
            // The position does not depend on the language. EXT:maps2 writes
            // the relation of a new PoiCollection into the default language
            // only, DataHandler copies it into the translations on the next
            // save. JobPoiCollectionViewHelper covers the time in between.
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'synchronizeColumns' => [
                [
                    'foreignColumnName' => [
                        'type' => ForeignColumnResolveTypeEnum::COALESCE,
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
                            'city',
                        ],
                    ],
                    'poiCollectionColumnName' => 'title',
                ],
            ],
        ],
    );
}
