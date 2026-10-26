<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\ViewHelpers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Test case.
 */
final class AddressPoiCollectionViewHelperTest extends FunctionalTestCase
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
     * Comma separated UIDs of the returned PoiCollections
     *
     * @param array<int|string> $addressUids
     */
    private function renderPoiCollectionUids(array $addressUids): string
    {
        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}'
            . '<f:for each="{jobboard:addressPoiCollection(addressUids: addressUids)}" as="poiCollection"'
            . ' iteration="iterator">{poiCollection.uid}{f:if(condition: iterator.isLast, else: \',\')}</f:for>',
        );
        $context->getVariableProvider()->add('addressUids', $addressUids);

        return trim((string)(new TemplateView($context))->render());
    }

    /**
     * @return array<string, array{array<int|string>, string}>
     */
    public static function addressUidsDataProvider(): array
    {
        return [
            'no addresses' => [[], ''],
            'one marker for two addresses at the same location' => [[1, 2], '1'],
            'markers in ascending order of the PoiCollections' => [[3, 1], '1,2'],
            'address given twice' => [[1, 1], '1'],
            'hidden address is skipped' => [[4], ''],
            'address without location is skipped' => [[5], ''],
            'translated address uses location of default address' => [[6], '2'],
            'translated address and its default address share one marker' => [[3, 6], '2'],
            'translated address of address without location is skipped' => [[7], ''],
            'hidden location is skipped' => [[8], ''],
            'unknown and invalid UIDs are skipped' => [[999, 0, -1, 'abc'], ''],
            'UIDs as strings' => [['1', '3'], '1,2'],
        ];
    }

    /**
     * @param array<int|string> $addressUids
     */
    #[Test]
    #[DataProvider('addressUidsDataProvider')]
    public function renderReturnsPoiCollectionsOfVisibleAddresses(array $addressUids, string $expectedUids): void
    {
        self::assertSame($expectedUids, $this->renderPoiCollectionUids($addressUids));
    }
}
