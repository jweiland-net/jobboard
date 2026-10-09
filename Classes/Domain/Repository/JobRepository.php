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
    protected $defaultOrderings = [
        'endingDate' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findBySettings(array $settings, int $limit = 0): QueryResultInterface
    {
        $query = $this->createQuery();

        $andConstraint = [
            $query->logicalNot($query->equals('title', '')),
        ];

        $andConstraint[] = $query->logicalOr(
            $query->equals('endingDate', 0),
            $query->greaterThanOrEqual('endingDate', new \DateTime()),
        );

        $jobAreaConstraints = $this->buildOrConstraintForJobAreas($settings['jobAreas'] ?? [], $query);
        if ($jobAreaConstraints !== []) {
            $andConstraint[] = $query->logicalOr(...$jobAreaConstraints);
        }

        if ($limit) {
            $query->setLimit($limit);
        }

        return $query->matching($query->logicalAnd(...$andConstraint))->execute();
    }

    public function findBySearch(Search $search, int $limit = 0): QueryResultInterface
    {
        $query = $this->createQuery();

        $andConstraint = [
            $query->logicalNot($query->equals('title', '')),
        ];

        $andConstraint[] = $query->logicalOr(
            $query->equals('endingDate', 0),
            $query->greaterThanOrEqual('endingDate', new \DateTime()),
        );

        if ($search->getJobArea() instanceof JobArea) {
            $andConstraint[] = $query->contains('jobAreas', $search->getJobArea());
        }

        if ($search->getJobRole() instanceof JobRole) {
            $andConstraint[] = $query->equals('jobRole', $search->getJobRole());
        }

        if ($search->getJobType() instanceof JobType) {
            $andConstraint[] = $query->equals('jobType', $search->getJobType());
        }

        $orConstraint = $this->buildOrConstraintForAddress($search->getZipCity(), $query);
        if ($orConstraint !== []) {
            $andConstraint[] = $query->logicalOr(...$orConstraint);
        }

        $andConstraint = [
            ...$andConstraint,
            ...$this->buildAndConstraintForSearchWords($search->getSearchWord(), $query),
        ];

        if ($limit) {
            $query->setLimit($limit);
        }

        return $query->matching($query->logicalAnd(...$andConstraint))->execute();
    }

    /**
     * A job matches, if any of its job areas is one of the configured job
     * areas. FlexForm delivers the selected uids as comma-separated string,
     * TypoScript may deliver them as array.
     *
     * @param string|int|int[]|string[] $jobAreas
     */
    private function buildOrConstraintForJobAreas(mixed $jobAreas, QueryInterface $query): array
    {
        if (is_array($jobAreas)) {
            $jobAreas = implode(',', $jobAreas);
        }

        if (!is_scalar($jobAreas)) {
            return [];
        }

        $orConstraint = [];
        foreach (GeneralUtility::intExplode(',', (string)$jobAreas, true) as $jobAreaUid) {
            if ($jobAreaUid > 0) {
                $orConstraint[] = $query->contains('jobAreas', $jobAreaUid);
            }
        }

        return $orConstraint;
    }

    private function buildOrConstraintForAddress(ZipCity $zipCity, QueryInterface $query): array
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

        return $orConstraint;
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
