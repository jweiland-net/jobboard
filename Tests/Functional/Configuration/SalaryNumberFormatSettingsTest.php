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
 * The currency setting is accepted by the site set registry of TYPO3, and
 * its setting type already rejects values with the wrong length in the
 * backend module "Site Management > Settings". The format of the code (three
 * uppercase letters) is validated by SalaryNumberFormat.
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
    public function siteSetDefinesCurrencyWithEurAsDefault(): void
    {
        self::assertSame(
            SalaryNumberFormat::DEFAULT_CURRENCY,
            $this->getSettingDefinition(SalaryNumberFormat::SETTING_CURRENCY)->default,
        );
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function currencyValueDataProvider(): array
    {
        return [
            'currency' => ['CHF', true],
            'currency with two characters' => ['EU', false],
            'currency with four characters' => ['EURO', false],
        ];
    }

    #[Test]
    #[DataProvider('currencyValueDataProvider')]
    public function settingTypeValidatesCurrency(mixed $value, bool $expected): void
    {
        $settingDefinition = $this->getSettingDefinition(SalaryNumberFormat::SETTING_CURRENCY);
        $settingsType = $this->get(SettingsTypeRegistry::class)->get($settingDefinition->type);

        self::assertSame($expected, $settingsType->validate($value, $settingDefinition));
    }
}
