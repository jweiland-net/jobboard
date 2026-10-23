<?php

declare(strict_types=1);

/*
 * This file is part of the package vendor/my-sitepackage.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Vendor\MySitepackage\EventListener;

use JWeiland\Maps2\Event\RenderInfoWindowContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(
    identifier: 'my-sitepackage/job-location-info-window',
    before: 'jobboard/renderJobLocationInfoWindowContent',
)]
final readonly class MyJobLocationInfoWindowEventListener
{
    public function __invoke(RenderInfoWindowContentEvent $event): void
    {
        $poiCollectionUid = (int)($event->getPoiCollectionRecord()['uid'] ?? 0);
        if ($poiCollectionUid === 0) {
            return;
        }

        // Render your own content. As the content is not empty anymore, the
        // listener of the job board does not change it.
        $event->setInfoWindowContent(
            '<p>' . htmlspecialchars('Location ' . $poiCollectionUid) . '</p>',
        );
    }
}
