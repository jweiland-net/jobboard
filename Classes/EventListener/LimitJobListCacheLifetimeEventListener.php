<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\ModifyCacheLifetimeForPageEvent;

/**
 * The cached job list changes without any record being saved: a job drops
 * out at the midnight after its application deadline or ending date (see
 * JobRepository::buildAndConstraintForOpenJobs()) and a job with a future
 * starttime appears. Extbase only adds cache tags with lifetimes for the
 * listed (fetched) jobs, and "config.cache" only knows starttime/endtime.
 * This listener limits the cache lifetime of pages with the job board plugin
 * to the next of these points in time of the jobs in the storage pages.
 *
 * "effective_salary_valid_until" is deliberately not considered: salary
 * changes are written by the daily command through DataHandler, which
 * clears the page cache ("TCEMAIN.clearCacheCmd"). Expiring the cache at that
 * point in time would only regenerate the page with the not yet updated
 * values.
 */
#[AsEventListener('jobboard/limitJobListCacheLifetime')]
final readonly class LimitJobListCacheLifetimeEventListener
{
    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    private const TABLE_CONTENT = 'tt_content';

    private const CTYPE = 'jobboard_jobboard';

    /**
     * Same depth as "plugin.tx_jobboard.persistence.recursive" of the site set
     */
    private const DEFAULT_STORAGE_DEPTH = 3;

    public function __construct(
        private ConnectionPool $connectionPool,
        private PageRepository $pageRepository,
        private SiteFinder $siteFinder,
    ) {}

    public function __invoke(ModifyCacheLifetimeForPageEvent $event): void
    {
        $plugins = $this->fetchPlugins($event->getPageId());
        if ($plugins === []) {
            return;
        }

        $now = (int)$event->getContext()->getPropertyFromAspect('date', 'timestamp');
        $nextChange = $this->getNextChange($this->getStoragePids($event->getPageId(), $plugins), $now);
        if ($nextChange > $now) {
            $event->setCacheLifetime(min($event->getCacheLifetime(), $nextChange - $now));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlugins(int $pageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CONTENT);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        return $queryBuilder
            ->select('pages', 'recursive')
            ->from(self::TABLE_CONTENT)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter(self::CTYPE)),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Record storage pages of the plugins, otherwise the site setting
     * "jobboard.storagePid", including sub pages. An empty result means:
     * storage unknown, all jobs are considered.
     *
     * @param array<int, array<string, mixed>> $plugins
     * @return int[]
     */
    private function getStoragePids(int $pageId, array $plugins): array
    {
        $storagePids = [];
        foreach ($plugins as $plugin) {
            $pids = GeneralUtility::intExplode(',', (string)($plugin['pages'] ?? ''), true);
            $depth = (int)($plugin['recursive'] ?? 0);
            if ($pids === []) {
                $pids = $this->getStoragePidsOfSite($pageId);
                $depth = self::DEFAULT_STORAGE_DEPTH;
            }
            $pids = array_values(array_filter($pids, static fn(int $pid): bool => $pid > 0));
            if ($pids === []) {
                return [];
            }
            $storagePids = [...$storagePids, ...$this->pageRepository->getPageIdsRecursive($pids, $depth)];
        }

        return array_values(array_unique($storagePids));
    }

    /**
     * @return int[]
     */
    private function getStoragePidsOfSite(int $pageId): array
    {
        try {
            $storagePid = $this->siteFinder->getSiteByPageId($pageId)->getSettings()->get('jobboard.storagePid', '0');
        } catch (SiteNotFoundException) {
            return [];
        }

        return GeneralUtility::intExplode(',', (string)$storagePid, true);
    }

    /**
     * One MIN() query over all not deleted, not hidden jobs of the default
     * language in the storage pages. Returns 0, if nothing will change.
     *
     * @param int[] $storagePids
     */
    private function getNextChange(array $storagePids, int $now): int
    {
        $today = $this->getMidnight($now, 'today');
        $queryBuilder = $this->createQueryBuilderForJobs($storagePids);
        $queryBuilder->selectLiteral(
            $this->buildMinimumExpression($queryBuilder, 'starttime', '>', $now, 'next_starttime'),
            $this->buildMinimumExpression($queryBuilder, 'endtime', '>', $now, 'next_endtime'),
            $this->buildMinimumExpression($queryBuilder, 'application_deadline', '>=', $today, 'next_deadline'),
            $this->buildMinimumExpression($queryBuilder, 'ending_date', '>=', $today, 'next_ending_date'),
        );
        $row = $queryBuilder->executeQuery()->fetchAssociative() ?: [];

        $nextChanges = array_filter([
            (int)($row['next_starttime'] ?? 0),
            (int)($row['next_endtime'] ?? 0),
            // Listed through the end of that day
            $this->getMidnightAfter((int)($row['next_deadline'] ?? 0)),
            $this->getMidnightAfter((int)($row['next_ending_date'] ?? 0)),
        ]);

        return $nextChanges === [] ? 0 : min($nextChanges);
    }

    /**
     * @param int[] $storagePids
     */
    private function createQueryBuilderForJobs(array $storagePids): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_JOB);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction())
            ->add(new HiddenRestriction());
        $queryBuilder
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->in(
                'sys_language_uid',
                $queryBuilder->createNamedParameter([0, -1], Connection::PARAM_INT_ARRAY),
            ));

        if ($storagePids !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->in(
                'pid',
                $queryBuilder->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY),
            ));
        }

        return $queryBuilder;
    }

    /**
     * MIN(CASE WHEN field > value THEN field END): the smallest value of the
     * field after $value, NULL if there is none
     */
    private function buildMinimumExpression(
        QueryBuilder $queryBuilder,
        string $field,
        string $operator,
        int $value,
        string $alias,
    ): string {
        $quotedField = $queryBuilder->quoteIdentifier($field);

        return sprintf(
            'MIN(CASE WHEN %s %s %s THEN %s END) AS %s',
            $quotedField,
            $operator,
            $queryBuilder->createNamedParameter($value, Connection::PARAM_INT),
            $quotedField,
            $queryBuilder->quoteIdentifier($alias),
        );
    }

    private function getMidnightAfter(int $timestamp): int
    {
        return $timestamp > 0 ? $this->getMidnight($timestamp, 'tomorrow') : 0;
    }

    /**
     * In the default time zone, like "new \DateTime('today')" of JobRepository
     */
    private function getMidnight(int $timestamp, string $modifier): int
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->modify($modifier)
            ->getTimestamp();
    }
}
