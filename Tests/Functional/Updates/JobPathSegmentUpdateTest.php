<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Updates;

use JWeiland\Jobboard\Updates\JobPathSegmentUpdate;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobPathSegmentUpdateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    private JobPathSegmentUpdate $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobPathSegmentUpdate.csv');

        $this->subject = GeneralUtility::makeInstance(
            JobPathSegmentUpdate::class,
            $this->get(ConnectionPool::class),
        );
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenJobsWithoutPathSegmentExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseOnceUpdated(): void
    {
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateGeneratesPathSegmentFromTitle(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('sample-job', $this->getPathSegmentOfJob(1));
    }

    #[Test]
    public function executeUpdateKeepsExistingPathSegment(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('custom-segment', $this->getPathSegmentOfJob(2));
        self::assertSame('sample-job-title', $this->getPathSegmentOfJob(5));
    }

    #[Test]
    public function executeUpdateGeneratesUniquePathSegmentsForHiddenJobsToo(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('duplicate-title', $this->getPathSegmentOfJob(3));
        self::assertSame('duplicate-title-1', $this->getPathSegmentOfJob(4));
    }

    #[Test]
    public function executeUpdateAvoidsPathSegmentAlreadyInUse(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('sample-job-title-1', $this->getPathSegmentOfJob(6));
    }

    #[Test]
    public function executeUpdateIgnoresDeletedJobs(): void
    {
        $this->subject->executeUpdate();

        self::assertSame('', $this->getPathSegmentOfJob(7));
    }

    /**
     * Connection::select() applies the default restrictions, which would hide
     * hidden and deleted jobs. So, use a QueryBuilder without restrictions.
     */
    private function getPathSegmentOfJob(int $jobUid): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();

        return (string)$queryBuilder
            ->select('path_segment')
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($jobUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
