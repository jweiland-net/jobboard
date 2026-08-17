<?php

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Jobboard\Backend\Element\LocalizedDecimalElement;
use JWeiland\Jobboard\Controller\JobboardController;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

ExtensionUtility::configurePlugin(
    'Jobboard',
    'Jobboard',
    [
        JobboardController::class => 'list, search, detail',
    ],
    [
        JobboardController::class => 'search',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][] = [
    'nodeName' => 'jobboardLocalizedDecimal',
    'priority' => 40,
    'class' => LocalizedDecimalElement::class,
];

if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobboard']['writerConfiguration'])) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobboard']['writerConfiguration'] = [
        LogLevel::INFO => [
            FileWriter::class => [
                'logFileInfix' => 'jobboard',
            ],
        ],
    ];
}
