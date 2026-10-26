<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\EventListener;

use JWeiland\Jobboard\EventListener\RenderJobLocationInfoWindowContentEventListener;
use JWeiland\Jobboard\Middleware\RememberJobMiddleware;
use JWeiland\Jobboard\Service\StoragePidResolverInterface;
use JWeiland\Maps2\Event\RenderInfoWindowContentEvent;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * EXT:maps2 is optional. Its classes can be autoloaded (require-dev), but
 * without the extension the listener must not be registered (its
 * dependencies need the column "tt_address.tx_maps2_uid") and the container
 * must still be built.
 */
final class RenderJobLocationInfoWindowContentEventListenerWithoutMaps2Test extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    #[Test]
    public function maps2IsNotLoaded(): void
    {
        self::assertFalse(ExtensionManagementUtility::isLoaded('maps2'));
        self::assertFalse($this->get(TcaSchemaFactory::class)->get('tt_address')->hasField('tx_maps2_uid'));
    }

    #[Test]
    public function listenerIsNotRegistered(): void
    {
        self::assertFalse($this->has(RenderJobLocationInfoWindowContentEventListener::class));
    }

    #[Test]
    public function containerProvidesServicesOfTheExtension(): void
    {
        self::assertInstanceOf(RememberJobMiddleware::class, $this->get(RememberJobMiddleware::class));
        self::assertInstanceOf(StoragePidResolverInterface::class, $this->get(StoragePidResolverInterface::class));
    }

    #[Test]
    public function infoWindowContentEventIsNotHandled(): void
    {
        $event = new RenderInfoWindowContentEvent(['uid' => 1], new ServerRequest('https://example.com/', 'POST'));

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        self::assertSame('', $event->getInfoWindowContent());
    }
}
