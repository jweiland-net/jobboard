<?php

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

ExtensionUtility::registerPlugin(
    'Jobfair2',
    'Jobfair',
    'Job fair',
);

ExtensionManagementUtility::addToAllTCAtypes('tt_content', '--div--;Configuration,pi_flexform,', 'jobfair2_jobfair', 'after:subheader');

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:jobfair2/Configuration/FlexForms/Job.xml',
    'jobfair2_jobfair',
);
