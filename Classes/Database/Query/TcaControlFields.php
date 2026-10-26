<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Database\Query;

use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchema;

/**
 * Optional control fields of a table (TCA ctrl "languageField",
 * "transOrigPointerField" and "enablecolumns" starttime/endtime), resolved
 * from the TCA schema. A field is null, if its capability is missing. Then
 * the related condition does not exist: no language constraint (all rows
 * count, only "uid" identifies a record), a missing start time means "always
 * started", a missing end time "never ends".
 */
final readonly class TcaControlFields
{
    public function __construct(
        public ?string $languageField = null,
        public ?string $translationParentField = null,
        public ?string $startTimeField = null,
        public ?string $endTimeField = null,
    ) {}

    public static function fromSchema(TcaSchema $schema): self
    {
        $languageCapability = $schema->hasCapability(TcaSchemaCapability::Language)
            ? $schema->getCapability(TcaSchemaCapability::Language)
            : null;

        return new self(
            $languageCapability?->getLanguageField()->getName(),
            $languageCapability?->getTranslationOriginPointerField()->getName(),
            self::getRestrictionFieldName($schema, TcaSchemaCapability::RestrictionStartTime),
            self::getRestrictionFieldName($schema, TcaSchemaCapability::RestrictionEndTime),
        );
    }

    /**
     * Language field and translation parent field are both set or both null
     */
    public function isLanguageAware(): bool
    {
        return $this->languageField !== null && $this->translationParentField !== null;
    }

    /**
     * UID of the default record for translations, else the own UID (default
     * records, records of language "all", translations without default record
     * and records of tables without language capability).
     *
     * @param array<string, mixed> $row
     */
    public function getDefaultLanguageUid(array $row): int
    {
        $parentUid = $this->translationParentField === null ? 0 : (int)($row[$this->translationParentField] ?? 0);

        return $parentUid > 0 ? $parentUid : (int)($row['uid'] ?? 0);
    }

    /**
     * Existing start and end time fields, to be selected
     *
     * @return string[]
     */
    public function getTimeFields(): array
    {
        return array_values(array_filter([$this->startTimeField, $this->endTimeField]));
    }

    /**
     * 0 (always started) without start time field
     *
     * @param array<string, mixed> $row
     */
    public function getStartTime(array $row): int
    {
        return $this->startTimeField === null ? 0 : (int)($row[$this->startTimeField] ?? 0);
    }

    /**
     * 0 (never ends) without end time field
     *
     * @param array<string, mixed> $row
     */
    public function getEndTime(array $row): int
    {
        return $this->endTimeField === null ? 0 : (int)($row[$this->endTimeField] ?? 0);
    }

    private static function getRestrictionFieldName(TcaSchema $schema, TcaSchemaCapability $capability): ?string
    {
        if (!$schema->hasCapability($capability)) {
            return null;
        }

        return $schema->getCapability($capability)->getFieldName();
    }
}
