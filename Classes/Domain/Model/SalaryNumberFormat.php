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
 * How salary amounts are displayed: thousands separator, decimal separator
 * and number of decimal places. Site settings "jobboard.salary.numberFormat"
 * (automatic, english, german or custom), "jobboard.salary.decimals" and,
 * for the custom format only, "jobboard.salary.thousandsSeparator" and
 * "jobboard.salary.decimalSeparator".
 *
 * Only the display is affected, never the stored salary values. Machine
 * readable output (JSON-LD) keeps the plain numbers.
 *
 * The automatic format uses the separators of the language of the page,
 * which are translated like "dateFormat" (labels "salary.thousandsSeparator"
 * and "salary.decimalSeparator" of locallang.xlf). An invalid configuration
 * never breaks the output: an invalid custom format falls back to the
 * automatic format, invalid separators of a language to the English format.
 */
final readonly class SalaryNumberFormat
{
    public const SETTING_FORMAT = 'jobboard.salary.numberFormat';
    public const SETTING_DECIMALS = 'jobboard.salary.decimals';
    public const SETTING_THOUSANDS_SEPARATOR = 'jobboard.salary.thousandsSeparator';
    public const SETTING_DECIMAL_SEPARATOR = 'jobboard.salary.decimalSeparator';

    public const FORMAT_AUTOMATIC = 'automatic';
    public const FORMAT_ENGLISH = 'english';
    public const FORMAT_GERMAN = 'german';
    public const FORMAT_CUSTOM = 'custom';

    public const DEFAULT_DECIMALS = 2;
    public const MAXIMUM_DECIMALS = 4;

    private function __construct(
        private string $thousandsSeparator,
        private string $decimalSeparator,
        private int $decimals,
    ) {}

    /**
     * 1,234.56
     */
    public static function english(int $decimals = self::DEFAULT_DECIMALS): self
    {
        return new self(',', '.', self::isValidDecimals($decimals) ? $decimals : self::DEFAULT_DECIMALS);
    }

    /**
     * 1.234,56
     */
    public static function german(int $decimals = self::DEFAULT_DECIMALS): self
    {
        return new self('.', ',', self::isValidDecimals($decimals) ? $decimals : self::DEFAULT_DECIMALS);
    }

    /**
     * @throws \InvalidArgumentException if the combination is not valid, see isValid()
     */
    public static function custom(string $thousandsSeparator, string $decimalSeparator, int $decimals): self
    {
        if (!self::isValid($thousandsSeparator, $decimalSeparator, $decimals)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid salary number format: thousands separator "%s", decimal separator "%s", %d decimals',
                    $thousandsSeparator,
                    $decimalSeparator,
                    $decimals,
                ),
                1761484800,
            );
        }

        return new self($thousandsSeparator, $decimalSeparator, $decimals);
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
     * @param string $languageThousandsSeparator Thousands separator of the language of the page
     * @param string $languageDecimalSeparator Decimal separator of the language of the page
     */
    public static function fromSettings(
        SiteSettings $settings,
        string $languageThousandsSeparator,
        string $languageDecimalSeparator,
    ): self {
        $decimals = self::getDecimalsFromSettings($settings);
        $automatic = self::isValid($languageThousandsSeparator, $languageDecimalSeparator, $decimals)
            ? new self($languageThousandsSeparator, $languageDecimalSeparator, $decimals)
            : self::english($decimals);

        return match (self::getStringFromSettings($settings, self::SETTING_FORMAT, self::FORMAT_AUTOMATIC)) {
            self::FORMAT_ENGLISH => self::english($decimals),
            self::FORMAT_GERMAN => self::german($decimals),
            self::FORMAT_CUSTOM => self::fromCustomSettings($settings, $decimals) ?? $automatic,
            default => $automatic,
        };
    }

    public function format(float $amount): string
    {
        return number_format($amount, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
    }

    public function getThousandsSeparator(): string
    {
        return $this->thousandsSeparator;
    }

    public function getDecimalSeparator(): string
    {
        return $this->decimalSeparator;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    private static function fromCustomSettings(SiteSettings $settings, int $decimals): ?self
    {
        $thousandsSeparator = self::getStringFromSettings($settings, self::SETTING_THOUSANDS_SEPARATOR, ',');
        $decimalSeparator = self::getStringFromSettings($settings, self::SETTING_DECIMAL_SEPARATOR, '.');

        return self::isValid($thousandsSeparator, $decimalSeparator, $decimals)
            ? new self($thousandsSeparator, $decimalSeparator, $decimals)
            : null;
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
