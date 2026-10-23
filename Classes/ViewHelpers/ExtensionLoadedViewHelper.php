<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ViewHelpers;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns true, if the given extension is loaded. Used to tell optional
 * features (like the map of EXT:maps2) apart from missing configuration.
 *
 * {jobboard:extensionLoaded(extensionKey: 'maps2')}
 */
final class ExtensionLoadedViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('extensionKey', 'string', 'Extension key, e.g. "maps2"', true);
    }

    public function render(): bool
    {
        return ExtensionManagementUtility::isLoaded((string)$this->arguments['extensionKey']);
    }
}
