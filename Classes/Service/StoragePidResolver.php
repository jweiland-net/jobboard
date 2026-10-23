<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reads "jobboard.storagePid" from the Site Settings instead of accepting it
 * from the request, so the storage pages can not be widened by a manipulated
 * request.
 */
final readonly class StoragePidResolver implements StoragePidResolverInterface
{
    public function getStoragePids(ServerRequestInterface $request): array
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return [];
        }

        $storagePids = array_filter(
            GeneralUtility::intExplode(',', (string)$site->getSettings()->get('jobboard.storagePid', '0'), true),
            static fn(int $storagePid): bool => $storagePid > 0,
        );
        if ($storagePids === []) {
            return [];
        }

        // PageRepository depends on the Context of the current request
        return GeneralUtility::makeInstance(PageRepository::class)
            ->getPageIdsRecursive(array_values($storagePids), self::STORAGE_DEPTH);
    }
}
