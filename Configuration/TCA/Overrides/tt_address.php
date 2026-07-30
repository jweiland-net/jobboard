<?php

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

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
