<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\ApiModel;

use JWeiland\Jobboard\ApiModel\AbstractModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class AbstractModelTest extends UnitTestCase
{
    private function buildSubject(string $xml): AbstractModel
    {
        return new class (new \SimpleXMLElement($xml)) extends AbstractModel {
            public function __construct(\SimpleXMLElement $xmlElement)
            {
                $this->xmlElement = $xmlElement;
            }
        };
    }

    #[Test]
    public function getXmlElementReturnsConstructorValue(): void
    {
        $xmlElement = new \SimpleXMLElement('<job><title>Foo</title></job>');
        $subject = new class ($xmlElement) extends AbstractModel {
            public function __construct(\SimpleXMLElement $xmlElement)
            {
                $this->xmlElement = $xmlElement;
            }
        };

        self::assertSame($xmlElement, $subject->getXmlElement());
    }

    #[Test]
    public function getValueByPathWithoutDataTypeReturnsString(): void
    {
        $subject = $this->buildSubject('<job><title>Foo</title></job>');

        self::assertSame('Foo', $subject->getValueByPath('title'));
    }

    #[Test]
    public function getValueByPathWithBoolDataTypeReturnsBool(): void
    {
        $subject = $this->buildSubject('<job><active>1</active></job>');

        self::assertTrue($subject->getValueByPath('active', 'bool'));
        self::assertTrue($subject->getValueByPath('active', 'boolean'));
    }

    #[Test]
    public function getValueByPathWithIntDataTypeReturnsInt(): void
    {
        $subject = $this->buildSubject('<job><vacancy_id>4711</vacancy_id></job>');

        self::assertSame(4711, $subject->getValueByPath('vacancy_id', 'int'));
        self::assertSame(4711, $subject->getValueByPath('vacancy_id', 'integer'));
    }

    #[Test]
    public function getValueByPathWithFloatDataTypeReturnsFloat(): void
    {
        $subject = $this->buildSubject('<job><rating>4.5</rating></job>');

        self::assertSame(4.5, $subject->getValueByPath('rating', 'float'));
        self::assertSame(4.5, $subject->getValueByPath('rating', 'double'));
    }

    #[Test]
    public function getValueByPathWithNativeDataTypeReturnsSimpleXmlElements(): void
    {
        $subject = $this->buildSubject('<job><locations><location>1</location></locations></job>');

        $elements = $subject->getValueByPath('locations/location', 'native');

        self::assertIsArray($elements);
        self::assertInstanceOf(\SimpleXMLElement::class, $elements[0]);
        self::assertSame('1', (string)$elements[0]);
    }

    #[Test]
    public function getValueByPathWithSimpleXmlElementClassDataTypeReturnsSimpleXmlElements(): void
    {
        $subject = $this->buildSubject('<job><locations><location>1</location></locations></job>');

        $elements = $subject->getValueByPath('locations/location', \SimpleXMLElement::class);

        self::assertIsArray($elements);
        self::assertInstanceOf(\SimpleXMLElement::class, $elements[0]);
    }

    #[Test]
    public function getValueByPathWithArrayDataTypeReturnsArray(): void
    {
        $subject = $this->buildSubject('<job><title>Foo</title></job>');

        self::assertIsArray($subject->getValueByPath('title', 'array'));
    }

    #[Test]
    public function getValueByPathWithNonExistingPathReturnsDefault(): void
    {
        $subject = $this->buildSubject('<job><title>Foo</title></job>');

        self::assertSame('fallback', $subject->getValueByPath('does/not/exist', 'string', 'fallback'));
        self::assertSame(42, $subject->getValueByPath('does/not/exist', 'int', 42));
    }

    public static function emptyValueOfDataTypeDataProvider(): array
    {
        return [
            'string' => ['string', ''],
            'int' => ['int', 0],
            'bool' => ['bool', false],
            'float' => ['float', 0.0],
            'native' => ['native', []],
            'array' => ['array', []],
        ];
    }

    #[Test]
    #[DataProvider('emptyValueOfDataTypeDataProvider')]
    public function getValueByPathWithNonExistingPathAndWithoutDefaultReturnsEmptyValueOfDataType(
        string $dataType,
        mixed $expectedValue,
    ): void {
        $subject = $this->buildSubject('<job><title>Foo</title></job>');

        self::assertSame($expectedValue, $subject->getValueByPath('does/not/exist', $dataType));
    }

    #[Test]
    public function getValueByPathWithExistingEmptyElementReturnsEmptyValueInsteadOfDefault(): void
    {
        $subject = $this->buildSubject('<job><title></title></job>');

        self::assertSame('', $subject->getValueByPath('title', 'string', 'fallback'));
    }

    #[Test]
    public function hasPathWithExistingElementReturnsTrue(): void
    {
        $subject = $this->buildSubject('<job><title></title></job>');

        self::assertTrue($subject->hasPath('title'));
    }

    #[Test]
    public function hasPathWithNonExistingElementReturnsFalse(): void
    {
        $subject = $this->buildSubject('<job><title>Foo</title></job>');

        self::assertFalse($subject->hasPath('does/not/exist'));
    }

    #[Test]
    public function getValueByPathWithInvalidXpathExpressionReturnsDefault(): void
    {
        $subject = $this->buildSubject('<job><title>Foo</title></job>');

        self::assertSame('fallback', @$subject->getValueByPath('undefinedns:bar', 'string', 'fallback'));
    }
}
