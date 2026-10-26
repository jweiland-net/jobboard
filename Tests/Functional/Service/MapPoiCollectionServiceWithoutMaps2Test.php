<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\JobSearchOptionsServiceInterface;
use JWeiland\Jobboard\Service\MapPoiCollectionService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * EXT:maps2 is optional: without it, there is no map and no query is needed.
 */
final class MapPoiCollectionServiceWithoutMaps2Test extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    #[Test]
    public function getPoiCollectionsReturnsEmptyStorageWithoutQueryingAddresses(): void
    {
        $jobSearchOptionsService = $this->createMock(JobSearchOptionsServiceInterface::class);
        $jobSearchOptionsService->expects($this->never())->method('getAddressUids');
        $subject = new MapPoiCollectionService(
            $jobSearchOptionsService,
            $this->get(ConnectionPool::class),
            $this->get(PersistenceManagerInterface::class),
            $this->get(TcaSchemaFactory::class),
        );

        self::assertCount(0, $subject->getPoiCollections(self::createStub(QueryInterface::class)));
    }
}
