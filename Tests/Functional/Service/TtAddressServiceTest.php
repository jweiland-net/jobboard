<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\ApiModel\ApiModelInterface;
use JWeiland\Jobboard\ApiModel\ApiModelTrait;
use JWeiland\Jobboard\ApiModel\JobModel;
use JWeiland\Jobboard\Service\TtAddressService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class TtAddressServiceTest extends FunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private TtAddressService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TtAddressService.csv');

        $this->subject = $this->get(TtAddressService::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    private function buildApiModel(): ApiModelInterface
    {
        return (new class () implements ApiModelInterface {
            use ApiModelTrait;

            private const NAME = 'test';

            private const API_ENDPOINT = 'https://example.org/api/jobs.xml';

            private const MAPPING = [];

            private int $storagePid = 0;
        })->withStoragePid(self::STORAGE_PAGE);
    }

    private function buildJobModel(string $technicalName): JobModel
    {
        return new JobModel(new \SimpleXMLElement(
            '<job>
                <manager_name>Jane Manager</manager_name>
                <manager_email>jane@example.org</manager_email>
                <locations>
                    <location primary="true">
                        <technical_name>' . $technicalName . '</technical_name>
                        <de>New Employer GmbH</de>
                        <street>New Street 5</street>
                        <zipcode>12345</zipcode>
                        <city>New City</city>
                    </location>
                </locations>
            </job>',
        ));
    }

    #[Test]
    public function getPreparedAddressByImportedJobMapsAllFields(): void
    {
        $preparedAddress = $this->subject->getPreparedAddressByImportedJob(
            $this->buildJobModel('loc-new'),
            $this->buildApiModel(),
        );

        self::assertSame(self::STORAGE_PAGE, $preparedAddress['pid']);
        self::assertSame('Jane Manager', $preparedAddress['name']);
        self::assertSame('jane@example.org', $preparedAddress['email']);
        self::assertSame('New Employer GmbH', $preparedAddress['company']);
        self::assertSame('New Street 5', $preparedAddress['address']);
        self::assertSame('12345', $preparedAddress['zip']);
        self::assertSame('New City', $preparedAddress['city']);
        self::assertSame('loc-new', $preparedAddress['import_key']);
    }

    #[Test]
    public function getPreparedAddressByImportedJobWithUnknownImportKeyReturnsNewUid(): void
    {
        $preparedAddress = $this->subject->getPreparedAddressByImportedJob(
            $this->buildJobModel('loc-new'),
            $this->buildApiModel(),
        );

        self::assertIsString($preparedAddress['uid']);
        self::assertStringStartsWith('NEW', $preparedAddress['uid']);
    }

    #[Test]
    public function getPreparedAddressByImportedJobWithExistingImportKeyReturnsItsUid(): void
    {
        $preparedAddress = $this->subject->getPreparedAddressByImportedJob(
            $this->buildJobModel('loc-existing'),
            $this->buildApiModel(),
        );

        self::assertSame(1, $preparedAddress['uid']);
    }
}
