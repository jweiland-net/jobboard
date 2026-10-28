<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ViewHelpers;

use JWeiland\Jobboard\Domain\Model\SalaryNumberFormat;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the ISO 4217 code of the salary currency, site setting
 * "jobboard.salary.currency" (EUR if not set or not valid). The same
 * currency as jobboard:format.salary, e.g. for the structured data:
 *
 * "currency": <f:format.json value="{jobboard:salaryCurrency()}"/>
 */
final class SalaryCurrencyViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;

        $site = $request?->getAttribute('site');

        return SalaryNumberFormat::getCurrencyFromSettings(
            $site instanceof Site ? $site->getSettings() : SiteSettings::createFromSettingsTree([]),
        );
    }
}
