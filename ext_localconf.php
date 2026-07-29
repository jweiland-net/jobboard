<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Jobfair2',
    'Jobfair',
    [
        \JWeiland\Jobfair2\Controller\JobfairController::class => 'list, search, detail',
    ],
    [
        \JWeiland\Jobfair2\Controller\JobfairController::class => 'search',
    ],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobfair2']['writerConfiguration'])) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobfair2']['writerConfiguration'] = [
        \Psr\Log\LogLevel::INFO => [
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFileInfix' => 'jobfair2',
            ],
        ],
    ];
}
