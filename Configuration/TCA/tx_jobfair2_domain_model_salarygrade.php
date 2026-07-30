<?php

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade',
        'label' => 'title',
        'label_alt' => 'salary_table',
        'label_alt_force' => true,
        'formattedLabel_userFunc' => \JWeiland\Jobfair2\UserFunc\InlineRecordTitleFormatter::class . '->formatSalaryGradeTitle',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'type' => 'has_steps',
        'typeicon_column' => 'has_steps',
        'typeicon_classes' => [
            'default' => 'ext-jobfair2-record-salarygrade-stepped',
            1 => 'ext-jobfair2-record-salarygrade-stepped',
            0 => 'ext-jobfair2-record-salarygrade-flat',
        ],
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:jobfair2/Resources/Public/Icons/salarygrade.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                --palette--;;titleStep, salary_steps,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
                --palette--;;access',
        ],
        '0' => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                --palette--;;titleStep, flat_amount,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
                --palette--;;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => ['showitem' => 'sys_language_uid, l10n_parent, hidden'],
        'titleStep' => ['showitem' => 'title, has_steps'],
        'access' => [
            'showitem' => 'starttime;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.starttime,endtime;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.endtime',
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
                'foreign_table' => 'tx_jobfair2_domain_model_salarygrade',
                'foreign_table_where' => 'AND tx_jobfair2_domain_model_salarygrade.pid=###CURRENT_PID### AND tx_jobfair2_domain_model_salarygrade.sys_language_uid IN (-1,0)',
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
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.starttime.description',
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
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.endtime.description',
            'config' => [
                'type' => 'datetime',
                'size' => 16,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'salary_table' => [
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_jobfair2_domain_model_salarytable',
            ],
        ],
        'title' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.title',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.title.description',
            'config' => [
                'type' => 'input',
                'size' => 13,
                'max' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'has_steps' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.has_steps',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.has_steps.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
            ],
        ],
        'flat_amount' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.flat_amount',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.flat_amount.description',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'range' => [
                    'lower' => 0,
                ],
                'default' => 0.00,
            ],
        ],
        'salary_steps' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.salary_steps',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.salary_steps.description',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_jobfair2_domain_model_salarystep',
                'foreign_field' => 'salary_grade',
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
