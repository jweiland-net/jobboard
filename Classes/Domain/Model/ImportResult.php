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
 * Result of an import run. Pure value object without Extbase.
 *
 * The job UIDs are the UIDs of all jobs still delivered by their API model,
 * including existing jobs which could not be updated. A failed API model
 * could not be fetched completely, its jobs are kept.
 */
final readonly class ImportResult
{
    /**
     * @param int[] $jobUids
     */
    public function __construct(
        private array $jobUids = [],
        private int $numberOfFailedJobs = 0,
        private int $numberOfFailedApiModels = 0,
    ) {}

    public function withJobUid(int $jobUid): self
    {
        return new self([...$this->jobUids, $jobUid], $this->numberOfFailedJobs, $this->numberOfFailedApiModels);
    }

    public function withFailedJob(): self
    {
        return new self($this->jobUids, $this->numberOfFailedJobs + 1, $this->numberOfFailedApiModels);
    }

    public function merge(self $importResult): self
    {
        return new self(
            [...$this->jobUids, ...$importResult->getJobUids()],
            $this->numberOfFailedJobs + $importResult->getNumberOfFailedJobs(),
            $this->numberOfFailedApiModels + $importResult->getNumberOfFailedApiModels(),
        );
    }

    /**
     * @return int[]
     */
    public function getJobUids(): array
    {
        return $this->jobUids;
    }

    public function getNumberOfFailedJobs(): int
    {
        return $this->numberOfFailedJobs;
    }

    public function getNumberOfFailedApiModels(): int
    {
        return $this->numberOfFailedApiModels;
    }

    public function hasErrors(): bool
    {
        return $this->numberOfFailedJobs > 0 || $this->numberOfFailedApiModels > 0;
    }
}
