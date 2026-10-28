<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\SalaryNumberFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * The expected values of the automatic format are the output of ICU
 * (\NumberFormatter). The thousands separator of de-CH depends on the ICU
 * version: older versions use "’" (U+2019), newer ones "'" (U+0027), see
 * normalizeSwissGrouping().
 */
final class SalaryNumberFormatTest extends UnitTestCase
{
    private const NBSP = "\u{00A0}";

    /**
     * Both are correct Swiss thousands separators, the ICU version decides
     */
    private static function normalizeSwissGrouping(string $formatted): string
    {
        return str_replace('’', "'", $formatted);
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    private function buildFromSettings(array $salarySettings, string $locale = 'de-DE'): SalaryNumberFormat
    {
        return SalaryNumberFormat::fromSettings(
            SiteSettings::createFromSettingsTree(['jobboard' => ['salary' => $salarySettings]]),
            $locale,
        );
    }

    #[Test]
    public function settingsAreDefinedInSiteSet(): void
    {
        $definitions = Yaml::parseFile(__DIR__ . '/../../../../Configuration/Sets/Jobboard/settings.definitions.yaml');
        $settings = $definitions['settings'];

        self::assertSame('jobboard.salary', $settings[SalaryNumberFormat::SETTING_FORMAT]['category'] ?? null);
        self::assertSame(SalaryNumberFormat::FORMAT_AUTOMATIC, $settings[SalaryNumberFormat::SETTING_FORMAT]['default'] ?? null);
        self::assertSame(
            [SalaryNumberFormat::FORMAT_AUTOMATIC, SalaryNumberFormat::FORMAT_CUSTOM],
            array_keys($settings[SalaryNumberFormat::SETTING_FORMAT]['enum'] ?? []),
        );
        self::assertSame(
            [
                'category' => 'jobboard.salary',
                'type' => 'string',
                'default' => SalaryNumberFormat::DEFAULT_CURRENCY,
                'options' => ['min' => 3, 'max' => 3],
            ],
            $settings[SalaryNumberFormat::SETTING_CURRENCY] ?? null,
        );
        self::assertSame(
            [
                'category' => 'jobboard.salary',
                'type' => 'int',
                'default' => SalaryNumberFormat::DEFAULT_DECIMALS,
                'options' => ['min' => 0, 'max' => SalaryNumberFormat::MAXIMUM_DECIMALS],
            ],
            $settings[SalaryNumberFormat::SETTING_DECIMALS] ?? null,
        );
        self::assertSame(
            ['category' => 'jobboard.salary', 'type' => 'string', 'default' => ',', 'options' => ['max' => 1]],
            $settings[SalaryNumberFormat::SETTING_THOUSANDS_SEPARATOR] ?? null,
        );
        self::assertSame(
            ['category' => 'jobboard.salary', 'type' => 'string', 'default' => '.', 'options' => ['min' => 1, 'max' => 1]],
            $settings[SalaryNumberFormat::SETTING_DECIMAL_SEPARATOR] ?? null,
        );
        self::assertSame('jobboard.salary', $settings[SalaryNumberFormat::SETTING_CURRENCY_POSITION]['category'] ?? null);
        self::assertSame(
            SalaryNumberFormat::CURRENCY_POSITION_AFTER,
            $settings[SalaryNumberFormat::SETTING_CURRENCY_POSITION]['default'] ?? null,
        );
        self::assertSame(
            [SalaryNumberFormat::CURRENCY_POSITION_AFTER, SalaryNumberFormat::CURRENCY_POSITION_BEFORE],
            array_keys($settings[SalaryNumberFormat::SETTING_CURRENCY_POSITION]['enum'] ?? []),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function automaticDataProvider(): array
    {
        return [
            'German, sign after the amount' => ['de-DE', 'EUR', '1.234,56' . self::NBSP . '€'],
            'English (US), sign before the amount' => ['en-US', 'EUR', '€1,234.56'],
            'Dutch, sign before the amount' => ['nl-NL', 'EUR', '€' . self::NBSP . '1.234,56'],
            'Swiss German with Swiss francs' => ['de-CH', 'CHF', 'CHF' . self::NBSP . "1'234.56"],
            'POSIX notation of the locale' => ['de_DE', 'EUR', '1.234,56' . self::NBSP . '€'],
            'US dollars in German' => ['de-DE', 'USD', '1.234,56' . self::NBSP . '$'],
        ];
    }

    #[Test]
    #[DataProvider('automaticDataProvider')]
    public function automaticFormatsWithNumberFormatOfLocale(string $locale, string $currency, string $expected): void
    {
        self::assertSame(
            $expected,
            self::normalizeSwissGrouping(SalaryNumberFormat::automatic($locale, $currency)->format(1234.56)),
        );
    }

    #[Test]
    public function automaticUsesGivenDecimalsAndRoundsHalfUp(): void
    {
        self::assertSame('1.235' . self::NBSP . '€', SalaryNumberFormat::automatic('de-DE', 'EUR', 0)->format(1234.5));
        self::assertSame('1.234,5600' . self::NBSP . '€', SalaryNumberFormat::automatic('de-DE', 'EUR', 4)->format(1234.56));
    }

    #[Test]
    public function automaticUsesDefaultsForInvalidCurrencyAndDecimals(): void
    {
        $salaryNumberFormat = SalaryNumberFormat::automatic('de-DE', 'euro', SalaryNumberFormat::MAXIMUM_DECIMALS + 1);

        self::assertSame(SalaryNumberFormat::DEFAULT_CURRENCY, $salaryNumberFormat->getCurrency());
        self::assertSame(SalaryNumberFormat::DEFAULT_DECIMALS, $salaryNumberFormat->getDecimals());
        self::assertFalse($salaryNumberFormat->isCustom());
    }

    #[Test]
    public function customFormatsWithGivenSeparatorsAndCurrencySignOfLocale(): void
    {
        self::assertSame("1'234.6" . self::NBSP . '€', SalaryNumberFormat::custom('de-DE', "'", '.', 1)->format(1234.56));
        self::assertSame(
            'CHF' . self::NBSP . "1'234.56",
            SalaryNumberFormat::custom('de-CH', "'", '.', 2, 'CHF', 'before')->format(1234.56),
        );
        self::assertSame(
            '1234,56' . self::NBSP . '$',
            SalaryNumberFormat::custom('en-US', '', ',', 2, 'USD')->format(1234.56),
        );
    }

    /**
     * @return array<string, array{string, string, int, string, string}>
     */
    public static function invalidCustomDataProvider(): array
    {
        return [
            'same separators' => ['.', '.', 2, 'EUR', 'after'],
            'invalid currency' => [',', '.', 2, 'Euro', 'after'],
            'invalid currency position' => [',', '.', 2, 'EUR', 'left'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCustomDataProvider')]
    public function customThrowsExceptionForInvalidCombination(
        string $thousandsSeparator,
        string $decimalSeparator,
        int $decimals,
        string $currency,
        string $currencyPosition,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1761484800);

        SalaryNumberFormat::custom('de-DE', $thousandsSeparator, $decimalSeparator, $decimals, $currency, $currencyPosition);
    }

    /**
     * @return array<string, array{string, string, int, bool}>
     */
    public static function isValidDataProvider(): array
    {
        return [
            'English' => [',', '.', 2, true],
            'German' => ['.', ',', 2, true],
            'apostrophe' => ["'", '.', 2, true],
            'space' => [' ', ',', 2, true],
            'narrow no-break space' => ["\u{202F}", ',', 2, true],
            'no grouping' => ['', '.', 2, true],
            'no decimals' => [',', '.', 0, true],
            'maximum decimals' => [',', '.', SalaryNumberFormat::MAXIMUM_DECIMALS, true],
            'same separators' => ['.', '.', 2, false],
            'same separators without decimals' => [',', ',', 0, false],
            'empty decimal separator' => [',', '', 2, false],
            'space as decimal separator' => ['.', ' ', 2, false],
            'two characters as thousands separator' => ['..', ',', 2, false],
            'two characters as decimal separator' => ['.', ',,', 2, false],
            'digit as thousands separator' => ['0', ',', 2, false],
            'digit as decimal separator' => ['.', '1', 2, false],
            'minus as thousands separator' => ['-', ',', 2, false],
            'plus as decimal separator' => ['.', '+', 2, false],
            'control character' => ["\t", ',', 2, false],
            'negative decimals' => [',', '.', -1, false],
            'too many decimals' => [',', '.', SalaryNumberFormat::MAXIMUM_DECIMALS + 1, false],
        ];
    }

    #[Test]
    #[DataProvider('isValidDataProvider')]
    public function isValidChecksSeparatorsAndDecimals(
        string $thousandsSeparator,
        string $decimalSeparator,
        int $decimals,
        bool $expected,
    ): void {
        self::assertSame($expected, SalaryNumberFormat::isValid($thousandsSeparator, $decimalSeparator, $decimals));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function isValidCurrencyDataProvider(): array
    {
        return [
            'EUR' => ['EUR', true],
            'CHF' => ['CHF', true],
            'lowercase' => ['eur', false],
            'sign' => ['€', false],
            'two letters' => ['EU', false],
            'four letters' => ['EURO', false],
            'empty' => ['', false],
        ];
    }

    #[Test]
    #[DataProvider('isValidCurrencyDataProvider')]
    public function isValidCurrencyChecksIsoCodeFormat(string $currency, bool $expected): void
    {
        self::assertSame($expected, SalaryNumberFormat::isValidCurrency($currency));
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function fromSettingsDataProvider(): array
    {
        return [
            'no settings in German' => [[], 'de-DE', '1.234,56' . self::NBSP . '€'],
            'no settings in English' => [[], 'en-US', '€1,234.56'],
            'automatic in Dutch' => [['numberFormat' => 'automatic'], 'nl-NL', '€' . self::NBSP . '1.234,56'],
            'automatic with currency' => [['currency' => 'CHF'], 'de-CH', 'CHF' . self::NBSP . "1'234.56"],
            'automatic with decimals' => [['decimals' => 0], 'de-DE', '1.235' . self::NBSP . '€'],
            'decimals as string' => [['decimals' => '1'], 'de-DE', '1.234,6' . self::NBSP . '€'],
            'invalid decimals' => [['decimals' => 9], 'de-DE', '1.234,56' . self::NBSP . '€'],
            'decimals no number' => [['decimals' => 'two'], 'de-DE', '1.234,56' . self::NBSP . '€'],
            // The Swiss locale writes the code instead of the euro sign
            'invalid currency falls back to EUR' => [['currency' => 'Franken'], 'de-CH', 'EUR' . self::NBSP . "1'234.56"],
            'currency position is ignored by automatic format' => [
                ['currencyPosition' => 'before'],
                'de-DE',
                '1.234,56' . self::NBSP . '€',
            ],
            'custom' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => "'", 'decimalSeparator' => '.', 'decimals' => 1],
                'de-DE',
                "1'234.6" . self::NBSP . '€',
            ],
            'custom with currency before the amount' => [
                [
                    'numberFormat' => 'custom',
                    'thousandsSeparator' => "'",
                    'decimalSeparator' => '.',
                    'currency' => 'CHF',
                    'currencyPosition' => 'before',
                ],
                'de-CH',
                'CHF' . self::NBSP . "1'234.56",
            ],
            'custom without grouping' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => '', 'decimalSeparator' => ','],
                'en-US',
                '1234,56' . self::NBSP . '€',
            ],
            'custom with default separators' => [['numberFormat' => 'custom'], 'de-DE', '1,234.56' . self::NBSP . '€'],
            'invalid custom with same separators falls back to automatic' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => ',', 'decimalSeparator' => ','],
                'de-DE',
                '1.234,56' . self::NBSP . '€',
            ],
            'invalid custom with digit falls back to automatic' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => 1, 'decimalSeparator' => '0'],
                'en-US',
                '€1,234.56',
            ],
            'invalid currency position falls back to automatic' => [
                ['numberFormat' => 'custom', 'currencyPosition' => 'left'],
                'nl-NL',
                '€' . self::NBSP . '1.234,56',
            ],
            'removed format "german" falls back to automatic' => [['numberFormat' => 'german'], 'en-US', '€1,234.56'],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    #[Test]
    #[DataProvider('fromSettingsDataProvider')]
    public function fromSettingsSelectsNumberFormat(array $salarySettings, string $locale, string $expected): void
    {
        self::assertSame(
            $expected,
            self::normalizeSwissGrouping($this->buildFromSettings($salarySettings, $locale)->format(1234.56)),
        );
    }

    #[Test]
    public function fromSettingsPrefersValidGivenCurrency(): void
    {
        $settings = SiteSettings::createFromSettingsTree(['jobboard' => ['salary' => ['currency' => 'USD']]]);

        self::assertSame('CHF', SalaryNumberFormat::fromSettings($settings, 'de-CH', 'CHF')->getCurrency());
        self::assertSame('USD', SalaryNumberFormat::fromSettings($settings, 'de-CH', 'chf')->getCurrency());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function getCurrencyFromSettingsDataProvider(): array
    {
        return [
            'not set' => [[], 'EUR'],
            'valid code' => [['currency' => 'CHF'], 'CHF'],
            'invalid code' => [['currency' => 'chf'], 'EUR'],
            'no string' => [['currency' => 756], 'EUR'],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    #[Test]
    #[DataProvider('getCurrencyFromSettingsDataProvider')]
    public function getCurrencyFromSettingsReturnsValidCodeOrEur(array $salarySettings, string $expected): void
    {
        self::assertSame(
            $expected,
            SalaryNumberFormat::getCurrencyFromSettings(
                SiteSettings::createFromSettingsTree(['jobboard' => ['salary' => $salarySettings]]),
            ),
        );
    }

    #[Test]
    public function formatFormatsZeroAndNegativeAmounts(): void
    {
        self::assertSame('0,00' . self::NBSP . '€', SalaryNumberFormat::automatic('de-DE')->format(0.0));
        self::assertSame('-1.234,56' . self::NBSP . '€', SalaryNumberFormat::automatic('de-DE')->format(-1234.56));
        self::assertSame('-1,234.56' . self::NBSP . '€', SalaryNumberFormat::custom('de-DE', ',', '.', 2)->format(-1234.56));
    }
}
