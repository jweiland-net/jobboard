<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\JobLocation;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders the Fluid template "Templates/Map/InfoWindow.html" with the root
 * paths of "plugin.tx_jobboard.view", so integrators can override it like
 * the templates of the plugin.
 *
 * The info window is requested by a middleware of EXT:maps2: there is no
 * Extbase request and no ContentObjectRenderer. On a cached page, the
 * TypoScript setup is not available, then the root paths of the site
 * settings "jobboard.templateRootPath" etc. are used (the same values the
 * site set uses as TypoScript constants).
 */
final readonly class JobLocationInfoWindowRenderer implements JobLocationInfoWindowRendererInterface
{
    /**
     * Type of root paths => [default root path, site setting]
     */
    private const ROOT_PATHS = [
        'templateRootPaths' => ['EXT:jobboard/Resources/Private/Templates/', 'jobboard.templateRootPath'],
        'partialRootPaths' => ['EXT:jobboard/Resources/Private/Partials/', 'jobboard.partialRootPath'],
        'layoutRootPaths' => ['EXT:jobboard/Resources/Private/Layouts/', 'jobboard.layoutRootPath'],
    ];

    public function __construct(
        private ViewFactoryInterface $viewFactory,
    ) {}

    public function render(JobLocation $jobLocation, ServerRequestInterface $request): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $this->getRootPaths('templateRootPaths', $request),
            partialRootPaths: $this->getRootPaths('partialRootPaths', $request),
            layoutRootPaths: $this->getRootPaths('layoutRootPaths', $request),
            request: $request,
        ));
        $view->assign('jobLocation', $jobLocation);

        return trim($view->render(self::TEMPLATE));
    }

    /**
     * @return array<int|string, string>
     */
    private function getRootPaths(string $type, ServerRequestInterface $request): array
    {
        $rootPaths = $this->getRootPathsFromTypoScript($type, $request);

        return $rootPaths !== [] ? $rootPaths : $this->getRootPathsFromSiteSettings($type, $request);
    }

    /**
     * @return array<int|string, string>
     */
    private function getRootPathsFromTypoScript(string $type, ServerRequestInterface $request): array
    {
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript || !$frontendTypoScript->hasSetup()) {
            return [];
        }

        try {
            $rootPaths = $frontendTypoScript->getSetupArray()['plugin.']['tx_jobboard.']['view.'][$type . '.'] ?? [];
        } catch (\RuntimeException) {
            return [];
        }

        return is_array($rootPaths) ? array_filter($rootPaths, static fn(mixed $path): bool => is_string($path)) : [];
    }

    /**
     * @return array<int, string>
     */
    private function getRootPathsFromSiteSettings(string $type, ServerRequestInterface $request): array
    {
        [$defaultRootPath, $siteSetting] = self::ROOT_PATHS[$type];
        $site = $request->getAttribute('site');
        $rootPath = $site instanceof Site ? (string)$site->getSettings()->get($siteSetting, '') : '';

        return array_filter([$defaultRootPath, $rootPath], static fn(string $path): bool => $path !== '');
    }
}
