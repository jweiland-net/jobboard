<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ViewHelpers;

use JWeiland\Jobboard\Domain\Model\Job;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collects the PoiCollections of EXT:maps2 of all given jobs to show them
 * as markers on a map. EXT:maps2 is optional: without it, no address has a
 * PoiCollection and an empty ObjectStorage is returned.
 */
class JobPoiCollectionViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'jobs',
            \Traversable::class,
            'Jobs',
        );
    }

    public function render(): ObjectStorage
    {
        $poiCollections = new ObjectStorage();
        /** @var Job $job */
        foreach ($this->arguments['jobs'] as $job) {
            if ($job->getAddress() && $poiCollection = $job->getAddress()->getTxMaps2Uid()) {
                $poiCollection->setTitle($job->getTitle());
                if ($job->getApplicationDeadline() && $job->getJobType()) {
                    $poiCollection->setInfoWindowContent(
                        LocalizationUtility::translate('poi_collection.apply_until', 'jobboard')
                        . '&nbsp;'
                        . $job->getApplicationDeadline()->format('d.m.Y')
                        . '<br />'
                        . $job->getJobType()->getTitle(),
                    );
                }
                $poiCollections->attach($poiCollection);
            }
        }
        return $poiCollections;
    }
}
