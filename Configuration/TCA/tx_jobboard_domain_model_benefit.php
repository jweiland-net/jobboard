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
        'title' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_benefit',
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
        'iconfile' => 'EXT:jobboard/Resources/Public/Icons/jobarea.svg',
    ],
    'types' => [
        0 => [
            'showitem' => '
                --palette--;;titleColor,
                description,
                --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_benefit.media,
                image,
            ',
        ],
    ],
    'palettes' => [
        'titleColor' => ['showitem' => 'title, color'],
    ],
    'columns' => [
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_benefit.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'color' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_benefit.color',
            'config' => [
                'type' => 'input',
                'renderType' => 'color',
                'size' => 10,
                'eval' => 'trim',
                'valuePicker' => [
                    'items' => [
                        ['Blue', '#A8D8EA'],
                        ['Green', '#B8E6B8'],
                        ['Orange', '#FFD8B8'],
                        ['Pink', '#F7C6D9'],
                        ['Purple', '#D9C6F0'],
                        ['Yellow', '#FFF3B0'],
                    ],
                ],
                'default' => '',
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_benefit.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 5,
            ],
        ],
        'image' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_benefit.image',
            'config' => [
                'type' => 'file',
                'maxitems' => 1,
            ],
        ],
    ],
];
