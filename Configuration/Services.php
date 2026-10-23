<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Jobboard\EventListener\RenderJobLocationInfoWindowContentEventListener;
use JWeiland\Maps2\Service\InfoWindowContentService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    // The listener of the info window content of EXT:maps2 is only registered,
    // if EXT:maps2 is loaded. The service of EXT:maps2 dispatching the event
    // is already defined then, as EXT:maps2 is a suggestion of this extension
    // and therefore loaded before (composer.json/ext_emconf.php).
    // EMU::isLoaded() can not be used, as the PackageManager is not available
    // in ExtensionManagementUtility while the container is built. class_exists()
    // is not sufficient either: the classes of EXT:maps2 can be autoloaded
    // without EXT:maps2 being loaded (e.g. require-dev in functional tests).
    // The listener is excluded in Services.yaml, which is loaded after this file.
    if ($containerBuilder->hasDefinition(InfoWindowContentService::class)) {
        $configurator->services()
            ->set(RenderJobLocationInfoWindowContentEventListener::class)
            ->autowire()
            ->autoconfigure();
    }
};
