<?php

use JWeiland\Maps2\Tca\Maps2Registry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

if (ExtensionManagementUtility::isLoaded('tt_address')) {
    Maps2Registry::getInstance()->add(
        'tt_address',
        'tt_address',
        [
            'addressColumns' => ['address', 'zip', 'city'],
            'countryColumn' => 'country',
            'synchronizeColumns' => [
                [
                    'foreignColumnName' => 'company',
                    'poiCollectionColumnName' => 'title',
                ],
            ],
        ],
    );
}
