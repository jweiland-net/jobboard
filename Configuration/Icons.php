<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-jobfair2-record-job' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/job.svg',
    ],
    'ext-jobfair2-record-job-import' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/job-import.svg',
    ],
    'ext-jobfair2-record-job-grade' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/job-grade.svg',
    ],
    'ext-jobfair2-record-job-freeentry' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/job-freeentry.svg',
    ],
    'ext-jobfair2-record-salarytable' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/salarytable.svg',
    ],
    'ext-jobfair2-record-salarygrade-stepped' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/salarygrade-stepped.svg',
    ],
    'ext-jobfair2-record-salarygrade-flat' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/salarygrade-flat.svg',
    ],
    'ext-jobfair2-record-salarystep' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobfair2/Resources/Public/Icons/salarystep.svg',
    ],
];
