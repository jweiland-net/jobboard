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
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrates existing "tt_content" records and backend user group permissions from the old CType
 * "jobfair2_jobfair" to the new "jobboard_jobboard", after the plugin was renamed from "Jobfair2"/
 * "Jobfair" to "Jobboard".
 *
 * This is intentionally NOT based on {@see \TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate}:
 * that base class only migrates `CType = 'list' AND list_type = ...` records, but existing records
 * here already carry `CType = 'jobfair2_jobfair'` (the former list_type -> CType migration already ran,
 * see {@see JWeilandJobfair2CTypeMigration}). The base class would therefore never find a match.
 */
#[UpgradeWizard('jweilandJobboardJobfairToJobboardCTypeMigration')]
final class JobfairToJobboardCTypeMigration implements UpgradeWizardInterface
{
    private const TABLE_CONTENT = 'tt_content';
    private const TABLE_BACKEND_USER_GROUPS = 'be_groups';
    private const OLD_CTYPE = 'jobfair2_jobfair';
    private const NEW_CTYPE = 'jobboard_jobboard';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return '[jobboard] Migrate "Jobfair2" content elements to the renamed "Jobboard" CType.';
    }

    public function getDescription(): string
    {
        return 'The content element type of this extension was renamed from "jobfair2_jobfair" to '
            . '"jobboard_jobboard". This wizard updates existing "tt_content" records and backend user '
            . 'group permissions to use the new CType.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->hasContentElementsToUpdate() || $this->hasBackendUserGroupsToUpdate();
    }

    public function executeUpdate(): bool
    {
        if ($this->hasContentElementsToUpdate()) {
            $this->updateContentElements();
        }

        if ($this->hasBackendUserGroupsToUpdate()) {
            $this->updateBackendUserGroups();
        }

        return true;
    }

    private function hasContentElementsToUpdate(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CONTENT);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->count('uid')
            ->from(self::TABLE_CONTENT)
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter(self::OLD_CTYPE)),
            );

        return (bool)$queryBuilder->executeQuery()->fetchOne();
    }

    private function updateContentElements(): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CONTENT);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update(self::TABLE_CONTENT)
            ->set('CType', self::NEW_CTYPE)
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter(self::OLD_CTYPE)),
            )
            ->executeStatement();
    }

    private function hasBackendUserGroupsToUpdate(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_BACKEND_USER_GROUPS);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->count('uid')
            ->from(self::TABLE_BACKEND_USER_GROUPS)
            ->where(
                $queryBuilder->expr()->like(
                    'explicit_allowdeny',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards('tt_content:CType:' . self::OLD_CTYPE) . '%',
                    ),
                ),
            );

        return (bool)$queryBuilder->executeQuery()->fetchOne();
    }

    private function updateBackendUserGroups(): void
    {
        $selectQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_BACKEND_USER_GROUPS);
        $selectQueryBuilder->getRestrictions()->removeAll();
        $groups = $selectQueryBuilder
            ->select('uid', 'explicit_allowdeny')
            ->from(self::TABLE_BACKEND_USER_GROUPS)
            ->where(
                $selectQueryBuilder->expr()->like(
                    'explicit_allowdeny',
                    $selectQueryBuilder->createNamedParameter(
                        '%' . $selectQueryBuilder->escapeLikeWildcards('tt_content:CType:' . self::OLD_CTYPE) . '%',
                    ),
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_BACKEND_USER_GROUPS);
        foreach ($groups as $group) {
            $updatedValue = str_replace(
                'tt_content:CType:' . self::OLD_CTYPE,
                'tt_content:CType:' . self::NEW_CTYPE,
                (string)$group['explicit_allowdeny'],
            );

            $connection->update(
                self::TABLE_BACKEND_USER_GROUPS,
                ['explicit_allowdeny' => $updatedValue],
                ['uid' => (int)$group['uid']],
            );
        }
    }
}
