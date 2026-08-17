<?php

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

if (!defined('TYPO3')) {
    die('Access denied.');
}

$GLOBALS['TCA']['tx_jobboard_domain_model_salarygrade']['columns']['starttime']['description'] =
    'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarygrade.starttime.description';
$GLOBALS['TCA']['tx_jobboard_domain_model_salarygrade']['columns']['starttime']['config']['behaviour']['allowLanguageSynchronization'] = true;

$GLOBALS['TCA']['tx_jobboard_domain_model_salarygrade']['columns']['endtime']['description'] =
    'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarygrade.endtime.description';
$GLOBALS['TCA']['tx_jobboard_domain_model_salarygrade']['columns']['endtime']['config']['behaviour']['allowLanguageSynchronization'] = true;
