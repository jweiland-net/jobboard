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
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Formats a salary amount with the number format of the site settings
 * "jobboard.salary.*", see SalaryNumberFormat. Without configuration, the
 * separators of the language of the page are used: 1,234.56 in English,
 * 1.234,56 in German.
 *
 * Only for display: use the plain value for machine readable output.
 *
 * <jobboard:format.salary currencySign="€">{job.salaryRangeMin}</jobboard:format.salary>
 * {job.salaryRangeMin -> jobboard:format.salary(currencySign: '€')}
 */
final class SalaryViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly Locales $locales,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('amount', 'float', 'Salary amount. Default: the content of the tag');
        $this->registerArgument('currencySign', 'string', 'Currency sign, appended with a space, e.g. "€"', false, '');
    }

    public function getContentArgumentName(): string
    {
        return 'amount';
    }

    public function render(): string
    {
        $amount = $this->renderChildren();
        $output = $this->getSalaryNumberFormat()->format(is_numeric($amount) ? (float)$amount : 0.0);

        $currencySign = (string)$this->arguments['currencySign'];

        return $currencySign === '' ? $output : $output . ' ' . $currencySign;
    }

    private function getSalaryNumberFormat(): SalaryNumberFormat
    {
        $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;

        $site = $request?->getAttribute('site');
        $settings = $site instanceof Site ? $site->getSettings() : SiteSettings::createFromSettingsTree([]);

        // Same locale as f:translate, so the separators match the other labels of the page
        $locale = $request instanceof ServerRequestInterface ? $this->locales->createLocaleFromRequest($request) : null;

        return SalaryNumberFormat::fromSettings(
            $settings,
            $this->translate('salary.thousandsSeparator', $locale),
            $this->translate('salary.decimalSeparator', $locale),
        );
    }

    private function translate(string $key, ?Locale $locale): string
    {
        return (string)LocalizationUtility::translate($key, 'Jobboard', null, $locale);
    }
}
