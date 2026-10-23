<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds frontend URIs to jobs without Extbase (middlewares, event listeners)
 */
interface JobUriBuilderInterface
{
    /**
     * URI of the detail view of the job in the language of the request.
     * Target is the page of the site setting "jobboard.pidOfDetailPage", if
     * set, otherwise the page of the request. An empty string, if no URI
     * could be built.
     *
     * @param int $jobUid UID of the job in the default language
     */
    public function buildDetailUri(int $jobUid, ServerRequestInterface $request): string;
}
