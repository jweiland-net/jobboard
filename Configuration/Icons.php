<?php

declare(strict_types=1);

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
];
