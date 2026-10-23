<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\JobLocation;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the info window content of a job location on the map
 */
interface JobLocationInfoWindowRendererInterface
{
    public const TEMPLATE = 'Map/InfoWindow';

    public function render(JobLocation $jobLocation, ServerRequestInterface $request): string;
}
