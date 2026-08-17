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

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarytable',
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
        'iconfile' => 'EXT:jobboard/Resources/Public/Icons/salarytable.svg',
    ],
    'types' => [
        0 => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                title, description, salary_grades,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
                --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => ['showitem' => 'sys_language_uid, l10n_parent, hidden'],
        'access' => [
            'showitem' => 'starttime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel,endtime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel',
        ],
    ],
    'columns' => [
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarytable.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 250,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarytable.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
            ],
        ],
        'salary_grades' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarytable.salary_grades',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_jobboard_domain_model_salarygrade',
                'foreign_field' => 'salary_table',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'useSortable' => true,
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'levelLinksPosition' => 'both',
                    'newRecordLinkAddTitle' => true,
                ],
            ],
        ],
    ],
];
