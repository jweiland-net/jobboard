<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\PageTitle;

use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides the title of the job requested in the detail view as page title.
 * Activated via "config.pageTitleProviders.jobboard" in the "jweiland/jobboard"
 * site set. Returns an empty string on all other pages, so the next provider
 * is used.
 */
final class JobPageTitleProvider extends AbstractPageTitleProvider
{
    use ConnectionPoolTrait;

    private const PLUGIN_NAMESPACE = 'tx_jobboard_jobboard';
    private const TABLE_NAME = 'tx_jobboard_domain_model_job';

    public function getTitle(): string
    {
        $jobUid = $this->getJobUidFromRequest();
        if ($jobUid === 0) {
            return '';
        }

        return trim((string)($this->getJobRecord($jobUid)['title'] ?? ''));
    }

    private function getJobUidFromRequest(): int
    {
        $routing = $this->request->getAttribute('routing');
        $arguments = $routing instanceof PageArguments
            ? $routing->getArguments()
            : $this->request->getQueryParams();

        $pluginArguments = $arguments[self::PLUGIN_NAMESPACE] ?? [];
        if (!is_array($pluginArguments)) {
            return 0;
        }

        // Argument "job" is only used by the detail action
        return (int)($pluginArguments['job'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJobRecord(int $jobUid): array
    {
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE_NAME);
        $jobRecord = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($jobRecord)) {
            return [];
        }

        return GeneralUtility::makeInstance(PageRepository::class)
            ->getLanguageOverlay(self::TABLE_NAME, $jobRecord) ?? [];
    }
}
