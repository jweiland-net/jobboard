<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\RememberedJobs;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Stores the remembered jobs in the session data of the TYPO3 frontend user
 * (request attribute "frontend.user"). This works for anonymous visitors as
 * well: storing data fixates an anonymous session and TYPO3 sets the cookie
 * "fe_typo_user". The data lives as long as this session, see
 * $GLOBALS['TYPO3_CONF_VARS']['FE']['sessionDataLifetime'].
 *
 * The session data is a plain list of integers (job UIDs of the default
 * language), never an object.
 */
final readonly class RememberedJobsService implements RememberedJobsServiceInterface
{
    public const SESSION_KEY = 'tx_jobboard_remembered_jobs';

    public function getRememberedJobs(ServerRequestInterface $request): RememberedJobs
    {
        $frontendUser = $this->getFrontendUser($request);
        if ($frontendUser === null) {
            return RememberedJobs::createEmpty();
        }

        return RememberedJobs::fromSessionData($frontendUser->getKey('ses', self::SESSION_KEY));
    }

    /**
     * An empty list removes the key from the session data. Without any other
     * session data, TYPO3 then removes the anonymous session and its cookie.
     */
    public function storeRememberedJobs(ServerRequestInterface $request, RememberedJobs $rememberedJobs): void
    {
        $this->getFrontendUser($request)?->setAndSaveSessionData(
            self::SESSION_KEY,
            $rememberedJobs->isEmpty() ? null : $rememberedJobs->getUids(),
        );
    }

    private function getFrontendUser(ServerRequestInterface $request): ?FrontendUserAuthentication
    {
        $frontendUser = $request->getAttribute('frontend.user');

        return $frontendUser instanceof FrontendUserAuthentication ? $frontendUser : null;
    }
}
