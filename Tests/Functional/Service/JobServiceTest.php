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
use JWeiland\Jobboard\Configuration\ImportConfiguration;
use JWeiland\Jobboard\Service\JobService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    private JobService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobService.csv');

        $this->subject = $this->get(JobService::class);
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
                'start_date' => [
                    'apiPath' => 'valid_from',
                    'isDate' => true,
                    'default' => '',
                ],
                'reference_number' => [
                    'apiPath' => 'vacancy_id',
                    'isDate' => false,
                    'default' => '000000',
                ],
                'vacancy_id' => [
                    'apiPath' => 'vacancy_id',
                    'isDate' => false,
                    'default' => 0,
                    'prefix' => self::NAME,
                ],
                'missing_field' => [
                    'apiPath' => 'does/not/exist',
                    'isDate' => false,
                    'default' => 'fallback',
                ],
            ];

            private int $storagePid = 2;
        };
    }

    private function buildJobModel(): JobModel
    {
        return new JobModel(new \SimpleXMLElement(
            '<job>
                <vacancy_id>4711</vacancy_id>
                <title><de>Fachkraft (m/w/d)</de></title>
                <custom_select_4><de>IT</de></custom_select_4>
                <custom_select_2><de>Vollzeit</de></custom_select_2>
                <valid_from>2024-01-09 00:00:00 +0100</valid_from>
            </job>',
        ));
    }

    #[Test]
    public function getJobUidWithExistingVacancyIdReturnsItsUid(): void
    {
        self::assertSame(
            1,
            $this->subject->getJobUid(4711, $this->buildApiModel()),
        );
    }

    #[Test]
    public function getJobUidWithUnknownVacancyIdReturnsNewIdentifier(): void
    {
        $jobUid = $this->subject->getJobUid(9999, $this->buildApiModel());

        self::assertIsString($jobUid);
        self::assertStringStartsWith('NEW', $jobUid);
    }

    #[Test]
    public function getPreparedJobByImportedJobMapsFieldsAndResolvesJobAreaAndJobType(): void
    {
        $preparedJob = $this->subject->getPreparedJobByImportedJob($this->buildJobModel(), $this->buildApiModel());

        self::assertSame(2, $preparedJob['pid']);
        self::assertTrue($preparedJob['is_import']);
        self::assertSame('1', $preparedJob['job_areas']);
        self::assertSame(1, $preparedJob['job_type']);
        self::assertSame('Fachkraft (m/w/d)', $preparedJob['title']);
        self::assertSame('test_4711', $preparedJob['vacancy_id']);
        self::assertSame('4711', $preparedJob['reference_number']);
        // JobService reads getValueByPath() without passing the ApiMapping's own
        // "default", so a missing field resolves to AbstractModel's own default ('')
        // instead of the mapping's configured one - the try/catch never actually
        // triggers for a missing path, since getValueByPath() never throws.
        self::assertSame('', $preparedJob['missing_field']);
        self::assertIsInt($preparedJob['start_date']);
        self::assertGreaterThan(0, $preparedJob['start_date']);
    }

    #[Test]
    public function getInitialImportStatusOfAlreadyImportedJobsOnlyReturnsJobsWithAVacancyId(): void
    {
        self::assertSame(
            [1 => false],
            $this->subject->getInitialImportStatusOfAlreadyImportedJobs(new ImportConfiguration(2)),
        );
    }
}
