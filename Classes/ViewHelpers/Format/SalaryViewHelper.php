<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ViewHelpers\Format;

use JWeiland\Jobboard\Domain\Model\SalaryNumberFormat;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Formats a salary amount with \NumberFormatter, see SalaryNumberFormat: the
 * number format of the locale of the page, e.g. "1.234,56 €" (de-DE),
 * "€ 1.234,56" (nl-NL) or "€1,234.56" (en-US), and the currency of site
 * setting "jobboard.salary.currency" (default EUR).
 *
 * Only for display: use the plain value for machine readable output. For an
 * own format, use f:format.number in an overridden template.
 *
 * <jobboard:format.salary>{job.salaryRangeMin}</jobboard:format.salary>
 * {job.salaryRangeMin -> jobboard:format.salary()}
 */
final class SalaryViewHelper extends AbstractViewHelper
{
    /**
     * Locale, if the request has no site language, e.g. in the backend
     */
    private const DEFAULT_LOCALE = 'en-US';

    public function initializeArguments(): void
    {
        $this->registerArgument('amount', 'float', 'Salary amount. Default: the content of the tag');
    }

    public function getContentArgumentName(): string
    {
        return 'amount';
    }

    public function render(): string
    {
        $amount = $this->renderChildren();

        return $this->getSalaryNumberFormat()->format(is_numeric($amount) ? (float)$amount : 0.0);
    }

    private function getSalaryNumberFormat(): SalaryNumberFormat
    {
        $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;

        $site = $request?->getAttribute('site');
        $settings = $site instanceof Site ? $site->getSettings() : SiteSettings::createFromSettingsTree([]);

        return SalaryNumberFormat::fromSettings($settings, $this->getLocale($request, $site));
    }

    /**
     * Locale of the site language of the request, e.g. "de-CH"
     */
    private function getLocale(?ServerRequestInterface $request, mixed $site): string
    {
        $language = $request?->getAttribute('language');
        if (!$language instanceof SiteLanguage && $site instanceof Site) {
            $language = $site->getDefaultLanguage();
        }

        return $language instanceof SiteLanguage ? $language->getLocale()->getName() : self::DEFAULT_LOCALE;
    }
}
