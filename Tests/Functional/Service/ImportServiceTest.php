<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use GuzzleHttp\Psr7\Utils;
use JWeiland\Jobboard\ApiModel\ApiModelInterface;
use JWeiland\Jobboard\ApiModel\ApiModelTrait;
use JWeiland\Jobboard\Client\XmlClient;
use JWeiland\Jobboard\Configuration\ImportConfiguration;
use JWeiland\Jobboard\Service\ImportService;
use JWeiland\Jobboard\Service\JobService;
use JWeiland\Jobboard\Service\TtAddressService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class ImportServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ImportService.csv');

        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $GLOBALS['BE_USER']->backendCheckLogin();
    }

    private function buildApiModel(): ApiModelInterface
    {
        return new class () implements ApiModelInterface {
            use ApiModelTrait;

            private const NAME = 'test';

            private const API_ENDPOINT = 'https://example.org/api/jobs.xml';

            private const MAPPING = [
                'title' => [
                    'apiPath' => 'title/de',
                    'isDate' => false,
                    'default' => '',
                ],
                'vacancy_id' => [
                    'apiPath' => 'vacancy_id',
                    'isDate' => false,
                    'default' => 0,
                    'prefix' => self::NAME,
                ],
                'salary_mode' => [
                    'apiPath' => 'salary_mode',
                    'isDate' => false,
                    'default' => 1,
                ],
            ];

            private int $storagePid = 0;
        };
    }

    private function buildImportService(string $xmlResponse): ImportService
    {
        $xmlClient = self::createStub(XmlClient::class);
        $xmlClient->method('sendRequest')->willReturn(new Response(Utils::streamFor($xmlResponse)));

        return new ImportService(
            $xmlClient,
            $this->get(JobService::class),
            $this->get(TtAddressService::class),
            [$this->buildApiModel()],
            self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function importCreatesNewJobAndAddressWithMappedFieldsAndRelation(): void
    {
        $importService = $this->buildImportService(
            '<jobs>
                <job>
                    <vacancy_id>4711</vacancy_id>
                    <title><de>Fachkraft (m/w/d)</de></title>
                    <custom_select_4><de>IT</de></custom_select_4>
                    <custom_select_2><de>Vollzeit</de></custom_select_2>
                    <salary_mode>1</salary_mode>
                    <manager_name>Max Mustermann</manager_name>
                    <manager_email>max@example.org</manager_email>
                    <locations>
                        <location primary="true">
                            <technical_name>loc-1</technical_name>
                            <de>Example Company GmbH</de>
                            <street>Bahnhofstrasse 1</street>
                            <zipcode>76133</zipcode>
                            <city>Pforzheim</city>
                        </location>
                    </locations>
                </job>
            </jobs>',
        );

        self::assertTrue($importService->import(new ImportConfiguration(2)));

        $connectionPool = $this->get(ConnectionPool::class);

        $jobQueryBuilder = $connectionPool->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $jobQueryBuilder->getRestrictions()->removeAll();
        $job = $jobQueryBuilder
            ->select('*')
            ->from('tx_jobboard_domain_model_job')
            ->where($jobQueryBuilder->expr()->eq('vacancy_id', $jobQueryBuilder->createNamedParameter('test_4711')))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($job);
        self::assertSame('Fachkraft (m/w/d)', $job['title']);
        self::assertSame(1, (int)$job['job_area']);
        self::assertSame(1, (int)$job['job_type']);
        self::assertGreaterThan(0, (int)$job['address']);

        $addressQueryBuilder = $connectionPool->getQueryBuilderForTable('tt_address');
        $addressQueryBuilder->getRestrictions()->removeAll();
        $address = $addressQueryBuilder
            ->select('*')
            ->from('tt_address')
            ->where($addressQueryBuilder->expr()->eq('uid', $addressQueryBuilder->createNamedParameter((int)$job['address'])))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($address);
        self::assertSame('Example Company GmbH', $address['company']);
        self::assertSame('Bahnhofstrasse 1', $address['address']);
        self::assertSame('76133', $address['zip']);
        self::assertSame('Pforzheim', $address['city']);
        self::assertSame('Max Mustermann', $address['name']);
        self::assertSame('max@example.org', $address['email']);
        self::assertSame('loc-1', $address['import_key']);
    }

    #[Test]
    public function importGeneratesPathSegmentForNewJob(): void
    {
        $importService = $this->buildImportService(
            '<jobs>
                <job>
                    <vacancy_id>4712</vacancy_id>
                    <title><de>Sample job title</de></title>
                    <custom_select_4><de>IT</de></custom_select_4>
                    <custom_select_2><de>Vollzeit</de></custom_select_2>
                    <salary_mode>1</salary_mode>
                    <locations>
                        <location primary="true">
                            <technical_name>loc-2</technical_name>
                            <de>Example Company</de>
                            <street>Example Street 1</street>
                            <zipcode>12345</zipcode>
                            <city>Example City</city>
                        </location>
                    </locations>
                </job>
            </jobs>',
        );

        self::assertTrue($importService->import(new ImportConfiguration(2)));

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();
        $pathSegment = $queryBuilder
            ->select('path_segment')
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('vacancy_id', $queryBuilder->createNamedParameter('test_4712')))
            ->executeQuery()
            ->fetchOne();

        self::assertSame('sample-job-title', $pathSegment);
    }

    #[Test]
    public function importDeletesJobsNoLongerPresentInApiResponse(): void
    {
        $importService = $this->buildImportService('<jobs></jobs>');

        self::assertTrue($importService->import(new ImportConfiguration(2)));

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();
        $deletedFlag = $queryBuilder
            ->select('deleted')
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1)))
            ->executeQuery()
            ->fetchOne();

        self::assertSame(1, (int)$deletedFlag);
    }
}
