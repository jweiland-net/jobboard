<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable',
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
        'iconfile' => 'EXT:jobfair2/Resources/Public/Icons/salarytable.svg',
    ],
    'types' => [
        '0' => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                title, description, salary_grades,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
                --palette--;;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => ['showitem' => 'sys_language_uid, l10n_parent, hidden'],
        'access' => [
            'showitem' => 'starttime;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.starttime,endtime;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.endtime',
        ],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobfair2_domain_model_salarytable',
                'foreign_table_where' => 'AND tx_jobfair2_domain_model_salarytable.pid=###CURRENT_PID### AND tx_jobfair2_domain_model_salarytable.sys_language_uid IN (-1,0)',
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => true,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.starttime.description',
            'config' => [
                'type' => 'datetime',
                'size' => 16,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.endtime.description',
            'config' => [
                'type' => 'datetime',
                'size' => 16,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'title' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 250,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
            ],
        ],
        'salary_grades' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarytable.salary_grades',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_jobfair2_domain_model_salarygrade',
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
