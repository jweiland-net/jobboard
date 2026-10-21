<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\PageTitle;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

/**
 * Provides the title of the currently displayed job as page title on the
 * detail view. Activated via "config.pageTitleProviders.jobboard" in the
 * "jweiland/jobboard" site set.
 *
 * AbstractPageTitleProvider implements SingletonInterface, so this class is
 * a shared, public service: the instance injected into the controller is the
 * same one the PageTitleProviderManager fetches from the container.
 */
final class JobPageTitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = trim($title);
    }
}
