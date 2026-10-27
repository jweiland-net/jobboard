<?php

declare(strict_types=1);

/*
 * This file is part of the package my-vendor/my-sitepackage.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MyVendor\MySitepackage\Import;

use JWeiland\Jobboard\ApiModel\ApiModelInterface;
use JWeiland\Jobboard\ApiModel\ApiModelTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(tags: ['api.model'], shared: false)]
final class ExampleJobBoardApiModel implements ApiModelInterface
{
    use ApiModelTrait;

    private const NAME = 'example';

    private const API_ENDPOINT = 'https://example.org/api/jobs.xml';

    private const MAPPING = [
        'title' => [
            'apiPath' => 'title/de',
            'isDate' => false,
            'default' => '',
        ],
        'description' => [
            'apiPath' => 'description/de',
            'isDate' => false,
            'default' => '',
        ],
        'reference_number' => [
            'apiPath' => 'vacancy_id',
            'isDate' => false,
            'default' => '',
        ],
        'employer' => [
            'apiPath' => 'company/de',
            'isDate' => false,
            'default' => '',
        ],
        'email' => [
            'apiPath' => 'application_email',
            'isDate' => false,
            'default' => '',
        ],
        'link' => [
            'apiPath' => 'publication_link',
            'isDate' => false,
            'default' => '',
        ],
        // Titles, resolved to (or created as) occupational field records
        'job_areas' => [
            'apiPath' => 'custom_select_4/de',
            'isDate' => false,
            'default' => '',
        ],
        // Title, resolved to (or created as) an employment scope record
        'job_type' => [
            'apiPath' => 'custom_select_2/de',
            'isDate' => false,
            'default' => '',
        ],
        'start_date' => [
            'apiPath' => 'start_date',
            'isDate' => true,
            'default' => 0,
        ],
        'application_deadline' => [
            'apiPath' => 'application_deadline',
            'isDate' => true,
            'default' => 0,
        ],
        'salary_mode' => [
            'apiPath' => 'salary_mode',
            'isDate' => false,
            'default' => 1,
        ],
        'salary_min' => [
            'apiPath' => 'salary/min',
            'isDate' => false,
            'default' => 0,
        ],
        'salary_max' => [
            'apiPath' => 'salary/max',
            'isDate' => false,
            'default' => 0,
        ],
        'vacancy_id' => [
            'apiPath' => 'vacancy_id',
            'isDate' => false,
            'default' => 0,
            'prefix' => self::NAME,
        ],
    ];

    private int $storagePid = 0;
}
