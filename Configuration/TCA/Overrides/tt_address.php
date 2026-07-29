<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
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
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'tt_address',
    'import_key',
    '',
    'after:country',
);
