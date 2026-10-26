<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Database\Query;

use JWeiland\Jobboard\Database\Query\TcaControlFields;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Schema\Field\DateTimeFieldType;
use TYPO3\CMS\Core\Schema\Field\FieldCollection;
use TYPO3\CMS\Core\Schema\Field\LanguageFieldType;
use TYPO3\CMS\Core\Schema\Field\PassthroughFieldType;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class TcaControlFieldsTest extends UnitTestCase
{
    private static function createFullSchema(): TcaSchema
    {
        return new TcaSchema(
            'tx_jobboard_domain_model_job',
            new FieldCollection([
                'sys_language_uid' => new LanguageFieldType('sys_language_uid', []),
                'l10n_parent' => new PassthroughFieldType('l10n_parent', []),
                'begin' => new DateTimeFieldType('begin', []),
                'finish' => new DateTimeFieldType('finish', []),
            ]),
            [
                'languageField' => 'sys_language_uid',
                'transOrigPointerField' => 'l10n_parent',
                'enablecolumns' => [
                    'starttime' => 'begin',
                    'endtime' => 'finish',
                ],
            ],
        );
    }

    private static function createSchemaWithoutCapabilities(): TcaSchema
    {
        return new TcaSchema('tx_jobboard_domain_model_job', new FieldCollection([]), []);
    }

    #[Test]
    public function fromSchemaReadsFieldNamesOfCapabilities(): void
    {
        $subject = TcaControlFields::fromSchema(self::createFullSchema());

        self::assertSame('sys_language_uid', $subject->languageField);
        self::assertSame('l10n_parent', $subject->translationParentField);
        self::assertSame('begin', $subject->startTimeField);
        self::assertSame('finish', $subject->endTimeField);
        self::assertTrue($subject->isLanguageAware());
        self::assertSame(['begin', 'finish'], $subject->getTimeFields());
    }

    #[Test]
    public function fromSchemaWithoutCapabilitiesReturnsNoFields(): void
    {
        $subject = TcaControlFields::fromSchema(self::createSchemaWithoutCapabilities());

        self::assertNull($subject->languageField);
        self::assertNull($subject->translationParentField);
        self::assertNull($subject->startTimeField);
        self::assertNull($subject->endTimeField);
        self::assertFalse($subject->isLanguageAware());
        self::assertSame([], $subject->getTimeFields());
    }

    #[Test]
    public function fromSchemaWithEndTimeOnlyReturnsEndTimeField(): void
    {
        $schema = new TcaSchema(
            'tx_jobboard_domain_model_salarystep',
            new FieldCollection(['endtime' => new DateTimeFieldType('endtime', [])]),
            ['enablecolumns' => ['endtime' => 'endtime']],
        );
        $subject = TcaControlFields::fromSchema($schema);

        self::assertNull($subject->startTimeField);
        self::assertSame(['endtime'], $subject->getTimeFields());
    }

    #[Test]
    public function getStartAndEndTimeReadExistingFields(): void
    {
        $subject = TcaControlFields::fromSchema(self::createFullSchema());

        self::assertSame(100, $subject->getStartTime(['begin' => '100', 'finish' => 200]));
        self::assertSame(200, $subject->getEndTime(['begin' => '100', 'finish' => 200]));
        self::assertSame(0, $subject->getStartTime([]));
        self::assertSame(0, $subject->getEndTime([]));
    }

    /**
     * Without fields: always started (0), never ends (0)
     */
    #[Test]
    public function getStartAndEndTimeWithoutFieldsReturnZero(): void
    {
        $subject = TcaControlFields::fromSchema(self::createSchemaWithoutCapabilities());

        self::assertSame(0, $subject->getStartTime(['starttime' => 100]));
        self::assertSame(0, $subject->getEndTime(['endtime' => 200]));
    }

    #[Test]
    public function getDefaultLanguageUidReturnsTranslationParent(): void
    {
        $subject = TcaControlFields::fromSchema(self::createFullSchema());

        self::assertSame(3, $subject->getDefaultLanguageUid(['uid' => 13, 'l10n_parent' => 3]));
        self::assertSame(3, $subject->getDefaultLanguageUid(['uid' => 3, 'l10n_parent' => 0]));
    }

    #[Test]
    public function getDefaultLanguageUidWithoutLanguageCapabilityReturnsOwnUid(): void
    {
        $subject = TcaControlFields::fromSchema(self::createSchemaWithoutCapabilities());

        self::assertSame(13, $subject->getDefaultLanguageUid(['uid' => 13, 'l10n_parent' => 3]));
    }
}
