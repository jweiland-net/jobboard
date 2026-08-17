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
        'title' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'type' => 'salary_mode',
        'typeicon_column' => 'salary_mode',
        'typeicon_classes' => [
            'default' => 'ext-jobboard-record-job-grade',
            0 => 'ext-jobboard-record-job-grade',
            1 => 'ext-jobboard-record-job-freeentry',
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
        0 => [
            'showitem' => '--div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tabs.job_description,
            --palette--;;languageHidden, l10n_diffsource,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.job_details;job_details,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.job_description;job_description,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.import;import,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tabs.job_details,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.address;address,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.information;information,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.salary;salary_grade,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.benefit;benefit,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.employer,
            employer, employer_logo, employer_address,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.application_information,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.contact_person;contact_person,
            --palette--;;startEndDate, application_deadline, application_guidelines,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.media,
            header_logo, tender_file, pdf_files, pdf_tstamp,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.relations,
            related_jobs,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access',
        ],
        1 => [
            'showitem' => '--div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tabs.job_description,
            --palette--;;languageHidden, l10n_diffsource,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.job_details;job_details,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.job_description;job_description,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.import;import,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tabs.job_details,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.address;address,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.information;information,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.salary;salary_min_max,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.benefit;benefit,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.employer,
            employer, employer_logo, employer_address,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.application_information,
            --palette--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:palette.contact_person;contact_person,
            --palette--;;startEndDate, application_deadline, application_guidelines,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.media,
            header_logo, tender_file, pdf_files, pdf_tstamp,
            --div--;LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.relations,
            related_jobs,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => ['showitem' => 'sys_language_uid, l10n_parent, hidden'],
        'job_details' => ['showitem' => 'title, reference_number, --linebreak--, subtitle'],
        'job_description' => ['showitem' => 'description, --linebreak--, offer, --linebreak--, requirements, --linebreak--, further_information'],
        'import' => ['showitem' => 'is_import, vacancy_id'],
        'address' => ['showitem' => 'address'],
        'information' => ['showitem' => 'job_role, job_area, --linebreak--, job_type, contract_type, --linebreak--, tender_type'],
        'salary_grade' => ['showitem' => 'salary_mode, --linebreak--, salary_grade'],
        'salary_min_max' => ['showitem' => 'salary_mode, --linebreak--, salary_min, salary_max'],
        'contact_person' => ['showitem' => 'first_name, last_name, --linebreak--, email, telephone, --linebreak--, function'],
        'startEndDate' => ['showitem' => 'start_date, ending_date'],
        'access' => [
            'showitem' => 'starttime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel,endtime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel',
        ],
    ],
    'columns' => [
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 250,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'subtitle' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.subtitle',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 250,
                'eval' => 'trim',
            ],
        ],
        'reference_number' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.reference_number',
            'config' => [
                'type' => 'input',
                'size' => 13,
                'max' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'offer' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.offer',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'requirements' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.requirements',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'further_information' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.further_information',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'address' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.address',
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
        'job_role' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.job_role',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobboard_domain_model_jobrole',
                'foreign_table_where' => 'AND tx_jobboard_domain_model_jobrole.sys_language_uid IN (-1,0) ORDER BY tx_jobboard_domain_model_jobrole.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'job_area' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.job_area',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobboard_domain_model_jobarea',
                'foreign_table_where' => 'AND tx_jobboard_domain_model_jobarea.sys_language_uid IN (-1,0) ORDER BY tx_jobboard_domain_model_jobarea.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'job_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.job_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobboard_domain_model_jobtype',
                'foreign_table_where' => 'AND tx_jobboard_domain_model_jobtype.sys_language_uid IN (-1,0) ORDER BY tx_jobboard_domain_model_jobtype.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'contract_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.contract_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobboard_domain_model_contracttype',
                'foreign_table_where' => 'AND tx_jobboard_domain_model_contracttype.sys_language_uid IN (-1,0) ORDER BY tx_jobboard_domain_model_contracttype.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'tender_type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.tender_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobboard_domain_model_tendertype',
                'foreign_table_where' => 'AND tx_jobboard_domain_model_tendertype.sys_language_uid IN (-1,0) ORDER BY tx_jobboard_domain_model_tendertype.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'benefits' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.benefits',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_jobboard_domain_model_benefit',
                'foreign_table_where' => 'AND tx_jobboard_domain_model_benefit.sys_language_uid IN (-1,0) ORDER BY tx_jobboard_domain_model_benefit.title ASC',
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'salary_mode' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_mode',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_mode.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_mode.grade', 'value' => 0],
                    ['label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_mode.freeEntry', 'value' => 1],
                ],
                'default' => 0,
            ],
        ],
        'salary_grade' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_grade',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_grade.description',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_jobboard_domain_model_salarygrade',
                'foreign_table' => 'tx_jobboard_domain_model_salarygrade',
                'minitems' => 1,
                'maxitems' => 1,
                'size' => 1,
                'suggestOptions' => [
                    'default' => [
                        'addWhere' => 'AND tx_jobboard_domain_model_salarygrade.sys_language_uid IN (-1,0)',
                    ],
                ],
            ],
        ],
        'salary_min' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_min',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_min.description',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'renderType' => 'jobboardLocalizedDecimal',
                'range' => [
                    'lower' => 0,
                ],
                'default' => 0.00,
            ],
        ],
        'salary_max' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_max',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.salary_max.description',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'renderType' => 'jobboardLocalizedDecimal',
                'range' => [
                    'lower' => 0,
                ],
                'default' => 0.00,
            ],
        ],
        'employer' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.employer',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'employer_logo' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.employer_logo',
            'config' => [
                'type' => 'file',
            ],
        ],
        'employer_address' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.employer_address',
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
        'first_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.first_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 60,
                'eval' => 'trim',
            ],
        ],
        'last_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.last_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 60,
                'eval' => 'trim',
            ],
        ],
        'email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.email',
            'config' => [
                'type' => 'email',
                'required' => true,
            ],
        ],
        'telephone' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.telephone',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 60,
                'eval' => 'trim',
            ],
        ],
        'function' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.function',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 60,
                'eval' => 'trim',
            ],
        ],
        'start_date' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.start_date',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.start_date.description',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'format' => 'date',
                'required' => true,
            ],
        ],
        'ending_date' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.ending_date',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.ending_date.description',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'format' => 'date',
                'required' => true,
            ],
        ],
        'application_deadline' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.application_deadline',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.application_deadline.description',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
                'format' => 'date',
            ],
        ],
        'application_guidelines' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.application_guidelines',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'header_logo' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.header_logo',
            'config' => [
                'type' => 'file',
            ],
        ],
        'tender_file' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.tender_file',
            'config' => [
                'type' => 'file',
                'allowed' => 'pdf',
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                ],
            ],
        ],
        'pdf_files' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.pdf_files',
            'config' => [
                'type' => 'file',
                'allowed' => 'pdf',
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                ],
            ],
        ],
        'pdf_tstamp' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.pdf_tstamp',
            'config' => [
                'type' => 'datetime',
                'size' => 16,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'related_jobs' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.related_jobs',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_jobboard_domain_model_job',
                'foreign_table' => 'tx_jobboard_domain_model_job',
                'minitems' => 1,
                'maxitems' => 99,
                'size' => 1,
            ],
        ],
        'link' => [
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.link',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'is_internal' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.is_internal',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.is_internal.no', 'value' => 0],
                    ['label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.is_internal.yes', 'value' => 1],
                ],
                'minitems' => 1,
                'maxItems' => 1,
                'default' => 0,
            ],
        ],
        'is_import' => [
            'exclude' => true,
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.is_import',
            'description' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.is_import.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'vacancy_id' => [
            'exclude' => true,
            'displayCond' => 'FIELD:is_import:REQ:true',
            'label' => 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_job.vacancy_id',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'eval' => 'trim',
            ],
        ],
    ],
];
