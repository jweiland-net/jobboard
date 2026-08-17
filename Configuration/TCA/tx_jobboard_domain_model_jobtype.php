<?php

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_jobtype',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'dividers2tabs' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:jobboard/Resources/Public/Icons/jobtype.svg',
    ],
    'types' => [
        0 => ['showitem' => 'hidden, title'],
    ],
    'palettes' => [
        '1' => ['showitem' => ''],
    ],
    'columns' => [
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_jobtype.title',
            'config' => [
                'type' => 'input',
                'size' => 13,
                'max' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
];
