<?php

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

file_put_contents('/tmp/jobfair2-jsmodules-debug.log', date('H:i:s') . ' read via SAPI=' . PHP_SAPI . "\n", FILE_APPEND);

return [
    'dependencies' => [
        'backend',
    ],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@jweiland/jobfair2/' => 'EXT:jobfair2/Resources/Public/JavaScript/',
    ],
];
