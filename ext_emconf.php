<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Job fair 2',
    'description' => 'Job fair implementation using Maps2 and tt_address to display jobs',
    'category' => 'plugin',
    'author' => 'Markus Kugler',
    'author_mail' => 'projects@ma-ku.eu',
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
