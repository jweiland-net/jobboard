<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Repository;

use JWeiland\Jobboard\Domain\Model\JobArea;
use JWeiland\Jobboard\Domain\Model\JobAreaRestriction;
use JWeiland\Jobboard\Domain\Model\JobRole;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\Domain\Model\Search;
use JWeiland\Jobboard\Domain\Model\ZipCity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Allows to help search for jobs by various search criteria
 *
 * Deliberately does not filter out jobs without resolvable salary information -
 * that is a display rule, not a data access concern, and belongs in
 * JobboardController (see excludeJobsWithoutSalaryInformation()).
 */
class JobRepository extends Repository
{
    /**
     * Jobs with the closest application deadline first. Jobs without an
     * application deadline (0) are sorted to the top by the database.
     */
    protected $defaultOrderings = [
        'applicationDeadline' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findBySettings(array $settings, int $limit = 0): QueryResultInterface
    {
        $query = $this->createQuery();

        $andConstraint = [
            ...$this->buildAndConstraintForOpenJobs($query),
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
    public function findBySearch(Search $search, int $limit = 0, array $settings = []): QueryResultInterface
    {
        $query = $this->createQuery();

        $andConstraint = [
            ...$this->buildAndConstraintForOpenJobs($query),
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

    private function buildAndConstraintForRelations(Search $search, QueryInterface $query): array
    {
        $andConstraint = [];

        if ($search->getJobArea() instanceof JobArea) {
            $andConstraint[] = $query->contains('jobAreas', $search->getJobArea());
        }

        if ($search->getJobRole() instanceof JobRole) {
            $andConstraint[] = $query->equals('jobRole', $search->getJobRole());
        }

        if ($search->getJobType() instanceof JobType) {
            $andConstraint[] = $query->equals('jobType', $search->getJobType());
        }

        return $andConstraint;
    }

    /**
     * Base constraints for every job listed in the frontend: it needs a title,
     * its application deadline must not have passed and, for fixed-term
     * positions, its employment must not have ended already.
     */
    private function buildAndConstraintForOpenJobs(QueryInterface $query): array
    {
        $today = new \DateTime('today');

        return [
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
