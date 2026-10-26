<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Database\Query;

use JWeiland\Jobboard\Database\Query\JobLanguageConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Schema\Field\FieldCollection;
use TYPO3\CMS\Core\Schema\Field\LanguageFieldType;
use TYPO3\CMS\Core\Schema\Field\PassthroughFieldType;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class JobLanguageConstraintTest extends UnitTestCase
{
    /**
     * Minimal TCA schema of a language aware table: the field names come
     * from TCA ctrl "languageField" and "transOrigPointerField".
     */
    private static function createSchema(string $parentField = 'l10n_parent'): TcaSchema
    {
        return new TcaSchema(
            'tx_jobboard_domain_model_job',
            new FieldCollection([
                'sys_language_uid' => new LanguageFieldType('sys_language_uid', []),
                $parentField => new PassthroughFieldType($parentField, []),
            ]),
            [
                'languageField' => 'sys_language_uid',
                'transOrigPointerField' => $parentField,
            ],
        );
    }

    private static function createSubject(string $parentField = 'l10n_parent'): JobLanguageConstraint
    {
        return new JobLanguageConstraint(new LanguageAspect(), self::createSchema($parentField));
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function rowDataProvider(): array
    {
        return [
            'record of default language' => [['uid' => 3, 'l10n_parent' => 0], 3],
            'translation' => [['uid' => 13, 'l10n_parent' => 3], 3],
            'translation with parent as string' => [['uid' => '13', 'l10n_parent' => '3'], 3],
            'floating translation' => [['uid' => 15, 'l10n_parent' => 0], 15],
            'record of language "all"' => [['uid' => 6, 'l10n_parent' => 0], 6],
            'without column l10n_parent' => [['uid' => 7], 7],
            'negative l10n_parent' => [['uid' => 7, 'l10n_parent' => -1], 7],
            'without columns' => [[], 0],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[Test]
    #[DataProvider('rowDataProvider')]
    public function getDefaultLanguageUidReturnsUidOfDefaultRecord(array $row, int $expectedUid): void
    {
        self::assertSame($expectedUid, self::createSubject()->getDefaultLanguageUid($row));
    }

    #[Test]
    public function getDefaultLanguageUidUsesTranslationParentFieldOfSchema(): void
    {
        $subject = self::createSubject('l18n_parent');

        self::assertSame(3, $subject->getDefaultLanguageUid(['uid' => 13, 'l18n_parent' => 3]));
        self::assertSame(13, $subject->getDefaultLanguageUid(['uid' => 13, 'l10n_parent' => 3]));
    }

    #[Test]
    public function getTranslationParentFieldReturnsTranslationParentFieldOfSchema(): void
    {
        self::assertSame('l10n_parent', self::createSubject()->getTranslationParentField());
        self::assertSame('l18n_parent', self::createSubject('l18n_parent')->getTranslationParentField());
    }

    /**
     * Like Extbase without "languageField": no constraint at all
     */
    #[Test]
    public function withoutLanguageCapabilityNoConstraintIsBuilt(): void
    {
        $schema = new TcaSchema('tx_jobboard_domain_model_job', new FieldCollection([]), []);
        $subject = new JobLanguageConstraint(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_MIXED, [0]), $schema);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->never())->method('expr');

        self::assertSame('', $subject->buildExpression($queryBuilder));
        self::assertNull($subject->getTranslationParentField());
        self::assertSame(13, $subject->getDefaultLanguageUid(['uid' => 13, 'l10n_parent' => 3]));
    }
}
