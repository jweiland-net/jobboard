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
 * The expected values are the output of ICU (\NumberFormatter). The thousands
 * separator of de-CH depends on the ICU version: older versions use "’"
 * (U+2019), newer ones "'" (U+0027), see normalizeSwissGrouping().
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
    private static function createSettings(array $salarySettings): SiteSettings
    {
        return SiteSettings::createFromSettingsTree(['jobboard' => ['salary' => $salarySettings]]);
    }

    #[Test]
    public function currencyIsTheOnlyFormatSettingOfSiteSet(): void
    {
        $definitions = Yaml::parseFile(__DIR__ . '/../../../../Configuration/Sets/Jobboard/settings.definitions.yaml');
        $salarySettings = array_filter(
            $definitions['settings'],
            static fn(string $key): bool => str_starts_with($key, 'jobboard.salary.'),
            ARRAY_FILTER_USE_KEY,
        );

        self::assertSame(['jobboard.salary.required', SalaryNumberFormat::SETTING_CURRENCY], array_keys($salarySettings));
        self::assertSame(
            [
                'category' => 'jobboard.salary',
                'type' => 'string',
                'default' => SalaryNumberFormat::DEFAULT_CURRENCY,
                'options' => ['min' => 3, 'max' => 3],
            ],
            $salarySettings[SalaryNumberFormat::SETTING_CURRENCY],
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function formatDataProvider(): array
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
    #[DataProvider('formatDataProvider')]
    public function formatUsesNumberFormatOfLocale(string $locale, string $currency, string $expected): void
    {
        self::assertSame(
            $expected,
            self::normalizeSwissGrouping(SalaryNumberFormat::create($locale, $currency)->format(1234.56)),
        );
    }

    #[Test]
    public function formatUsesDecimalPlacesOfLocaleAndCurrency(): void
    {
        self::assertSame('1.234,50' . self::NBSP . '€', SalaryNumberFormat::create('de-DE')->format(1234.5));
        self::assertSame('1.235' . self::NBSP . '¥', SalaryNumberFormat::create('de-DE', 'JPY')->format(1234.56));
    }

    #[Test]
    public function formatFormatsZeroAndNegativeAmounts(): void
    {
        self::assertSame('0,00' . self::NBSP . '€', SalaryNumberFormat::create('de-DE')->format(0.0));
        self::assertSame('-1.234,56' . self::NBSP . '€', SalaryNumberFormat::create('de-DE')->format(-1234.56));
    }

    #[Test]
    public function createUsesEurForInvalidCurrency(): void
    {
        self::assertSame(SalaryNumberFormat::DEFAULT_CURRENCY, SalaryNumberFormat::create('de-DE', 'euro')->getCurrency());
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
        self::assertSame($expected, SalaryNumberFormat::getCurrencyFromSettings(self::createSettings($salarySettings)));
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function fromSettingsDataProvider(): array
    {
        return [
            'no settings in German' => [[], 'de-DE', '1.234,56' . self::NBSP . '€'],
            'no settings in English' => [[], 'en-US', '€1,234.56'],
            'currency in Swiss German' => [['currency' => 'CHF'], 'de-CH', 'CHF' . self::NBSP . "1'234.56"],
            'invalid currency falls back to EUR' => [['currency' => 'Franken'], 'de-DE', '1.234,56' . self::NBSP . '€'],
            'settings of earlier versions are ignored' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => "'", 'decimalSeparator' => '.', 'decimals' => 0],
                'de-DE',
                '1.234,56' . self::NBSP . '€',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     */
    #[Test]
    #[DataProvider('fromSettingsDataProvider')]
    public function fromSettingsUsesCurrencyOfSettingsAndGivenLocale(
        array $salarySettings,
        string $locale,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            self::normalizeSwissGrouping(
                SalaryNumberFormat::fromSettings(self::createSettings($salarySettings), $locale)->format(1234.56),
            ),
        );
    }
}
