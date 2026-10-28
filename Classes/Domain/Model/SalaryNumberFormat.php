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
 * How salary amounts are displayed, including the currency. Site settings
 * "jobboard.salary.numberFormat" (automatic or custom),
 * "jobboard.salary.decimals", "jobboard.salary.currency" and, for the
 * custom format only, "jobboard.salary.thousandsSeparator",
 * "jobboard.salary.decimalSeparator" and "jobboard.salary.currencyPosition".
 *
 * The automatic format uses \NumberFormatter (ICU) with the locale of the
 * page: separators, grouping and the position of the currency sign follow
 * the locale, e.g. "1.234,56 €" (de-DE), "€ 1.234,56" (nl-NL) or
 * "CHF 1’234.56" (de-CH). The custom format uses the configured separators
 * and puts the currency sign of the locale before or after the amount.
 *
 * Only the display is affected, never the stored salary values. Machine
 * readable output (JSON-LD) keeps the plain numbers. An invalid
 * configuration never breaks the output: an invalid custom format falls
 * back to the automatic format, an invalid currency to EUR.
 */
final readonly class SalaryNumberFormat
{
    public const SETTING_FORMAT = 'jobboard.salary.numberFormat';
    public const SETTING_DECIMALS = 'jobboard.salary.decimals';
    public const SETTING_CURRENCY = 'jobboard.salary.currency';
    public const SETTING_THOUSANDS_SEPARATOR = 'jobboard.salary.thousandsSeparator';
    public const SETTING_DECIMAL_SEPARATOR = 'jobboard.salary.decimalSeparator';
    public const SETTING_CURRENCY_POSITION = 'jobboard.salary.currencyPosition';

    public const FORMAT_AUTOMATIC = 'automatic';
    public const FORMAT_CUSTOM = 'custom';

    public const CURRENCY_POSITION_AFTER = 'after';
    public const CURRENCY_POSITION_BEFORE = 'before';

    public const DEFAULT_DECIMALS = 2;
    public const MAXIMUM_DECIMALS = 4;
    public const DEFAULT_CURRENCY = 'EUR';

    /**
     * Separates the amount and the currency sign of the custom format, like
     * the automatic format of most locales
     */
    private const NO_BREAK_SPACE = "\u{00A0}";

    private function __construct(
        private string $locale,
        private string $currency,
        private int $decimals,
        private ?string $thousandsSeparator = null,
        private ?string $decimalSeparator = null,
        private string $currencyPosition = self::CURRENCY_POSITION_AFTER,
    ) {}

    /**
     * Separators, grouping and position of the currency sign of the locale
     *
     * @param string $locale Locale of the page, e.g. "de-DE" or "de_CH"
     * @param string $currency ISO 4217 currency code, e.g. "EUR"
     */
    public static function automatic(
        string $locale,
        string $currency = self::DEFAULT_CURRENCY,
        int $decimals = self::DEFAULT_DECIMALS,
    ): self {
        return new self(
            $locale,
            self::isValidCurrency($currency) ? $currency : self::DEFAULT_CURRENCY,
            self::isValidDecimals($decimals) ? $decimals : self::DEFAULT_DECIMALS,
        );
    }

    /**
     * Given separators, the currency sign of the locale before or after the amount
     *
     * @throws \InvalidArgumentException if the combination is not valid, see isValid() and isValidCurrency()
     */
    public static function custom(
        string $locale,
        string $thousandsSeparator,
        string $decimalSeparator,
        int $decimals,
        string $currency = self::DEFAULT_CURRENCY,
        string $currencyPosition = self::CURRENCY_POSITION_AFTER,
    ): self {
        if (
            !self::isValid($thousandsSeparator, $decimalSeparator, $decimals)
            || !self::isValidCurrency($currency)
            || !self::isValidCurrencyPosition($currencyPosition)
        ) {
            throw new \InvalidArgumentException('Invalid custom salary number format', 1761484800);
        }

        return new self($locale, $currency, $decimals, $thousandsSeparator, $decimalSeparator, $currencyPosition);
    }

    /**
     * Valid, if
     * - the decimal separator is exactly one character, but no whitespace,
     * - the thousands separator is empty (no grouping) or one character, a
     *   (non-breaking) space is allowed,
     * - no separator is a digit, a plus or minus sign or a control character,
     * - both separators are different and
     * - the number of decimals is between 0 and MAXIMUM_DECIMALS.
     */
    public static function isValid(string $thousandsSeparator, string $decimalSeparator, int $decimals): bool
    {
        if (!self::isValidDecimals($decimals)) {
            return false;
        }
        if (mb_strlen($decimalSeparator) !== 1 || preg_match('/^[\s\p{Z}]$/u', $decimalSeparator) === 1) {
            return false;
        }
        if (mb_strlen($thousandsSeparator) > 1) {
            return false;
        }
        foreach ([$thousandsSeparator, $decimalSeparator] as $separator) {
            if ($separator !== '' && preg_match('/^[\d+\-\p{C}]$/u', $separator) !== 0) {
                return false;
            }
        }

        return $thousandsSeparator !== $decimalSeparator;
    }

    /**
     * Three uppercase letters, the format of an ISO 4217 code like "EUR" or "CHF"
     */
    public static function isValidCurrency(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/', $currency) === 1;
    }

    public static function isValidCurrencyPosition(string $currencyPosition): bool
    {
        return in_array($currencyPosition, [self::CURRENCY_POSITION_AFTER, self::CURRENCY_POSITION_BEFORE], true);
    }

    /**
     * @param string $locale Locale of the page, e.g. "de-DE"
     * @param string $currency ISO 4217 code instead of site setting "jobboard.salary.currency", ignored if not valid
     */
    public static function fromSettings(SiteSettings $settings, string $locale, string $currency = ''): self
    {
        $decimals = self::getDecimalsFromSettings($settings);
        if (!self::isValidCurrency($currency)) {
            $currency = self::getCurrencyFromSettings($settings);
        }

        if (self::getStringFromSettings($settings, self::SETTING_FORMAT, self::FORMAT_AUTOMATIC) === self::FORMAT_CUSTOM) {
            $thousandsSeparator = self::getStringFromSettings($settings, self::SETTING_THOUSANDS_SEPARATOR, ',');
            $decimalSeparator = self::getStringFromSettings($settings, self::SETTING_DECIMAL_SEPARATOR, '.');
            $currencyPosition = self::getStringFromSettings(
                $settings,
                self::SETTING_CURRENCY_POSITION,
                self::CURRENCY_POSITION_AFTER,
            );
            if (
                self::isValid($thousandsSeparator, $decimalSeparator, $decimals)
                && self::isValidCurrencyPosition($currencyPosition)
            ) {
                return new self($locale, $currency, $decimals, $thousandsSeparator, $decimalSeparator, $currencyPosition);
            }
        }

        return new self($locale, $currency, $decimals);
    }

    /**
     * ISO 4217 code of site setting "jobboard.salary.currency", EUR if it is not valid
     */
    public static function getCurrencyFromSettings(SiteSettings $settings): string
    {
        $currency = self::getStringFromSettings($settings, self::SETTING_CURRENCY, self::DEFAULT_CURRENCY);

        return self::isValidCurrency($currency) ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * The amount with the currency sign, e.g. "1.234,56 €"
     */
    public function format(float $amount): string
    {
        if ($this->isCustom()) {
            $number = number_format($amount, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
            $currencySign = $this->getCurrencySign();

            return $this->currencyPosition === self::CURRENCY_POSITION_BEFORE
                ? $currencySign . self::NO_BREAK_SPACE . $number
                : $number . self::NO_BREAK_SPACE . $currencySign;
        }

        $formatter = $this->createCurrencyFormatter();
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $this->decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $this->decimals);
        // Like number_format() of the custom format. ICU rounds half to even by default.
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
        $formatted = $formatter->formatCurrency($amount, $this->currency);

        return is_string($formatted)
            ? $formatted
            : number_format($amount, $this->decimals, '.', ',') . self::NO_BREAK_SPACE . $this->currency;
    }

    public function isCustom(): bool
    {
        return $this->decimalSeparator !== null;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    public function getThousandsSeparator(): ?string
    {
        return $this->thousandsSeparator;
    }

    public function getDecimalSeparator(): ?string
    {
        return $this->decimalSeparator;
    }

    public function getCurrencyPosition(): string
    {
        return $this->currencyPosition;
    }

    /**
     * Currency sign of the locale, e.g. "€" for EUR or "CHF" for CHF
     */
    private function getCurrencySign(): string
    {
        $formatter = $this->createCurrencyFormatter();
        $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $this->currency);
        $currencySign = $formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

        return is_string($currencySign) && $currencySign !== '' ? $currencySign : $this->currency;
    }

    private function createCurrencyFormatter(): \NumberFormatter
    {
        return new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
    }

    private static function getDecimalsFromSettings(SiteSettings $settings): int
    {
        $decimals = $settings->get(self::SETTING_DECIMALS, self::DEFAULT_DECIMALS);
        if (is_string($decimals) && preg_match('/^\d+$/', $decimals) === 1) {
            $decimals = (int)$decimals;
        }

        return is_int($decimals) && self::isValidDecimals($decimals) ? $decimals : self::DEFAULT_DECIMALS;
    }

    private static function getStringFromSettings(SiteSettings $settings, string $identifier, string $default): string
    {
        $value = $settings->get($identifier, $default);

        return is_string($value) ? $value : $default;
    }

    private static function isValidDecimals(int $decimals): bool
    {
        return $decimals >= 0 && $decimals <= self::MAXIMUM_DECIMALS;
    }
}
