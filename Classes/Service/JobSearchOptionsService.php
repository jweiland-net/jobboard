<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Storage\Typo3DbQueryParser;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Aggregates the search options with GROUP BY / COUNT() queries instead of
 * iterating all jobs.
 *
 * To guarantee exactly the same constraints as the listed jobs (storage
 * pages, enable fields, language, job areas of the plugin, salary (if
 * required by the site), open jobs and search criteria), the Extbase query of the list/search action is
 * converted into a QueryBuilder by Extbase's own Typo3DbQueryParser
 * (internal API of Extbase, used the same way as Typo3DbBackend does).
 * Only its SELECT and GROUP BY are replaced. A limit ("maxEntries") is not
 * part of the converted query, so the options are restricted to the UIDs of
 * the limited jobs, like before.
 *
 * Titles and cities are loaded with frontend restrictions and translated
 * with the language of the query, like Extbase does for related objects.
 */
final readonly class JobSearchOptionsService implements JobSearchOptionsServiceInterface
{
    /**
     * Typo3DbQueryParser uses the table name as alias of the main table
     */
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    private const TABLE_JOB_AREA = 'tx_jobboard_domain_model_jobarea';

    private const TABLE_JOB_ROLE = 'tx_jobboard_domain_model_jobrole';

    private const TABLE_JOB_TYPE = 'tx_jobboard_domain_model_jobtype';

    private const TABLE_ADDRESS = 'tt_address';

    private const TABLE_JOB_JOB_AREA_MM = 'tx_jobboard_job_jobarea_mm';

    /**
     * Own alias, must not collide with the aliases of Typo3DbQueryParser
     */
    private const ALIAS_JOB_JOB_AREA_MM = 'jobboard_search_options_job_area_mm';

    public function __construct(
        private ConnectionPool $connectionPool,
        private PageRepository $pageRepository,
    ) {}

    public function getSearchOptions(
        QueryInterface $query,
        JobAreaRestriction $jobAreaRestriction,
        bool $showJobAreaCount,
    ): array {
        $languageAspect = $query->getQuerySettings()->getLanguageAspect();

        return [
            'jobAreas' => $this->getJobAreas($query, $jobAreaRestriction, $showJobAreaCount, $languageAspect),
            'jobRoles' => $this->getTitles(
                self::TABLE_JOB_ROLE,
                $this->getRelatedUids($query, 'job_role'),
                $languageAspect,
            ),
            'jobTypes' => $this->getTitles(
                self::TABLE_JOB_TYPE,
                $this->getRelatedUids($query, 'job_type'),
                $languageAspect,
            ),
            'jobLocations' => $this->getCities($this->getRelatedUids($query, 'address'), $languageAspect),
        ];
    }

    /**
     * @return int[]
     */
    public function getAddressUids(QueryInterface $query): array
    {
        return $this->getRelatedUids($query, 'address');
    }

    /**
     * A job can have several job areas, so the numbers may add up to more
     * than the total number of jobs.
     *
     * @return array<int, string>
     */
    private function getJobAreas(
        QueryInterface $query,
        JobAreaRestriction $jobAreaRestriction,
        bool $showJobAreaCount,
        LanguageAspect $languageAspect,
    ): array {
        $numberOfJobsByJobArea = $this->countJobsByJobArea($query, $jobAreaRestriction);
        $titles = $this->getTitles(self::TABLE_JOB_AREA, array_keys($numberOfJobsByJobArea), $languageAspect);
        if (!$showJobAreaCount) {
            return $titles;
        }

        $jobAreas = [];
        foreach ($titles as $jobAreaUid => $title) {
            $jobAreas[$jobAreaUid] = sprintf('%s (%d)', $title, $numberOfJobsByJobArea[$jobAreaUid]);
        }

        return $jobAreas;
    }

    /**
     * @return array<int, int> Number of jobs with the job area UID as key
     */
    private function countJobsByJobArea(QueryInterface $query, JobAreaRestriction $jobAreaRestriction): array
    {
        $queryBuilder = $this->createQueryBuilderForJobs($query);
        $queryBuilder
            ->select(self::ALIAS_JOB_JOB_AREA_MM . '.uid_foreign AS uid')
            ->addSelectLiteral(
                'COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier(self::TABLE_JOB . '.uid') . ') AS '
                . $queryBuilder->quoteIdentifier('number_of_jobs'),
            )
            ->join(
                self::TABLE_JOB,
                self::TABLE_JOB_JOB_AREA_MM,
                self::ALIAS_JOB_JOB_AREA_MM,
                $queryBuilder->expr()->eq(
                    self::ALIAS_JOB_JOB_AREA_MM . '.uid_local',
                    $queryBuilder->quoteIdentifier(self::TABLE_JOB . '.uid'),
                ),
            )
            ->groupBy(self::ALIAS_JOB_JOB_AREA_MM . '.uid_foreign');
        $this->addJobAreaRestriction($queryBuilder, $jobAreaRestriction);

        $numberOfJobsByJobArea = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $numberOfJobsByJobArea[(int)$row['uid']] = (int)$row['number_of_jobs'];
        }

        return $numberOfJobsByJobArea;
    }

    /**
     * A job may have further job areas besides the job areas configured in
     * the plugin. These are neither offered nor counted.
     */
    private function addJobAreaRestriction(QueryBuilder $queryBuilder, JobAreaRestriction $jobAreaRestriction): void
    {
        if (!$jobAreaRestriction->isRestricted()) {
            return;
        }

        $queryBuilder->andWhere($queryBuilder->expr()->in(
            self::ALIAS_JOB_JOB_AREA_MM . '.uid_foreign',
            $queryBuilder->createNamedParameter($jobAreaRestriction->getJobAreaUids(), Connection::PARAM_INT_ARRAY),
        ));
    }

    /**
     * Distinct values of a single relation column ("job_role", "job_type",
     * "address") of all matching jobs
     *
     * @return int[]
     */
    private function getRelatedUids(QueryInterface $query, string $column): array
    {
        $queryBuilder = $this->createQueryBuilderForJobs($query);
        $queryBuilder
            ->select(self::TABLE_JOB . '.' . $column)
            ->andWhere($queryBuilder->expr()->gt(self::TABLE_JOB . '.' . $column, 0))
            ->groupBy(self::TABLE_JOB . '.' . $column);

        return array_map(intval(...), $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * Converts the Extbase query into a QueryBuilder with the same
     * constraints, without ordering and without limit.
     */
    private function createQueryBuilderForJobs(QueryInterface $query): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(Typo3DbQueryParser::class)
            ->convertQueryToDoctrineQueryBuilder($query);
        $queryBuilder->resetOrderBy();

        if ($query->getLimit() > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->in(
                self::TABLE_JOB . '.uid',
                $queryBuilder->createNamedParameter($this->getUidsOfLimitedJobs($query), Connection::PARAM_INT_ARRAY),
            ));
        }

        return $queryBuilder;
    }

    /**
     * Raw rows only, no objects. A limited query (a teaser) is small.
     *
     * @return int[]
     */
    private function getUidsOfLimitedJobs(QueryInterface $query): array
    {
        $uids = [0];
        foreach ($query->execute(true) as $row) {
            $uids[] = (int)($row['uid'] ?? 0);
        }

        return $uids;
    }

    /**
     * Hidden, scheduled or deleted titles are not offered, like Extbase
     * does not load such related objects.
     *
     * @param int[] $uids
     * @return array<int, string>
     */
    private function getTitles(string $table, array $uids, LanguageAspect $languageAspect): array
    {
        $titles = [];
        foreach ($this->fetchVisibleRecords($table, $uids, $languageAspect) as $uid => $record) {
            $titles[$uid] = (string)($record['title'] ?? '');
        }

        return $titles;
    }

    /**
     * @param int[] $addressUids
     * @return array<string, string>
     */
    private function getCities(array $addressUids, LanguageAspect $languageAspect): array
    {
        $cities = [];
        foreach ($this->fetchVisibleRecords(self::TABLE_ADDRESS, $addressUids, $languageAspect) as $record) {
            $city = (string)($record['city'] ?? '');
            $cities[$city] = $city;
        }

        return $cities;
    }

    /**
     * @param int[] $uids
     * @return array<int, array<string, mixed>> Translated records with the UID of the default language as key
     */
    private function fetchVisibleRecords(string $table, array $uids, LanguageAspect $languageAspect): array
    {
        if ($uids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        $result = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->in(
                'uid',
                $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY),
            ))
            ->executeQuery();

        $records = [];
        while ($record = $result->fetchAssociative()) {
            $overlaidRecord = $this->pageRepository->getLanguageOverlay($table, $record, $languageAspect);
            if (is_array($overlaidRecord)) {
                $records[(int)$record['uid']] = $overlaidRecord;
            }
        }

        return $records;
    }
}
