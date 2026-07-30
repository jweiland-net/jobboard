<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

$GLOBALS['TCA']['tx_jobfair2_domain_model_salarystep']['columns']['starttime']['config']['behaviour']['allowLanguageSynchronization'] = true;
$GLOBALS['TCA']['tx_jobfair2_domain_model_salarystep']['columns']['endtime']['config']['behaviour']['allowLanguageSynchronization'] = true;
