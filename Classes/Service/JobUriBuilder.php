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
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Uses the router of the site, so the route enhancer of the plugin (see
 * Configuration/Routes/Default.yaml) and the cHash are applied like for the
 * links of the job list.
 */
final readonly class JobUriBuilder implements JobUriBuilderInterface
{
    private const PLUGIN_NAMESPACE = 'tx_jobboard_jobboard';

    public function buildDetailUri(int $jobUid, ServerRequestInterface $request): string
    {
        $site = $request->getAttribute('site');
        $pageId = $this->getTargetPageId($request);
        if ($jobUid <= 0 || $pageId === 0 || !$site instanceof Site) {
            return '';
        }

        try {
            return (string)$site->getRouter()->generateUri(
                $pageId,
                $this->buildParameters($jobUid, $request),
                '',
                RouterInterface::ABSOLUTE_PATH,
            );
        } catch (InvalidRouteArgumentsException) {
            return '';
        }
    }

    /**
     * Same arguments as Extbase builds for "f:link.action action='detail'"
     *
     * @return array<string, mixed>
     */
    private function buildParameters(int $jobUid, ServerRequestInterface $request): array
    {
        $parameters = [
            self::PLUGIN_NAMESPACE => [
                'action' => 'detail',
                'controller' => 'Jobboard',
                'job' => $jobUid,
            ],
        ];
        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $parameters['_language'] = $language;
        }

        return $parameters;
    }

    private function getTargetPageId(ServerRequestInterface $request): int
    {
        $site = $request->getAttribute('site');
        $pidOfDetailPage = $site instanceof Site
            ? (int)$site->getSettings()->get('jobboard.pidOfDetailPage', 0)
            : 0;
        if ($pidOfDetailPage > 0) {
            return $pidOfDetailPage;
        }

        $routing = $request->getAttribute('routing');

        return $routing instanceof PageArguments ? $routing->getPageId() : 0;
    }
}
