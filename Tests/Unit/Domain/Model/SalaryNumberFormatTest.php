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
 */
final class SalaryNumberFormatTest extends UnitTestCase
{
    /**
     * Separators of the labels "salary.thousandsSeparator" and
     * "salary.decimalSeparator" in English and German
     */
    private const ENGLISH_SEPARATORS = [',', '.'];
    private const GERMAN_SEPARATORS = ['.', ','];

    /**
     * @param array<string, mixed> $salarySettings
     * @param array{string, string} $languageSeparators
     */
    private function buildFromSettings(array $salarySettings, array $languageSeparators): SalaryNumberFormat
    {
        return SalaryNumberFormat::fromSettings(
            SiteSettings::createFromSettingsTree(['jobboard' => ['salary' => $salarySettings]]),
            ...$languageSeparators,
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
            [
                SalaryNumberFormat::FORMAT_AUTOMATIC,
                SalaryNumberFormat::FORMAT_ENGLISH,
                SalaryNumberFormat::FORMAT_GERMAN,
                SalaryNumberFormat::FORMAT_CUSTOM,
            ],
            array_keys($settings[SalaryNumberFormat::SETTING_FORMAT]['enum'] ?? []),
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
    }

    #[Test]
    public function englishFormatsWithCommaAndPeriod(): void
    {
        self::assertSame('1,234.56', SalaryNumberFormat::english()->format(1234.56));
        self::assertSame('1,234,567.80', SalaryNumberFormat::english()->format(1234567.8));
    }

    #[Test]
    public function germanFormatsWithPeriodAndComma(): void
    {
        self::assertSame('1.234,56', SalaryNumberFormat::german()->format(1234.56));
        self::assertSame('1.234.567,80', SalaryNumberFormat::german()->format(1234567.8));
    }

    #[Test]
    public function fixedFormatsUseGivenDecimals(): void
    {
        self::assertSame('1,235', SalaryNumberFormat::english(0)->format(1234.56));
        self::assertSame('1.234,5600', SalaryNumberFormat::german(4)->format(1234.56));
    }

    #[Test]
    public function fixedFormatsUseDefaultDecimalsForInvalidDecimals(): void
    {
        self::assertSame(2, SalaryNumberFormat::english(-1)->getDecimals());
        self::assertSame(2, SalaryNumberFormat::german(SalaryNumberFormat::MAXIMUM_DECIMALS + 1)->getDecimals());
    }

    #[Test]
    public function customFormatsWithGivenSeparators(): void
    {
        self::assertSame("1'234.6", SalaryNumberFormat::custom("'", '.', 1)->format(1234.56));
        self::assertSame("1\u{202F}234,56", SalaryNumberFormat::custom("\u{202F}", ',', 2)->format(1234.56));
        self::assertSame('1234,56', SalaryNumberFormat::custom('', ',', 2)->format(1234.56));
    }

    #[Test]
    public function customThrowsExceptionForInvalidCombination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1761484800);

        SalaryNumberFormat::custom('.', '.', 2);
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

    #[Test]
    public function fromSettingsWithoutSettingsUsesSeparatorsOfEnglish(): void
    {
        self::assertSame('1,234.56', $this->buildFromSettings([], self::ENGLISH_SEPARATORS)->format(1234.56));
    }

    #[Test]
    public function fromSettingsWithoutSettingsUsesSeparatorsOfGerman(): void
    {
        self::assertSame('1.234,56', $this->buildFromSettings([], self::GERMAN_SEPARATORS)->format(1234.56));
    }

    /**
     * @return array<string, array{array<string, mixed>, array{string, string}, string}>
     */
    public static function fromSettingsDataProvider(): array
    {
        return [
            'automatic in English' => [['numberFormat' => 'automatic'], self::ENGLISH_SEPARATORS, '1,234.56'],
            'automatic in German' => [['numberFormat' => 'automatic'], self::GERMAN_SEPARATORS, '1.234,56'],
            'automatic with decimals' => [['numberFormat' => 'automatic', 'decimals' => 0], self::GERMAN_SEPARATORS, '1.235'],
            'English in German' => [['numberFormat' => 'english'], self::GERMAN_SEPARATORS, '1,234.56'],
            'German in English' => [['numberFormat' => 'german'], self::ENGLISH_SEPARATORS, '1.234,56'],
            'German with decimals' => [['numberFormat' => 'german', 'decimals' => 3], self::ENGLISH_SEPARATORS, '1.234,560'],
            'decimals as string' => [['numberFormat' => 'english', 'decimals' => '1'], self::GERMAN_SEPARATORS, '1,234.6'],
            'invalid decimals' => [['numberFormat' => 'english', 'decimals' => 9], self::GERMAN_SEPARATORS, '1,234.56'],
            'decimals no number' => [['numberFormat' => 'english', 'decimals' => 'two'], self::GERMAN_SEPARATORS, '1,234.56'],
            'custom' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => "'", 'decimalSeparator' => '.', 'decimals' => 1],
                self::GERMAN_SEPARATORS,
                "1'234.6",
            ],
            'custom without grouping' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => '', 'decimalSeparator' => ','],
                self::ENGLISH_SEPARATORS,
                '1234,56',
            ],
            'custom with default separators' => [['numberFormat' => 'custom'], self::GERMAN_SEPARATORS, '1,234.56'],
            'custom separators are ignored by fixed formats' => [
                ['numberFormat' => 'german', 'thousandsSeparator' => "'", 'decimalSeparator' => '.'],
                self::ENGLISH_SEPARATORS,
                '1.234,56',
            ],
            'invalid custom with same separators falls back to automatic' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => ',', 'decimalSeparator' => ','],
                self::GERMAN_SEPARATORS,
                '1.234,56',
            ],
            'invalid custom with digit falls back to automatic' => [
                ['numberFormat' => 'custom', 'thousandsSeparator' => 1, 'decimalSeparator' => '0'],
                self::ENGLISH_SEPARATORS,
                '1,234.56',
            ],
            'unknown format falls back to automatic' => [['numberFormat' => 'swiss'], self::GERMAN_SEPARATORS, '1.234,56'],
            'invalid separators of language fall back to English' => [[], ['.', '.'], '1,234.56'],
            'missing separators of language fall back to English' => [[], ['', ''], '1,234.56'],
        ];
    }

    /**
     * @param array<string, mixed> $salarySettings
     * @param array{string, string} $languageSeparators
     */
    #[Test]
    #[DataProvider('fromSettingsDataProvider')]
    public function fromSettingsSelectsNumberFormat(array $salarySettings, array $languageSeparators, string $expected): void
    {
        self::assertSame($expected, $this->buildFromSettings($salarySettings, $languageSeparators)->format(1234.56));
    }

    #[Test]
    public function formatFormatsZeroAndNegativeAmounts(): void
    {
        self::assertSame('0.00', SalaryNumberFormat::english()->format(0.0));
        self::assertSame('-1.234,56', SalaryNumberFormat::german()->format(-1234.56));
    }
}
