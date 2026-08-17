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
    'ext-jobboard-record-job' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/job.svg',
    ],
    'ext-jobboard-record-job-import' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/job-import.svg',
    ],
    'ext-jobboard-record-job-grade' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/job-grade.svg',
    ],
    'ext-jobboard-record-job-freeentry' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/job-freeentry.svg',
    ],
    'ext-jobboard-record-salarytable' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/salarytable.svg',
    ],
    'ext-jobboard-record-salarygrade-stepped' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/salarygrade-stepped.svg',
    ],
    'ext-jobboard-record-salarygrade-flat' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/salarygrade-flat.svg',
    ],
    'ext-jobboard-record-salarystep' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:jobboard/Resources/Public/Icons/salarystep.svg',
    ],
];
