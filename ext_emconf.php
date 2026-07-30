<?php

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Job fair 2',
    'description' => 'Job fair implementation using Maps2 and tt_address to display jobs',
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
