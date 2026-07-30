<?php

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

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

$GLOBALS['TCA']['tx_jobfair2_domain_model_job']['columns']['starttime']['config']['behaviour']['allowLanguageSynchronization'] = true;
$GLOBALS['TCA']['tx_jobfair2_domain_model_job']['columns']['endtime']['config']['behaviour']['allowLanguageSynchronization'] = true;
