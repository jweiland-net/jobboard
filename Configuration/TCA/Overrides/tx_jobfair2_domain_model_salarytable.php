<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

$GLOBALS['TCA']['tx_jobfair2_domain_model_salarytable']['columns']['starttime']['description'] =
    'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.starttime.description';
$GLOBALS['TCA']['tx_jobfair2_domain_model_salarytable']['columns']['starttime']['config']['behaviour']['allowLanguageSynchronization'] = true;

$GLOBALS['TCA']['tx_jobfair2_domain_model_salarytable']['columns']['endtime']['description'] =
    'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.endtime.description';
$GLOBALS['TCA']['tx_jobfair2_domain_model_salarytable']['columns']['endtime']['config']['behaviour']['allowLanguageSynchronization'] = true;
