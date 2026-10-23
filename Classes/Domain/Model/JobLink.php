<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

/**
 * Title and link of an open job, as listed in the info window of a job
 * location on the map. An external link (imported jobs) is opened in a new
 * tab like in the job list. The URI may be empty, if no link could be built.
 */
final readonly class JobLink
{
    public function __construct(
        private int $uid,
        private string $title,
        private string $uri,
        private bool $external,
    ) {}

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getIsExternal(): bool
    {
        return $this->external;
    }
}
