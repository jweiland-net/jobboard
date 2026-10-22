<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
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
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ImportService.csv');

        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $GLOBALS['BE_USER']->backendCheckLogin();
    }

    private function buildApiModel(string $name = 'test'): ApiModelInterface
    {
        $apiModel = new class () implements ApiModelInterface {
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
                    'default' => '',
                    'prefix' => self::NAME,
                ],
                'salary_mode' => [
                    'apiPath' => 'salary_mode',
                    'isDate' => false,
                    'default' => 1,
                ],
            ];

            private int $storagePid = 0;

            private string $name = self::NAME;

            public function getName(): string
            {
                return $this->name;
            }

            public function withName(string $name): self
            {
                $clonedApiModel = clone $this;
                $clonedApiModel->name = $name;

                return $clonedApiModel;
            }
        };

        return $apiModel->withName($name);
    }

    private function buildImportService(
        string $xmlResponse,
        ?LoggerInterface $logger = null,
    ): ImportService {
        $xmlClient = self::createStub(XmlClient::class);
        $xmlClient->method('sendRequest')->willReturn(new Response(Utils::streamFor($xmlResponse)));

        return $this->buildImportServiceWithXmlClient($xmlClient, [$this->buildApiModel()], $logger);
    }

    /**
     * @param ApiModelInterface[] $apiModels
     */
    private function buildImportServiceWithXmlClient(
        XmlClient $xmlClient,
        array $apiModels,
        ?LoggerInterface $logger = null,
    ): ImportService {
        return new ImportService(
            $xmlClient,
            $this->get(JobService::class),
            $this->get(TtAddressService::class),
            $apiModels,
            $logger ?? self::createStub(LoggerInterface::class),
        );
    }

    private function buildJobXml(string $vacancyId, string $title, bool $withPrimaryLocation = true): string
    {
        return '<job>'
            . '<vacancy_id>' . $vacancyId . '</vacancy_id>'
            . '<title><de>' . $title . '</de></title>'
            . '<custom_select_4><de>IT</de></custom_select_4>'
            . '<custom_select_2><de>Vollzeit</de></custom_select_2>'
            . '<salary_mode>1</salary_mode>'
            . '<locations><location primary="' . ($withPrimaryLocation ? 'true' : 'false') . '">'
            . '<technical_name>location-1</technical_name>'
            . '<de>Sample Company Ltd.</de>'
            . '<street>Example Street 1</street>'
            . '<zipcode>12345</zipcode>'
            . '<city>Sample Town</city>'
            . '</location></locations>'
            . '</job>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getJobRecords(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'title', 'vacancy_id', 'deleted')
            ->from('tx_jobboard_domain_model_job')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function isJobDeleted(int $jobUid): bool
    {
        foreach ($this->getJobRecords() as $jobRecord) {
            if ((int)$jobRecord['uid'] === $jobUid) {
                return (bool)$jobRecord['deleted'];
            }
        }

        self::fail('Job with UID ' . $jobUid . ' does not exist');
    }

    #[Test]
    public function importCreatesNewJobAndAddressWithMappedFieldsAndRelation(): void
    {
        $importService = $this->buildImportService(
            '<jobs>
                <job>
                    <vacancy_id>4711</vacancy_id>
                    <title><de>Web Developer (m/f/d)</de></title>
                    <custom_select_4><de>IT</de></custom_select_4>
                    <custom_select_2><de>Vollzeit</de></custom_select_2>
                    <salary_mode>1</salary_mode>
                    <manager_name>Jane Doe</manager_name>
                    <manager_email>jobs@example.org</manager_email>
                    <locations>
                        <location primary="true">
                            <technical_name>loc-1</technical_name>
                            <de>Sample Company Ltd.</de>
                            <street>Example Street 1</street>
                            <zipcode>12345</zipcode>
                            <city>Sample Town</city>
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
        self::assertSame('Web Developer (m/f/d)', $job['title']);
        self::assertSame(1, (int)$job['job_areas']);
        self::assertSame(
            [1],
            array_map(
                intval(...),
                $connectionPool
                    ->getConnectionForTable('tx_jobboard_job_jobarea_mm')
                    ->select(['uid_foreign'], 'tx_jobboard_job_jobarea_mm', ['uid_local' => (int)$job['uid']])
                    ->fetchFirstColumn(),
            ),
        );
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
        self::assertSame('Sample Company Ltd.', $address['company']);
        self::assertSame('Example Street 1', $address['address']);
        self::assertSame('12345', $address['zip']);
        self::assertSame('Sample Town', $address['city']);
        self::assertSame('Jane Doe', $address['name']);
        self::assertSame('jobs@example.org', $address['email']);
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

    #[Test]
    public function importWithInvalidApiEndpointKeepsJobsAndLogsError(): void
    {
        $xmlClient = self::createStub(XmlClient::class);
        $xmlClient->method('sendRequest')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(self::stringContains('API endpoint is not a valid URL'));

        $importService = $this->buildImportServiceWithXmlClient($xmlClient, [$this->buildApiModel()], $logger);

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        self::assertFalse($this->isJobDeleted(1));
    }

    #[Test]
    public function importWithFailedRequestKeepsJobsAndLogsError(): void
    {
        $xmlClient = self::createStub(XmlClient::class);
        $xmlClient->method('sendRequest')->willThrowException(
            new ConnectException('Connection refused', new Request('GET', 'https://example.org/api/jobs.xml')),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(self::stringContains('Request failed: Connection refused'));

        $importService = $this->buildImportServiceWithXmlClient($xmlClient, [$this->buildApiModel()], $logger);

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        self::assertFalse($this->isJobDeleted(1));
    }

    #[Test]
    public function importWithInvalidXmlKeepsJobsAndLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(self::stringContains('Response is not valid XML'));

        $importService = $this->buildImportService('<jobs><job>', $logger);

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        self::assertFalse($this->isJobDeleted(1));
    }

    #[Test]
    public function importWithFailedApiModelStillDeletesNotDeliveredJobsOfOtherApiModels(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->insert('tx_jobboard_domain_model_job', [
                'uid' => 2,
                'pid' => 2,
                'title' => 'Job of failing source',
                'vacancy_id' => 'failing_1',
            ]);

        $failingApiModel = $this->buildApiModel('failing');
        $xmlClient = self::createStub(XmlClient::class);
        $xmlClient->method('sendRequest')->willReturnOnConsecutiveCalls(
            null,
            new Response(Utils::streamFor('<jobs></jobs>')),
        );

        $importService = $this->buildImportServiceWithXmlClient($xmlClient, [$failingApiModel, $this->buildApiModel()]);

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        self::assertFalse($this->isJobDeleted(2));
        self::assertTrue($this->isJobDeleted(1));
    }

    #[Test]
    public function importUpdatesExistingJobWithNonNumericVacancyIdInsteadOfCreatingANewOne(): void
    {
        $importService = $this->buildImportService(
            '<jobs>' . $this->buildJobXml('AB-0815', 'Web Developer (m/f/d)') . '</jobs>',
        );

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        $importService = $this->buildImportService(
            '<jobs>' . $this->buildJobXml('AB-0815', 'Senior Web Developer (m/f/d)') . '</jobs>',
        );
        self::assertTrue($importService->import(new ImportConfiguration(2)));

        $jobRecords = array_values(array_filter(
            $this->getJobRecords(),
            static fn(array $jobRecord): bool => $jobRecord['vacancy_id'] === 'test_AB-0815',
        ));
        self::assertCount(1, $jobRecords);
        self::assertSame('Senior Web Developer (m/f/d)', $jobRecords[0]['title']);
        self::assertSame(0, (int)$jobRecords[0]['deleted']);
    }

    #[Test]
    public function importKeepsExistingJobWhichCouldNotBeSaved(): void
    {
        // A job without primary location can not be saved, but it was still
        // delivered by the source and must not be deleted.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(self::stringContains('vacancy ID "9999"'));

        $importService = $this->buildImportService(
            '<jobs>' . $this->buildJobXml('9999', 'Old job to be deleted', false) . '</jobs>',
            $logger,
        );

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        self::assertFalse($this->isJobDeleted(1));
    }

    #[Test]
    public function importSkipsJobWithoutVacancyId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(self::stringContains('Job without vacancy ID'));

        $importService = $this->buildImportService(
            '<jobs>' . $this->buildJobXml('', 'Web Developer (m/f/d)') . '</jobs>',
            $logger,
        );

        self::assertTrue($importService->import(new ImportConfiguration(2)));
        self::assertCount(1, $this->getJobRecords());
    }

    #[Test]
    public function importLogsDataHandlerErrorWithVacancyId(): void
    {
        // Jobs are not allowed on standard pages like page 1, so DataHandler
        // refuses to insert the job
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('error')
            ->with(self::stringContains('Internal DataHandler error with vacancy ID "AB-0815"'));

        $importService = $this->buildImportService(
            '<jobs>' . $this->buildJobXml('AB-0815', 'Web Developer (m/f/d)') . '</jobs>',
            $logger,
        );

        self::assertTrue($importService->import(new ImportConfiguration(1)));
    }
}
