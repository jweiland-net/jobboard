<?php

use JWeiland\Jobfair2\UserFunc\SalaryGradeTitleFormatter;

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade',
        'label' => 'title',
        'label_userFunc' => SalaryGradeTitleFormatter::class . '->formatTitle',
        'formattedLabel_userFunc' => SalaryGradeTitleFormatter::class . '->formatInlineChildTitle',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'type' => 'has_steps',
        'typeicon_column' => 'has_steps',
        'typeicon_classes' => [
            'default' => 'ext-jobfair2-record-salarygrade-stepped',
            0 => 'ext-jobfair2-record-salarygrade-flat',
            1 => 'ext-jobfair2-record-salarygrade-stepped',
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
        0 => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                --palette--;;titleStep, flat_amount,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
                --palette--;;access',
        ],
        1 => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                --palette--;;titleStep, salary_steps,
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
        'title' => [
            'exclude' => true,
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
            'exclude' => true,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.has_steps',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.has_steps.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
            ],
        ],
        'flat_amount' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.flat_amount',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarygrade.flat_amount.description',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'renderType' => 'jobfair2LocalizedDecimal',
                'range' => [
                    'lower' => 0,
                ],
                'default' => 0.00,
            ],
        ],
        'salary_steps' => [
            'exclude' => true,
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
        'salary_table' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
