<?php

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Job Board',
    'description' => 'Displays a searchable list of job openings with map view and optional automatic import from external sources',
    'category' => 'plugin',
    'author' => 'Stefan Froemken',
    'author_mail' => 'projects@jweiland.net',
    'state' => 'alpha',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'maps2' => '*',
            'tt_address' => '*',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
