<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\ApiModel;

/**
 * To be used in classes implementing ApiModelInterface only
 */
trait ApiModelTrait
{
    public function getName(): string
    {
        return self::NAME;
    }

    public function getApiEndpoint(): string
    {
        return self::API_ENDPOINT;
    }

    /**
     * @return ApiMapping[]
     */
    public function getMapping(): array
    {
        $mapping = [];

        foreach (self::MAPPING as $dbColumn => $configuration) {
            $mapping[$dbColumn] = new ApiMapping(
                $configuration['apiPath'],
                $configuration['isDate'],
                $configuration['default'],
                $configuration['prefix'] ?? '',
            );
        }

        return $mapping;
    }

    public function getStoragePid(): int
    {
        return $this->storagePid;
    }

    public function withStoragePid(int $storagePid): self
    {
        $clonedApiModel = clone $this;
        $clonedApiModel->storagePid = $storagePid;

        return $clonedApiModel;
    }
}
