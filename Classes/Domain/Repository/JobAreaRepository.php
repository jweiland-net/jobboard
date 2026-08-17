<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class JobAreaRepository extends Repository
{
    public function findByUids(array $uids): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->in('uid', $uids),
        );

        return $query->execute();
    }
}
