<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Backend\Element;

use JWeiland\Jobboard\Backend\Element\LocalizedDecimalElement;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class LocalizedDecimalElementTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    private function buildSubject(string $value): LocalizedDecimalElement
    {
        $data = [
            'tableName' => 'tx_jobboard_domain_model_job',
            'fieldName' => 'salary_min',
            'parameterArray' => [
                'itemFormElName' => 'data[tx_jobboard_domain_model_job][1][salary_min]',
                'itemFormElValue' => $value,
                'fieldConf' => [
                    'label' => 'Minimum salary',
                    'config' => [
                        'type' => 'number',
                        'format' => 'decimal',
                        'renderType' => 'jobboardLocalizedDecimal',
                    ],
                ],
            ],
        ];

        $subject = GeneralUtility::makeInstance(LocalizedDecimalElement::class);
        $subject->setData($data);

        return $subject;
    }

    #[Test]
    public function renderReturnsTextInputWithMachineFormattedValue(): void
    {
        $html = $this->buildSubject('3421.84')->render()['html'];

        self::assertStringContainsString('type="text"', $html);
        self::assertStringContainsString('value="3421.84"', $html);
        self::assertStringContainsString('name="data[tx_jobboard_domain_model_job][1][salary_min]"', $html);
        self::assertStringContainsString('inputmode="decimal"', $html);
    }

    #[Test]
    public function renderRegistersLocalizedDecimalJavaScriptModule(): void
    {
        $result = $this->buildSubject('3421.84')->render();

        $moduleNames = array_map(
            static fn($module): string => $module->getName(),
            $result['javaScriptModules'],
        );

        self::assertContains('@jweiland/jobboard/form-engine-localized-decimal.js', $moduleNames);
    }
}
