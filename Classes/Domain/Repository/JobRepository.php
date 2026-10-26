<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Repository;

use JWeiland\Jobboard\Database\Query\TcaControlFields;
use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Model\ZipCity;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Allows to help search for jobs by various search criteria
 *
 * Every query for the frontend lists open jobs only, see
 * buildAndConstraintForOpenJobs(). By default, salary information is
 * required as well ("$salaryRequired", site setting "jobboard.salary.required",
 * see SalaryRequirement). It is filtered in SQL by the precomputed effective
 * salary (see EffectiveSalaryUpdater), so limits and pagination are applied
 * by the database.
 */
class JobRepository extends Repository
{
    private const TABLE = 'tx_jobboard_domain_model_job';

    /**
     * Jobs with the closest application deadline first. Jobs without an
     * application deadline (0) are sorted to the top by the database. The
     * UID makes the order unique, so pages of the page browser never overlap.
     */
    protected $defaultOrderings = [
        'applicationDeadline' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    public function __construct(
        private readonly TcaSchemaFactory $tcaSchemaFactory,
    ) {
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function findBySettings(array $settings, int $limit = 0, bool $salaryRequired = true): QueryResultInterface
    {
        $query = $this->createQuery();

        $andConstraint = [
            ...$this->buildAndConstraintForOpenJobs($query, $salaryRequired),
            ...$this->buildConstraintForJobAreas(JobAreaRestriction::fromSettings($settings), $query),
        ];

        if ($limit) {
            $query->setLimit($limit);
        }

        return $query->matching($query->logicalAnd(...$andConstraint))->execute();
    }

    /**
     * The job areas configured in the plugin ("settings.jobAreas") restrict
     * the search results the same way as in findBySettings().
     *
     * @param array<string, mixed> $settings
     */
    public function findBySearch(
        Search $search,
        int $limit = 0,
        array $settings = [],
        bool $salaryRequired = true,
    ): QueryResultInterface {
        $query = $this->createQuery();

        $andConstraint = [
            ...$this->buildAndConstraintForOpenJobs($query, $salaryRequired),
            ...$this->buildConstraintForJobAreas(JobAreaRestriction::fromSettings($settings), $query),
            ...$this->buildAndConstraintForRelations($search, $query),
            ...$this->buildConstraintForAddress($search->getZipCity(), $query),
            ...$this->buildAndConstraintForSearchWords($search->getSearchWord(), $query),
        ];

        if ($limit) {
            $query->setLimit($limit);
        }

        return $query->matching($query->logicalAnd(...$andConstraint))->execute();
    }

    /**
     * The remembered jobs of a visitor, which are still listed in the
     * frontend. Ordered like the job list.
     *
     * The query keeps the language handling of findBySettings()
     * (Typo3DbQueryParser::getLanguageStatement()), so exactly the jobs of the
     * job list in the current language are returned and overlaid by Extbase.
     * Depending on the fallback type, the selected rows are the translations
     * ("strict", "free"), the translations plus the untranslated default
     * records ("fallback") or the default records (default language). A
     * translation has its own UID, so the given UIDs of the default language
     * are matched against "uid" and "l10n_parent".
     *
     * @param int[] $jobUids UIDs of jobs in the default language
     */
    public function findByUids(array $jobUids, bool $salaryRequired = true): QueryResultInterface
    {
        $query = $this->createQuery();

        return $query->matching($query->logicalAnd(
            $this->buildConstraintForUidsOfDefaultLanguage($jobUids, $query),
            ...$this->buildAndConstraintForOpenJobs($query, $salaryRequired),
        ))->execute();
    }

    /**
     * Matches the default record (uid) and its translations (l10n_parent).
     * The language statement of Extbase decides, which of them is selected.
     * Never matches anything without UIDs: "l10n_parent IN (0)" would match
     * every record of the default language.
     *
     * @param int[] $jobUids
     */
    private function buildConstraintForUidsOfDefaultLanguage(array $jobUids, QueryInterface $query): ConstraintInterface
    {
        $jobUids = array_values(array_filter($jobUids, static fn(int $jobUid): bool => $jobUid > 0));
        if ($jobUids === []) {
            return $query->equals('uid', 0);
        }

        $translationParentProperty = $this->getTranslationParentProperty();
        if ($translationParentProperty === null) {
            return $query->in('uid', $jobUids);
        }

        return $query->logicalOr(
            $query->in('uid', $jobUids),
            $query->in($translationParentProperty, $jobUids),
        );
    }

    /**
     * Property of the column TCA ctrl "transOrigPointerField" (usually
     * "l10n_parent"), which is not mapped in the model: Extbase resolves the
     * column name of an unmapped property by converting it to snake case.
     * Null, if the table is not language aware.
     */
    private function getTranslationParentProperty(): ?string
    {
        $parentField = TcaControlFields::fromSchema($this->tcaSchemaFactory->get(self::TABLE))->translationParentField;

        return $parentField === null ? null : GeneralUtility::underscoredToLowerCamelCase($parentField);
    }

    private function buildAndConstraintForRelations(Search $search, QueryInterface $query): array
    {
        $andConstraint = [];

        if ($search->getJobArea() instanceof JobArea) {
            $andConstraint[] = $query->contains('jobAreas', (int)$search->getJobArea()->getUid());
        }

        if ($search->getJobRole() instanceof JobRole) {
            $andConstraint[] = $query->in('jobRole', $this->getUidsOfRelatedObject($search->getJobRole()));
        }

        if ($search->getJobType() instanceof JobType) {
            $andConstraint[] = $query->in('jobType', $this->getUidsOfRelatedObject($search->getJobType()));
        }

        return $andConstraint;
    }

    /**
     * Never compare a relation with the domain object itself: for a
     * translated object, Typo3DbQueryParser::createTypedNamedParameter()
     * binds its localized UID ("_localizedUid"), but the jobs of all languages
     * reference the UID of the default language (DataHandler copies the
     * value into the translations). With fallback type "fallback", the
     * translated job rows are queried, so no job would match. The localized
     * UID is accepted as well, for jobs referencing the translation directly.
     *
     * @return int[]
     */
    private function getUidsOfRelatedObject(AbstractDomainObject $domainObject): array
    {
        return array_values(array_unique(array_filter([
            (int)$domainObject->getUid(),
            (int)$domainObject->_getProperty(AbstractDomainObject::PROPERTY_LOCALIZED_UID),
        ])));
    }

    /**
     * Base constraints for every job listed in the frontend: it needs a title,
     * its application deadline must not have passed and, for fixed-term
     * positions, its employment must not have ended already. Deadline and
     * ending date are compared with the start of today, so a job is listed
     * through the end of that day (see also
     * LimitJobListCacheLifetimeEventListener). Salary information is required
     * with "$salaryRequired" only. Keep in sync with the QueryBuilder version
     * OpenJobRestriction (map info window, RememberJobMiddleware).
     */
    private function buildAndConstraintForOpenJobs(QueryInterface $query, bool $salaryRequired): array
    {
        $today = new \DateTime('today');

        $andConstraint = [
            $query->logicalNot($query->equals('title', '')),
            $query->logicalOr(
                $query->equals('applicationDeadline', 0),
                $query->greaterThanOrEqual('applicationDeadline', $today),
            ),
            $query->logicalOr(
                $query->equals('endingDate', 0),
                $query->greaterThanOrEqual('endingDate', $today),
            ),
        ];

        if ($salaryRequired) {
            $andConstraint[] = $query->logicalOr(
                $query->greaterThan('effectiveSalaryMin', 0),
                $query->greaterThan('effectiveSalaryMax', 0),
            );
        }

        return $andConstraint;
    }

    /**
     * A job matches, if any of its job areas is one of the configured job
     * areas. Returns no constraint at all, if no job area is configured.
     */
    private function buildConstraintForJobAreas(JobAreaRestriction $jobAreaRestriction, QueryInterface $query): array
    {
        $orConstraint = [];
        foreach ($jobAreaRestriction->getJobAreaUids() as $jobAreaUid) {
            $orConstraint[] = $query->contains('jobAreas', $jobAreaUid);
        }

        return $orConstraint === [] ? [] : [$query->logicalOr(...$orConstraint)];
    }

    /**
     * Zip and city are combined with OR. Returns no constraint at all, if
     * neither zip nor city was given.
     */
    private function buildConstraintForAddress(ZipCity $zipCity, QueryInterface $query): array
    {
        $orConstraint = [];

        // For zip, we do an exact search
        if ($zipCity->getZip()) {
            $orConstraint[] = $query->equals('address.zip', $zipCity->getZip());
        }

        // For city, we start a like search
        if ($zipCity->getCity()) {
            $orConstraint[] = $query->like(
                'address.city',
                '%' . addcslashes($zipCity->getCity(), '_%') . '%',
            );
        }

        return $orConstraint === [] ? [] : [$query->logicalOr(...$orConstraint)];
    }

    /**
     * Each word of the free text search is required to match (AND), but a single
     * word may match any of several Job fields (OR) - we don't know upfront which
     * part of the search word belongs to which field.
     */
    private function buildAndConstraintForSearchWords(string $searchWord, QueryInterface $query): array
    {
        $andConstraint = [];

        foreach (GeneralUtility::trimExplode(' ', $searchWord, true) as $word) {
            $likeWord = '%' . addcslashes($word, '_%') . '%';

            $andConstraint[] = $query->logicalOr(
                $query->like('title', $likeWord),
                $query->like('description', $likeWord),
                $query->like('offer', $likeWord),
                $query->like('requirements', $likeWord),
                $query->like('furtherInformation', $likeWord),
                $query->like('applicationGuidelines', $likeWord),
                $query->like('address.address', $likeWord),
                $query->like('address.city', $likeWord),
            );
        }

        return $andConstraint;
    }
}
