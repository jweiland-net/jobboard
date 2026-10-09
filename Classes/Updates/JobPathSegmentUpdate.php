<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Updates;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Fills the empty slug column "path_segment" of job records. New jobs get
 * their slug from DataHandler automatically, but jobs created before the
 * slug column existed (or written without DataHandler) have none, which
 * would break their speaking detail URLs.
 *
 * The slug is generated with TYPO3's SlugHelper based on the TCA
 * configuration of "path_segment", including its uniqueness evaluation.
 */
#[UpgradeWizard('jweilandJobboardJobPathSegmentUpdate')]
final readonly class JobPathSegmentUpdate implements UpgradeWizardInterface
{
    private const TABLE = 'tx_jobboard_domain_model_job';
    private const FIELD = 'path_segment';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Generate URL segments (slugs) of jobs.';
    }

    public function getDescription(): string
    {
        return 'Fills the empty slug column "' . self::FIELD . '" of all job records with a unique, '
            . 'URI compatible version of the job title. Needed for speaking URLs of the job detail view.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->getQueryBuilderForJobsWithoutPathSegment();

        return (int)$queryBuilder->count('uid')->executeQuery()->fetchOne() > 0;
    }

    public function executeUpdate(): bool
    {
        $slugHelper = $this->createSlugHelper();
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        // Fetch all rows first, so the updates below do not interfere with
        // an open result set of the same table
        $jobs = $this->getQueryBuilderForJobsWithoutPathSegment()
            ->select('uid', 'pid', 'title', 'sys_language_uid', 'l10n_parent')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($jobs as $job) {
            $connection->update(
                self::TABLE,
                [self::FIELD => $this->buildUniquePathSegment($slugHelper, $job)],
                ['uid' => (int)$job['uid']],
            );
        }

        return true;
    }

    private function getQueryBuilderForJobsWithoutPathSegment(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(self::FIELD, $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull(self::FIELD),
                ),
            );
    }

    private function createSlugHelper(): SlugHelper
    {
        return GeneralUtility::makeInstance(
            SlugHelper::class,
            self::TABLE,
            self::FIELD,
            $GLOBALS['TCA'][self::TABLE]['columns'][self::FIELD]['config'] ?? [],
        );
    }

    /**
     * @param array<string, mixed> $job
     */
    private function buildUniquePathSegment(SlugHelper $slugHelper, array $job): string
    {
        $pid = (int)$job['pid'];
        $pathSegment = $slugHelper->generate($job, $pid);
        $recordState = RecordStateFactory::forName(self::TABLE)->fromArray($job, $pid, (int)$job['uid']);

        return $slugHelper->buildSlugForUniqueInTable($pathSegment, $recordState);
    }
}
