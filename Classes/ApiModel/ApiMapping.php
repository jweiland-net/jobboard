<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\ApiModel;

readonly class ApiMapping
{
    public function __construct(
        private string $apiPath,
        private bool $isDate = false,
        private int|string $default = '',
        private string $prefix = '',
    ) {}

    public function getApiPath(): string
    {
        return $this->apiPath;
    }

    public function isDate(): bool
    {
        return $this->isDate;
    }

    public function getDefault(): int|string
    {
        return $this->default;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
