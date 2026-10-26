<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Whether a job needs salary information (effective salary min or max > 0)
 * to be listed, shown, searched, remembered and placed on the map (EU pay
 * transparency directive). Site setting "jobboard.salary.required" of the
 * site of the request, default true.
 *
 * Read from the site only, never from TypoScript or FlexForm: every entry
 * point (Extbase plugin, middlewares, map info window) has to apply the
 * same rule, so a single content element can not override it.
 */
final readonly class SalaryRequirement
{
    public const SETTING = 'jobboard.salary.required';

    public function __construct(
        private bool $required,
    ) {}

    /**
     * Without site (e.g. no site found), salary information is required.
     */
    public static function fromRequest(ServerRequestInterface $request): self
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return new self(true);
        }

        return new self((bool)$site->getSettings()->get(self::SETTING, true));
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
