<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Database\Query\Restriction\OpenJobRestriction;
use JWeiland\Jobboard\Domain\Model\JobLink;
use JWeiland\Jobboard\Domain\Model\JobLocation;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The jobs are selected with the same rules as the job list of the plugin
 * (JobRepository): storage pages of the site setting "jobboard.storagePid"
 * incl. 3 levels of sub pages, enable fields, open jobs with salary
 * information (OpenJobRestriction). Job titles and the company of the
 * address are translated into the language of the request. The jobs are
 * ordered by the translated title (locale of the site language, case
 * insensitive), then by UID.
 *
 * Requires the column "tt_address.tx_maps2_uid" of EXT:maps2. It is only
 * called by RenderJobLocationInfoWindowContentEventListener, which is only
 * registered with EXT:maps2.
 */
final readonly class JobLocationService implements JobLocationServiceInterface
{
    private const TABLE_ADDRESS = 'tt_address';

    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    public function __construct(
        private ConnectionPool $connectionPool,
        private StoragePidResolverInterface $storagePidResolver,
        private JobUriBuilderInterface $jobUriBuilder,
    ) {}

    public function findByPoiCollection(int $poiCollectionUid, ServerRequestInterface $request): ?JobLocation
    {
        $siteLanguage = $request->getAttribute('language');
        if ($poiCollectionUid <= 0 || !$siteLanguage instanceof SiteLanguage) {
            return null;
        }

        $addressRows = $this->fetchAddresses($poiCollectionUid);
        if ($addressRows === []) {
            return null;
        }

        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $addressUids = array_map(intval(...), array_column($addressRows, 'uid'));
        $jobRows = $this->sortByTitle(
            $this->fetchOpenJobs($addressUids, $request, $languageAspect),
            $siteLanguage,
        );

        return JobLocation::fromAddressRow(
            $this->getAddressOverlay($addressRows[0], $languageAspect),
            $this->buildJobLinks($jobRows, $request),
        );
    }

    /**
     * Usually one address per PoiCollection. Several addresses can share one
     * PoiCollection (e.g. copies), then the first one names the location.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAddresses(int $poiCollectionUid): array
    {
        $queryBuilder = $this->createQueryBuilder(self::TABLE_ADDRESS);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_ADDRESS)
            ->where(
                $queryBuilder->expr()->eq(
                    'tx_maps2_uid',
                    $queryBuilder->createNamedParameter($poiCollectionUid, Connection::PARAM_INT),
                ),
                $this->buildLanguageConstraint($queryBuilder, [0, -1]),
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int[] $addressUids
     * @return array<int, array<string, mixed>>
     */
    private function fetchOpenJobs(array $addressUids, ServerRequestInterface $request, LanguageAspect $languageAspect): array
    {
        $storagePids = $this->storagePidResolver->getStoragePids($request);
        if ($storagePids === []) {
            return [];
        }

        $jobRows = $this->buildOpenJobsQuery($addressUids, $storagePids, $languageAspect)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->getLanguageOverlays($jobRows, $languageAspect);
    }

    /**
     * @param int[] $addressUids
     * @param int[] $storagePids
     */
    private function buildOpenJobsQuery(array $addressUids, array $storagePids, LanguageAspect $languageAspect): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder(self::TABLE_JOB);
        $queryBuilder->getRestrictions()->add(OpenJobRestriction::forToday());

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_JOB)
            ->where(
                $queryBuilder->expr()->in(
                    'address',
                    $queryBuilder->createNamedParameter($addressUids, Connection::PARAM_INT_ARRAY),
                ),
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY),
                ),
                $this->buildLanguageConstraint($queryBuilder, $this->getLanguageUidsOfJobs($languageAspect)),
            )
            ->orderBy('uid', 'ASC');
    }

    /**
     * Sorted in PHP after the language overlay: the visitor sees the
     * translated title, SQL could only sort by the title of the default
     * language. Equal titles are ordered by UID.
     *
     * @param array<int, array<string, mixed>> $jobRows
     * @return array<int, array<string, mixed>>
     */
    private function sortByTitle(array $jobRows, SiteLanguage $siteLanguage): array
    {
        $collator = $this->createCollator($siteLanguage);
        usort($jobRows, static function (array $a, array $b) use ($collator): int {
            $result = (int)$collator->compare((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));

            return $result !== 0 ? $result : (int)($a['uid'] ?? 0) <=> (int)($b['uid'] ?? 0);
        });

        return $jobRows;
    }

    /**
     * Case insensitive (strength "secondary": accents still count) with
     * natural order of numbers ("Job 2" before "Job 10"). ext-intl is
     * required by composer.json.
     */
    private function createCollator(SiteLanguage $siteLanguage): \Collator
    {
        $collator = new \Collator($siteLanguage->getLocale()->posixFormatted());
        $collator->setStrength(\Collator::SECONDARY);
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);

        return $collator;
    }

    /**
     * Like Extbase: records of the default language, which are translated by
     * overlays. Without overlays (fallback type "free"), the records of the
     * language itself.
     *
     * @return int[]
     */
    private function getLanguageUidsOfJobs(LanguageAspect $languageAspect): array
    {
        return $languageAspect->getOverlayType() === LanguageAspect::OVERLAYS_OFF
            ? [$languageAspect->getContentId(), -1]
            : [0, -1];
    }

    /**
     * Jobs without translation are skipped in strict mode, like Extbase does.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function getLanguageOverlays(array $rows, LanguageAspect $languageAspect): array
    {
        $overlays = [];
        foreach ($rows as $row) {
            $overlay = $this->getLanguageOverlay(self::TABLE_JOB, $row, $languageAspect);
            if ($overlay !== null) {
                $overlays[] = $overlay;
            }
        }

        return $overlays;
    }

    /**
     * Like Extbase translates relations (DataMapper::getPreparedQuery()): the
     * address is overlaid also without overlays of the jobs (fallback type
     * "free", the jobs of all languages reference the default address) and
     * keeps the default language, if it has no translation.
     *
     * @param array<string, mixed> $addressRow
     * @return array<string, mixed>
     */
    private function getAddressOverlay(array $addressRow, LanguageAspect $languageAspect): array
    {
        if ($languageAspect->getOverlayType() === LanguageAspect::OVERLAYS_OFF) {
            $languageAspect = new LanguageAspect(
                $languageAspect->getId(),
                $languageAspect->getContentId(),
                LanguageAspect::OVERLAYS_MIXED,
                $languageAspect->getFallbackChain(),
            );
        }

        return $this->getLanguageOverlay(self::TABLE_ADDRESS, $addressRow, $languageAspect) ?? $addressRow;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function getLanguageOverlay(string $table, array $row, LanguageAspect $languageAspect): ?array
    {
        // PageRepository depends on the Context of the current request
        return GeneralUtility::makeInstance(PageRepository::class)->getLanguageOverlay($table, $row, $languageAspect);
    }

    /**
     * Imported jobs with an external link (column "link") link to it, like
     * Partials/Job/TitleLink.html does. Only http(s) URLs are accepted.
     *
     * @param array<int, array<string, mixed>> $jobRows
     * @return JobLink[]
     */
    private function buildJobLinks(array $jobRows, ServerRequestInterface $request): array
    {
        $jobLinks = [];
        foreach ($jobRows as $jobRow) {
            $jobUid = (int)($jobRow['uid'] ?? 0);
            $externalLink = trim((string)($jobRow['link'] ?? ''));
            $isExternal = preg_match('#^https?://#i', $externalLink) === 1;
            $jobLinks[] = new JobLink(
                $jobUid,
                (string)($jobRow['title'] ?? ''),
                $isExternal ? $externalLink : $this->jobUriBuilder->buildDetailUri($jobUid, $request),
                $isExternal,
            );
        }

        return $jobLinks;
    }

    /**
     * @param int[] $languageUids
     */
    private function buildLanguageConstraint(QueryBuilder $queryBuilder, array $languageUids): string
    {
        return $queryBuilder->expr()->in(
            'sys_language_uid',
            $queryBuilder->createNamedParameter($languageUids, Connection::PARAM_INT_ARRAY),
        );
    }

    /**
     * Hidden, deleted, scheduled and access-restricted records are excluded
     * by the frontend restrictions.
     */
    private function createQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        return $queryBuilder;
    }
}
