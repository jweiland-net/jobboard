<?php

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
