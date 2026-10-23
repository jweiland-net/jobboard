<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads and writes the jobs a visitor has remembered in the job list.
 */
interface RememberedJobsServiceInterface
{
    public function getRememberedJobs(ServerRequestInterface $request): RememberedJobs;

    public function storeRememberedJobs(ServerRequestInterface $request, RememberedJobs $rememberedJobs): void;
}
