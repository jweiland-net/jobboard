<?php

use JWeiland\Jobfair2\Backend\Element\LocalizedDecimalElement;
use JWeiland\Jobfair2\Controller\JobfairController;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

ExtensionUtility::configurePlugin(
    'Jobfair2',
    'Jobfair',
    [
        JobfairController::class => 'list, search, detail',
    ],
    [
        JobfairController::class => 'search',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][] = [
    'nodeName' => 'jobfair2LocalizedDecimal',
    'priority' => 40,
    'class' => LocalizedDecimalElement::class,
];

if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobfair2']['writerConfiguration'])) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobfair2']['writerConfiguration'] = [
        LogLevel::INFO => [
            FileWriter::class => [
                'logFileInfix' => 'jobfair2',
            ],
        ],
    ];
}
