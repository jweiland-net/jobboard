<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\EventListener;

use JWeiland\Jobboard\Service\JobLocationInfoWindowRendererInterface;
use JWeiland\Jobboard\Service\JobLocationServiceInterface;
use JWeiland\Maps2\Event\RenderInfoWindowContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Renders the info window of a map marker of EXT:maps2, which belongs to a
 * job location (tt_address): name and city of the location and links to its
 * open jobs. The event is dispatched for every PoiCollection of the site.
 * If no address with open jobs is linked to the PoiCollection, the content
 * stays untouched, so EXT:maps2 (or another listener) renders it.
 *
 * EXT:maps2 is optional: this class is excluded in Services.yaml and only
 * registered by Configuration/Services.php, if EXT:maps2 is loaded.
 */
#[AsEventListener('jobboard/renderJobLocationInfoWindowContent')]
final readonly class RenderJobLocationInfoWindowContentEventListener
{
    public function __construct(
        private JobLocationServiceInterface $jobLocationService,
        private JobLocationInfoWindowRendererInterface $jobLocationInfoWindowRenderer,
    ) {}

    public function __invoke(RenderInfoWindowContentEvent $event): void
    {
        // Already rendered by another listener
        if ($event->getInfoWindowContent() !== '') {
            return;
        }

        $request = $event->getRequest();
        $jobLocation = $this->jobLocationService->findByPoiCollection(
            (int)($event->getPoiCollectionRecord()['uid'] ?? 0),
            $request,
        );

        if ($jobLocation !== null) {
            $event->setInfoWindowContent($this->jobLocationInfoWindowRenderer->render($jobLocation, $request));
        }
    }
}
