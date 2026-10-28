<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * How salary amounts are displayed: \NumberFormatter (ICU) with the locale
 * of the page and the currency of site setting "jobboard.salary.currency".
 * Separators, decimal places and the position of the currency sign follow
 * the locale, e.g. "1.234,56 €" (de-DE), "€ 1.234,56" (nl-NL) or
 * "CHF 1'234.56" (de-CH).
 *
 * Only the display is affected, never the stored salary values. Machine
 * readable output (JSON-LD) keeps the plain numbers and uses the same
 * currency code. An invalid currency is replaced by EUR.
 */
final readonly class SalaryNumberFormat
{
    public const SETTING_CURRENCY = 'jobboard.salary.currency';
    public const DEFAULT_CURRENCY = 'EUR';

    private function __construct(
        private string $locale,
        private string $currency,
    ) {}

    /**
     * @param string $locale Locale of the page, e.g. "de-DE" or "de_CH"
     * @param string $currency ISO 4217 currency code, e.g. "EUR"
     */
    public static function create(string $locale, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self($locale, self::isValidCurrency($currency) ? $currency : self::DEFAULT_CURRENCY);
    }

    /**
     * @param string $locale Locale of the page, e.g. "de-DE"
     */
    public static function fromSettings(SiteSettings $settings, string $locale): self
    {
        return new self($locale, self::getCurrencyFromSettings($settings));
    }

    /**
     * Three uppercase letters, the format of an ISO 4217 code like "EUR" or "CHF"
     */
    public static function isValidCurrency(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', $currency) === 1;
    }

    /**
     * ISO 4217 code of site setting "jobboard.salary.currency", EUR if it is not valid
     */
    public static function getCurrencyFromSettings(SiteSettings $settings): string
    {
        $currency = $settings->get(self::SETTING_CURRENCY, self::DEFAULT_CURRENCY);

        return is_string($currency) && self::isValidCurrency($currency) ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * The amount with the currency sign, e.g. "1.234,56 €"
     */
    public function format(float $amount): string
    {
        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount, $this->currency);

        return is_string($formatted)
            ? $formatted
            : number_format($amount, 2, '.', ',') . "\u{00A0}" . $this->currency;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
