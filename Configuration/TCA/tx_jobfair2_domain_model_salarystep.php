<?php

use JWeiland\Jobfair2\UserFunc\SalaryStepTitleFormatter;

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarystep',
        'label' => 'step_label',
        'label_alt' => 'amount',
        'label_alt_force' => true,
        'label_userFunc' => SalaryStepTitleFormatter::class . '->formatTitle',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:jobfair2/Resources/Public/Icons/salarystep.svg',
    ],
    'types' => [
        0 => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
                step_label, amount,
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
        'step_label' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarystep.step_label',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarystep.step_label.description',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'amount' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarystep.amount',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_salarystep.amount.description',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'renderType' => 'jobfair2LocalizedDecimal',
                'range' => [
                    'lower' => 0,
                ],
                'required' => true,
                'default' => 0.00,
            ],
        ],
    ],
];
