<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\SalaryRequirement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class SalaryRequirementTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $settings
     */
    private function buildRequestWithSiteSettings(array $settings): ServerRequest
    {
        return (new ServerRequest('https://example.com/'))
            ->withAttribute('site', new Site('main', 1, ['settings' => $settings]));
    }

    #[Test]
    public function isRequiredReturnsGivenValue(): void
    {
        self::assertTrue((new SalaryRequirement(true))->isRequired());
        self::assertFalse((new SalaryRequirement(false))->isRequired());
    }

    #[Test]
    public function settingIdentifierMatchesSiteSetting(): void
    {
        self::assertSame('jobboard.salary.required', SalaryRequirement::SETTING);
    }

    #[Test]
    public function settingIsDefinedInSiteSetAsBooleanWithDefaultTrue(): void
    {
        $definitions = Yaml::parseFile(__DIR__ . '/../../../../Configuration/Sets/Jobboard/settings.definitions.yaml');

        self::assertSame(['parent' => 'jobboard'], $definitions['categories']['jobboard.salary'] ?? null);
        self::assertSame(
            ['category' => 'jobboard.salary', 'type' => 'bool', 'default' => true],
            $definitions['settings'][SalaryRequirement::SETTING] ?? null,
        );
    }

    #[Test]
    public function fromRequestWithoutSiteRequiresSalary(): void
    {
        self::assertTrue(SalaryRequirement::fromRequest(new ServerRequest('https://example.com/'))->isRequired());
    }

    #[Test]
    public function fromRequestWithNullSiteRequiresSalary(): void
    {
        $request = (new ServerRequest('https://example.com/'))->withAttribute('site', new NullSite());

        self::assertTrue(SalaryRequirement::fromRequest($request)->isRequired());
    }

    #[Test]
    public function fromRequestWithoutSettingRequiresSalary(): void
    {
        $request = $this->buildRequestWithSiteSettings(['jobboard' => ['storagePid' => '2']]);

        self::assertTrue(SalaryRequirement::fromRequest($request)->isRequired());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function settingDataProvider(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            'string "1"' => ['1', true],
            'string "0"' => ['0', false],
            'integer 0' => [0, false],
        ];
    }

    #[Test]
    #[DataProvider('settingDataProvider')]
    public function fromRequestReadsSettingOfSite(mixed $value, bool $expectedRequired): void
    {
        $request = $this->buildRequestWithSiteSettings(['jobboard' => ['salary' => ['required' => $value]]]);

        self::assertSame($expectedRequired, SalaryRequirement::fromRequest($request)->isRequired());
    }
}
