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
use JWeiland\Jobboard\Service\MapPoiCollectionServiceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * The addresses of the jobs come from JobSearchOptionsServiceInterface, see
 * JobSearchOptionsServiceTest. Here they are given by a stub.
 */
final class MapPoiCollectionServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AddressPoiCollection.csv');
    }

    /**
     * @param int[] $addressUids
     * @return int[]
     */
    private function getPoiCollectionUids(array $addressUids): array
    {
        $jobSearchOptionsService = self::createStub(JobSearchOptionsServiceInterface::class);
        $jobSearchOptionsService->method('getAddressUids')->willReturn($addressUids);
        $subject = new MapPoiCollectionService(
            $jobSearchOptionsService,
            $this->get(ConnectionPool::class),
            $this->get(PersistenceManagerInterface::class),
        );

        $poiCollectionUids = [];
        foreach ($subject->getPoiCollections(self::createStub(QueryInterface::class)) as $poiCollection) {
            self::assertInstanceOf(DomainObjectInterface::class, $poiCollection);
            $poiCollectionUids[] = (int)$poiCollection->getUid();
        }

        return $poiCollectionUids;
    }

    #[Test]
    public function serviceIsRegisteredForInterface(): void
    {
        self::assertInstanceOf(MapPoiCollectionService::class, $this->get(MapPoiCollectionServiceInterface::class));
    }

    /**
     * @return array<string, array{int[], int[]}>
     */
    public static function addressUidsDataProvider(): array
    {
        return [
            'no addresses' => [[], []],
            'one marker for two addresses at the same location' => [[1, 2], [1]],
            'markers in ascending order of the PoiCollections' => [[3, 1], [1, 2]],
            'hidden address is skipped' => [[4], []],
            'address without location is skipped' => [[5], []],
            'translated address uses location of default address' => [[6], [2]],
            'translated address and its default address share one marker' => [[3, 6], [2]],
            'translated address of address without location is skipped' => [[7], []],
            'hidden location is skipped' => [[8], []],
            'unknown and invalid UIDs are skipped' => [[999, 0, -1], []],
        ];
    }

    /**
     * @param int[] $addressUids
     * @param int[] $expectedPoiCollectionUids
     */
    #[Test]
    #[DataProvider('addressUidsDataProvider')]
    public function getPoiCollectionsReturnsLocationsOfVisibleAddresses(
        array $addressUids,
        array $expectedPoiCollectionUids,
    ): void {
        self::assertSame($expectedPoiCollectionUids, $this->getPoiCollectionUids($addressUids));
    }
}
