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
 * UIDs of the jobs a visitor has remembered in the job list, in the order
 * they were remembered. Pure value object: it does not know where the UIDs
 * are stored (see RememberedJobsService) and does not use Extbase.
 *
 * The UIDs are the UIDs of the jobs in the default language, which are valid
 * in every language of the site.
 */
final readonly class RememberedJobs
{
    /**
     * Maximum number of remembered jobs per visitor. Limits the size of the
     * session data, which is written on behalf of anonymous visitors.
     */
    public const MAXIMUM = 50;

    /**
     * @param int[] $uids
     */
    private function __construct(
        private array $uids,
    ) {}

    public static function createEmpty(): self
    {
        return new self([]);
    }

    /**
     * Session data is not trusted: everything but positive integers is
     * dropped, duplicates are removed and the list is cut to MAXIMUM.
     */
    public static function fromSessionData(mixed $sessionData): self
    {
        if (!is_array($sessionData)) {
            return self::createEmpty();
        }

        $uids = array_filter(
            $sessionData,
            static fn(mixed $uid): bool => is_int($uid) && $uid > 0,
        );

        return new self(array_slice(array_values(array_unique($uids)), 0, self::MAXIMUM));
    }

    /**
     * @return int[]
     */
    public function getUids(): array
    {
        return $this->uids;
    }

    /**
     * UIDs as keys, so Fluid can look up a job with
     * {rememberedJobs.{job.uid}}
     *
     * @return array<int, bool>
     */
    public function getUidsAsKeys(): array
    {
        return array_fill_keys($this->uids, true);
    }

    public function contains(int $uid): bool
    {
        return in_array($uid, $this->uids, true);
    }

    public function isEmpty(): bool
    {
        return $this->uids === [];
    }

    public function isFull(): bool
    {
        return count($this->uids) >= self::MAXIMUM;
    }

    public function equals(self $rememberedJobs): bool
    {
        return $this->uids === $rememberedJobs->getUids();
    }

    /**
     * @throws \OverflowException if MAXIMUM jobs are remembered already
     */
    public function with(int $uid): self
    {
        if ($this->contains($uid)) {
            return $this;
        }

        if ($this->isFull()) {
            throw new \OverflowException(
                'Not more than ' . self::MAXIMUM . ' jobs can be remembered.',
                1761210001,
            );
        }

        return new self([...$this->uids, $uid]);
    }

    public function without(int $uid): self
    {
        return new self(array_values(array_diff($this->uids, [$uid])));
    }

    /**
     * Keeps only the given UIDs, e.g. the UIDs of jobs that are still
     * visible. Keeps the order in which the jobs were remembered.
     *
     * @param int[] $uids
     */
    public function intersect(array $uids): self
    {
        return new self(array_values(array_intersect($this->uids, $uids)));
    }
}
