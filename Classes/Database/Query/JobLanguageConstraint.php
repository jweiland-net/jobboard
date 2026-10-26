<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Database\Query;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * QueryBuilder twin of the language statement of Extbase
 * (Typo3DbQueryParser::getLanguageStatement()) for the job table, for code
 * without Extbase, which has to select the same job rows as JobRepository
 * in the language of the request. Keep both in sync.
 *
 * Fallback type "fallback" (OVERLAYS_MIXED): translations with a visible
 * default record plus default records without a visible translation.
 * "strict" (OVERLAYS_ON_WITH_FLOATING): translations with a visible default
 * record plus translations without default record. "free" (OVERLAYS_OFF)
 * and the default language: the records of the language itself. Records of
 * language "all" (-1) always.
 *
 * The selected rows are identified by getDefaultLanguageUid(), the UID
 * Extbase assigns to the overlaid object.
 *
 * The table name, the language field and the translation parent field
 * (TCA ctrl "languageField" / "transOrigPointerField") are taken from the
 * TCA schema of the job table, which the creator passes in.
 */
final readonly class JobLanguageConstraint
{
    private string $table;

    private string $languageField;

    private string $parentField;

    public function __construct(
        private LanguageAspect $languageAspect,
        TcaSchema $jobSchema,
    ) {
        $languageCapability = $jobSchema->getCapability(TcaSchemaCapability::Language);
        $this->table = $jobSchema->getName();
        $this->languageField = $languageCapability->getLanguageField()->getName();
        $this->parentField = $languageCapability->getTranslationOriginPointerField()->getName();
    }

    /**
     * Without site language (no site found), the default language is used.
     */
    public static function fromRequest(ServerRequestInterface $request, TcaSchema $jobSchema): self
    {
        $siteLanguage = $request->getAttribute('language');

        return new self(
            $siteLanguage instanceof SiteLanguage
                ? LanguageAspectFactory::createFromSiteLanguage($siteLanguage)
                : new LanguageAspect(),
            $jobSchema,
        );
    }

    /**
     * Column of the default record of a translation (TCA ctrl
     * "transOrigPointerField"), needed in the rows of getDefaultLanguageUid().
     */
    public function getTranslationParentField(): string
    {
        return $this->parentField;
    }

    /**
     * UID of the default record for translations, else the own UID (default
     * records, records of language "all" and translations without default
     * record). Same as the UID of the Extbase object.
     *
     * @param array<string, mixed> $row with the columns "uid" and the translation parent field
     */
    public function getDefaultLanguageUid(array $row): int
    {
        $parentUid = (int)($row[$this->parentField] ?? 0);

        return $parentUid > 0 ? $parentUid : (int)($row['uid'] ?? 0);
    }

    /**
     * For a query of the job table without table alias
     */
    public function buildExpression(QueryBuilder $queryBuilder): string
    {
        $languageId = $this->languageAspect->getContentId();
        if ($languageId === 0 || !$this->languageAspect->doOverlays()) {
            return $this->buildLanguageIn($queryBuilder, [$languageId, -1]);
        }

        $expr = $queryBuilder->expr();
        $orConstraints = [
            $this->buildLanguageIn($queryBuilder, [-1]),
            $expr->and(
                $this->buildLanguageIn($queryBuilder, [$languageId]),
                $expr->in($this->parentField, $this->buildVisibleDefaultUidsSubSelect($queryBuilder)),
            ),
        ];

        $orConstraints[] = match ($this->languageAspect->getOverlayType()) {
            LanguageAspect::OVERLAYS_ON_WITH_FLOATING => $this->buildFloatingTranslations($queryBuilder, $languageId),
            LanguageAspect::OVERLAYS_MIXED => $this->buildUntranslatedDefaults($queryBuilder, $languageId),
            default => null,
        };

        return (string)$expr->or(...array_filter($orConstraints));
    }

    private function buildFloatingTranslations(QueryBuilder $queryBuilder, int $languageId): CompositeExpression
    {
        return $queryBuilder->expr()->and(
            $this->buildLanguageIn($queryBuilder, [$languageId]),
            $queryBuilder->expr()->eq($this->parentField, 0),
        );
    }

    private function buildUntranslatedDefaults(QueryBuilder $queryBuilder, int $languageId): CompositeExpression
    {
        return $queryBuilder->expr()->and(
            $this->buildLanguageIn($queryBuilder, [0]),
            $queryBuilder->expr()->notIn('uid', $this->buildVisibleTranslationParentsSubSelect($queryBuilder, $languageId)),
        );
    }

    /**
     * Visible records of the default language, regardless of their page
     */
    private function buildVisibleDefaultUidsSubSelect(QueryBuilder $queryBuilder): string
    {
        $alias = 'job_dl';
        $subQueryBuilder = $this->createSubQueryBuilder($queryBuilder, $alias);
        $subQueryBuilder
            ->select($alias . '.uid')
            ->where(
                $subQueryBuilder->expr()->eq($alias . '.' . $this->parentField, 0),
                $subQueryBuilder->expr()->eq($alias . '.' . $this->languageField, 0),
            );

        return $subQueryBuilder->getSQL();
    }

    /**
     * Default records, which have a visible translation in the language
     */
    private function buildVisibleTranslationParentsSubSelect(QueryBuilder $queryBuilder, int $languageId): string
    {
        $alias = 'job_to';
        $subQueryBuilder = $this->createSubQueryBuilder($queryBuilder, $alias);
        $subQueryBuilder
            ->select($alias . '.' . $this->parentField)
            ->where(
                $subQueryBuilder->expr()->gt($alias . '.' . $this->parentField, 0),
                $subQueryBuilder->expr()->eq(
                    $alias . '.' . $this->languageField,
                    $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT),
                ),
            );

        return $subQueryBuilder->getSQL();
    }

    /**
     * Enable fields only (like Extbase), the frontend restrictions build
     * their expressions without parameters.
     */
    private function createSubQueryBuilder(QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        $subQueryBuilder = $queryBuilder->getConnection()->createQueryBuilder();
        $subQueryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        $subQueryBuilder->from($this->table, $alias);

        return $subQueryBuilder;
    }

    /**
     * @param int[] $languageIds
     */
    private function buildLanguageIn(QueryBuilder $queryBuilder, array $languageIds): string
    {
        return $queryBuilder->expr()->in(
            $this->languageField,
            $queryBuilder->createNamedParameter($languageIds, Connection::PARAM_INT_ARRAY),
        );
    }
}
