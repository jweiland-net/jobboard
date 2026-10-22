<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The job areas a plugin is restricted to ("settings.jobAreas"). FlexForm
 * delivers the selected uids as comma-separated string, TypoScript may
 * deliver them as array. Without any job area, nothing is restricted.
 */
final readonly class JobAreaRestriction
{
    /**
     * @param int[] $jobAreaUids
     */
    private function __construct(
        private array $jobAreaUids,
    ) {}

    /**
     * @param array<string, mixed> $settings
     */
    public static function fromSettings(array $settings): self
    {
        $jobAreas = $settings['jobAreas'] ?? '';
        if (is_array($jobAreas)) {
            $jobAreas = implode(',', array_filter($jobAreas, is_scalar(...)));
        }

        if (!is_scalar($jobAreas)) {
            return new self([]);
        }

        $jobAreaUids = array_filter(
            GeneralUtility::intExplode(',', (string)$jobAreas, true),
            static fn(int $jobAreaUid): bool => $jobAreaUid > 0,
        );

        return new self(array_values(array_unique($jobAreaUids)));
    }

    public function isRestricted(): bool
    {
        return $this->jobAreaUids !== [];
    }

    /**
     * @return int[]
     */
    public function getJobAreaUids(): array
    {
        return $this->jobAreaUids;
    }

    public function allows(int $jobAreaUid): bool
    {
        return !$this->isRestricted() || in_array($jobAreaUid, $this->jobAreaUids, true);
    }
}
