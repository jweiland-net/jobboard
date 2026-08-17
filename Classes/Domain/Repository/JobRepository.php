<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Repository;

use TYPO3\CMS\Core\Utility\MathUtility;
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
        'ending_date' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findBySearchCriteria(array $searchCriteria, int $limit = 0): QueryResultInterface
    {
        $query = $this->createQuery();

        $andConstraint = [
            $query->logicalNot($query->equals('title', '')),
        ];

        $andConstraint[] = $query->logicalOr(
            $query->equals('ending_date', 0),
            $query->greaterThanOrEqual('ending_date', new \DateTime()),
        );

        foreach ($searchCriteria as $property => $searchValue) {
            if (is_array($searchValue)) {
                $andConstraint[] = $query->in($property, $searchValue);
                continue;
            }

            if ($property === 'address') {
                $orConstraint = $this->buildOrConstraintForAddress($searchValue, $query);

                if ($orConstraint !== []) {
                    $andConstraint[] = $query->logicalOr(...$orConstraint);
                }

                continue;
            }

            if (is_object($searchValue)) {
                $andConstraint[] = $query->equals($property, $searchValue);
            } elseif (is_string($searchValue)) {
                $andConstraint[] = $query->like($property, $searchValue);
            }
        }

        if ($limit) {
            $query->setLimit($limit);
        }

        return $query->matching($query->logicalAnd(...$andConstraint))->execute();
    }

    private function buildOrConstraintForAddress(string $searchValue, QueryInterface $query): array
    {
        [$zip, $city] = $this->extractZipAndCityFromSearchValue($searchValue);

        $orConstraint = [];

        if ($zip) {
            // For zip, we do an exact search
            $orConstraint[] = $query->equals('address.zip', $zip);
        }

        if ($city) {
            // For city, we start a like search
            $orConstraint[] = $query->like(
                'address.city',
                '%' . addcslashes($city, '_%') . '%',
            );
        }

        return $orConstraint;
    }

    /**
     * A customer can select a value from auto-complete. In that case the search value
     * has the following structure "zip - city". Without using auto-complete the
     * value is either a zip or a city
     *
     * @param string $searchValue
     * @return array
     */
    private function extractZipAndCityFromSearchValue(string $searchValue): array
    {
        if (str_contains($searchValue, '-')) {
            [$zip, $city] = explode(' - ', $searchValue);
        } elseif (MathUtility::canBeInterpretedAsInteger($searchValue)) {
            // Sure, zip is not an integer because of leading zero, but it
            // still can be interpreted as an integer 03524 -> 3524
            $zip = $searchValue;
            $city = null;
        } else {
            $zip = null;
            $city = $searchValue;
        }

        return [$zip, $city];
    }
}
