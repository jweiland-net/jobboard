<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Configuration;

use JWeiland\Jobboard\Domain\Model\SalaryNumberFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Settings\SettingDefinition;
use TYPO3\CMS\Core\Settings\SettingsTypeRegistry;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * The settings of the number format are accepted by the site set registry
 * of TYPO3, and the setting types already reject invalid single values in
 * the backend module "Site Management > Settings". Combinations of values
 * (e.g. the same character for both separators) are validated by
 * SalaryNumberFormat.
 */
final class SalaryNumberFormatSettingsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private function getSettingDefinition(string $key): SettingDefinition
    {
        $setRegistry = $this->get(SetRegistry::class);
        self::assertTrue($setRegistry->hasSet('jweiland/jobboard'));

        foreach ($setRegistry->getSet('jweiland/jobboard')->settingsDefinitions as $settingDefinition) {
            if ($settingDefinition->key === $key) {
                return $settingDefinition;
            }
        }

        self::fail('Setting "' . $key . '" is not defined in site set "jweiland/jobboard"');
    }

    #[Test]
    public function siteSetDefinesNumberFormatWithAutomaticAsDefault(): void
    {
        $settingDefinition = $this->getSettingDefinition(SalaryNumberFormat::SETTING_FORMAT);

        self::assertSame(SalaryNumberFormat::FORMAT_AUTOMATIC, $settingDefinition->default);
        self::assertSame(
            ['automatic', 'english', 'german', 'custom'],
            array_keys($settingDefinition->enum),
        );
    }

    /**
     * @return array<string, array{string, mixed, bool}>
     */
    public static function settingValueDataProvider(): array
    {
        return [
            'no decimals' => [SalaryNumberFormat::SETTING_DECIMALS, 0, true],
            'maximum decimals' => [SalaryNumberFormat::SETTING_DECIMALS, 4, true],
            'negative decimals' => [SalaryNumberFormat::SETTING_DECIMALS, -1, false],
            'too many decimals' => [SalaryNumberFormat::SETTING_DECIMALS, 5, false],
            'thousands separator' => [SalaryNumberFormat::SETTING_THOUSANDS_SEPARATOR, "'", true],
            'empty thousands separator' => [SalaryNumberFormat::SETTING_THOUSANDS_SEPARATOR, '', true],
            'thousands separator with two characters' => [SalaryNumberFormat::SETTING_THOUSANDS_SEPARATOR, '..', false],
            'decimal separator' => [SalaryNumberFormat::SETTING_DECIMAL_SEPARATOR, ',', true],
            'empty decimal separator' => [SalaryNumberFormat::SETTING_DECIMAL_SEPARATOR, '', false],
            'decimal separator with two characters' => [SalaryNumberFormat::SETTING_DECIMAL_SEPARATOR, ',,', false],
        ];
    }

    #[Test]
    #[DataProvider('settingValueDataProvider')]
    public function settingTypeValidatesValue(string $key, mixed $value, bool $expected): void
    {
        $settingDefinition = $this->getSettingDefinition($key);
        $settingsType = $this->get(SettingsTypeRegistry::class)->get($settingDefinition->type);

        self::assertSame($expected, $settingsType->validate($value, $settingDefinition));
    }
}
