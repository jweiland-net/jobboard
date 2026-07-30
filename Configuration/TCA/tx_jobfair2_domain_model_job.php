<?php

use TYPO3\CMS\Core\Resource\FileType;

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'type' => 'salary_mode',
        'typeicon_column' => 'salary_mode',
        'typeicon_classes' => [
            'default' => 'ext-jobfair2-record-job-grade',
            0 => 'ext-jobfair2-record-job-grade',
            1 => 'ext-jobfair2-record-job-freeentry',
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
    ],
    'types' => [
        '0' => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
            --palette--;Job;titleReference,
            --palette--;Import;importVacancy,
            description, address, --palette--;;areaType, --palette--;;startEndDate,
            --div--;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.employer, employer, email, employer_address,
            --div--;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary, salary_grade,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access',
        ],
        '1' => [
            'showitem' => '--palette--;;languageHidden, l10n_diffsource,
            --palette--;Job;titleReference,
            --palette--;Import;importVacancy,
            description, address, --palette--;;areaType, --palette--;;startEndDate,
            --div--;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.employer, employer, email, employer_address,
            --div--;LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary, salary_min, salary_max,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => ['showitem' => 'sys_language_uid, l10n_parent, hidden'],
        'titleReference' => ['showitem' => 'title, reference_number'],
        'importVacancy' => ['showitem' => 'is_import, vacancy_id'],
        'areaType' => ['showitem' => 'job_area, job_type'],
        'startEndDate' => ['showitem' => 'start_date, ending_date'],
        'access' => [
            'showitem' => 'starttime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel,endtime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel',
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
                'foreign_table' => 'tx_jobfair2_domain_model_job',
                'foreign_table_where' => 'AND tx_jobfair2_domain_model_job.pid=###CURRENT_PID### AND tx_jobfair2_domain_model_job.sys_language_uid IN (-1,0)',
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => true,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'l10n_source' => [
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
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 250,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'reference_number' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.reference_number',
            'config' => [
                'type' => 'input',
                'size' => 13,
                'max' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'is_import' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.is_import',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.is_import.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'vacancy_id' => [
            'exclude' => 1,
            'displayCond' => 'FIELD:is_import:REQ:true',
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.vacancy_id',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'eval' => 'trim',
            ],
        ],
        'description' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'address' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.address',
            'config' => [
                'type' => 'group',
                'allowed' => 'tt_address',
                'foreign_table' => 'tt_address',
                'minitems' => 1,
                'maxitems' => 1,
                'size' => 1,
                'suggestOptions' => [
                    'default' => [
                        'additionalSearchFields' => 'company',
                        'addWhere' => 'AND tt_address.pid = ###CURRENT_PID###',
                    ],
                ],
                'required' => true,
            ],
        ],
        'link' => [
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.link',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'job_area' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.job_area',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobfair2_domain_model_jobarea',
                'foreign_table_where' => 'AND tx_jobfair2_domain_model_jobarea.sys_language_uid IN (-1,0) ORDER BY tx_jobfair2_domain_model_jobarea.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'job_type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.job_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobfair2_domain_model_jobtype',
                'foreign_table_where' => 'AND tx_jobfair2_domain_model_jobtype.sys_language_uid IN (-1,0) ORDER BY tx_jobfair2_domain_model_jobtype.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'start_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.start_date',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.start_date.description',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'format' => 'date',
                'required' => true,
            ],
        ],
        'ending_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.ending_date',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.ending_date.description',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'format' => 'date',
                'required' => true,
            ],
        ],
        'employer' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.employer',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'employer_address' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.employer_address',
            'config' => [
                'type' => 'group',
                'allowed' => 'tt_address',
                'minitems' => 0,
                'maxitems' => 1,
                'size' => 1,
                'default' => 0,
                'suggestOptions' => [
                    'default' => [
                        'additionalSearchFields' => 'company',
                        'addWhere' => 'AND tt_address.pid = ###CURRENT_PID###',
                    ],
                ],
            ],
        ],
        'email' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.email',
            'config' => [
                'type' => 'email',
                'required' => true,
            ],
        ],
        'tender_file' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.tender_file',
            'config' => [
                //## !!! Watch out for fieldName different from columnName
                'type' => 'file',
                'allowed' => 'pdf',
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                ],
                // Use the imageoverlayPalette instead of the basicoverlayPalette
                'overrideChildTca' => [
                    'types' => [
                        '0' => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::TEXT->value => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::IMAGE->value => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::AUDIO->value => [
                            'showitem' => '
                                        --palette--;;audioOverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::VIDEO->value => [
                            'showitem' => '
                                        --palette--;;videoOverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::APPLICATION->value => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                    ],
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'pdf_files' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.pdf_files',
            'config' => [
                //## !!! Watch out for fieldName different from columnName
                'type' => 'file',
                'allowed' => 'pdf',
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                ],
                // Use the imageoverlayPalette instead of the basicoverlayPalette
                'overrideChildTca' => [
                    'types' => [
                        '0' => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::TEXT->value => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::IMAGE->value => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::AUDIO->value => [
                            'showitem' => '
                                        --palette--;;audioOverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::VIDEO->value => [
                            'showitem' => '
                                        --palette--;;videoOverlayPalette,
                                        --palette--;;filePalette',
                        ],
                        FileType::APPLICATION->value => [
                            'showitem' => '
                                        --palette--;;imageoverlayPalette,
                                        --palette--;;filePalette',
                        ],
                    ],
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'pdf_tstamp' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.pdf_tstamp',
            'config' => [
                'type' => 'datetime',
                'size' => 16,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'is_internal' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.is_internal',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.is_internal.no', 'value' => 0],
                    ['label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.is_internal.yes', 'value' => 1],
                ],
                'minitems' => 1,
                'maxItems' => 1,
                'default' => 0,
            ],
        ],
        'salary_mode' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_mode',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_mode.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_mode.grade', 'value' => 0],
                    ['label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_mode.freeEntry', 'value' => 1],
                ],
                'default' => 0,
            ],
        ],
        'salary_grade' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_grade',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_grade.description',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_jobfair2_domain_model_salarygrade',
                'foreign_table' => 'tx_jobfair2_domain_model_salarygrade',
                'minitems' => 1,
                'maxitems' => 1,
                'size' => 1,
                'suggestOptions' => [
                    'default' => [
                        'addWhere' => 'AND tx_jobfair2_domain_model_salarygrade.sys_language_uid IN (-1,0)',
                    ],
                ],
            ],
        ],
        'salary_min' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_min',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_min.description',
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
        'salary_max' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_max',
            'description' => 'LLL:EXT:jobfair2/Resources/Private/Language/locallang_db.xlf:tx_jobfair2_domain_model_job.salary_max.description',
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
    ],
];
