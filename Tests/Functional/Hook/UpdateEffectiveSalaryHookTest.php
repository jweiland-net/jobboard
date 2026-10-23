<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Hook;

use JWeiland\Jobboard\Hook\UpdateEffectiveSalaryHook;
use JWeiland\Jobboard\Service\EffectiveSalaryJobFinder;
use JWeiland\Jobboard\Service\EffectiveSalaryUpdaterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * The first tests save records through DataHandler like an editor (or the
 * import) does and check the effective salary stored in the database. The
 * last tests call the hook directly with a spy of the updater.
 */
final class UpdateEffectiveSalaryHookTest extends FunctionalTestCase
{
    private const NOW = 1767225600;

    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    private const TABLE_SALARY_GRADE = 'tx_jobboard_domain_model_salarygrade';

    private const TABLE_SALARY_STEP = 'tx_jobboard_domain_model_salarystep';

    private const TABLE_SALARY_TABLE = 'tx_jobboard_domain_model_salarytable';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-core',
        'typo3/cms-frontend',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private BackendUserAuthentication $backendUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EffectiveSalaryUpdater.csv');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . self::NOW)));

        // Localization needs a site with the target language
        $this->get(SiteWriter::class)->write('main', [
            'rootPageId' => 1,
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'navigationTitle' => 'English',
                    'base' => '/',
                    'locale' => 'en_US.UTF-8',
                    'flag' => 'global',
                ],
                [
                    'languageId' => 1,
                    'title' => 'German',
                    'navigationTitle' => 'Deutsch',
                    'base' => '/de/',
                    'locale' => 'de_DE.UTF-8',
                    'flag' => 'de',
                ],
            ],
        ]);

        $this->backendUser = $this->setUpBackendUser(1);
        // Needed by DataHandler for move commands
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($this->backendUser);
    }

    protected function tearDown(): void
    {
        unset(
            $this->backendUser,
            $GLOBALS['LANG'],
        );

        parent::tearDown();
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     */
    private function processDataMap(array $dataMap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        return $dataHandler;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $commandMap
     */
    private function processCommandMap(array $commandMap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $commandMap);
        $dataHandler->process_cmdmap();

        self::assertSame([], $dataHandler->errorLog);

        return $dataHandler;
    }

    /**
     * @return array{float, float, int}
     */
    private function fetchEffectiveSalary(int $jobUid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_JOB);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('effective_salary_min', 'effective_salary_max', 'effective_salary_valid_until')
            ->from(self::TABLE_JOB)
            ->where($queryBuilder->expr()->eq('uid', $jobUid))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row);

        return [
            (float)$row['effective_salary_min'],
            (float)$row['effective_salary_max'],
            (int)$row['effective_salary_valid_until'],
        ];
    }

    /**
     * Raw SQL, which does not trigger the hook
     *
     * @param array<string, mixed> $values
     */
    private function updateRecordWithoutDataHandler(string $table, int $uid, array $values): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable($table)->update($table, $values, ['uid' => $uid]);
    }

    private function setOutdatedEffectiveSalary(int $jobUid): void
    {
        $this->updateRecordWithoutDataHandler(
            self::TABLE_JOB,
            $jobUid,
            ['effective_salary_min' => 1.0, 'effective_salary_max' => 1.0],
        );
    }

    #[Test]
    public function hookIsRegisteredForDataMapAndCommandMap(): void
    {
        $options = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php'];

        self::assertSame(UpdateEffectiveSalaryHook::class, $options['processDatamapClass']['jobboard']);
        self::assertSame(UpdateEffectiveSalaryHook::class, $options['processCmdmapClass']['jobboard']);
    }

    #[Test]
    public function newJobWithFreeEntryGetsEffectiveSalary(): void
    {
        $dataHandler = $this->processDataMap([
            self::TABLE_JOB => [
                'NEW1' => [
                    'pid' => 2,
                    'title' => 'New job',
                    'employer' => 'Example Employer Ltd.',
                    'salary_mode' => 1,
                    'salary_min' => '2500.00',
                    'salary_max' => '3000.00',
                ],
            ],
        ]);

        self::assertSame([2500.0, 3000.0, 0], $this->fetchEffectiveSalary((int)$dataHandler->substNEWwithIDs['NEW1']));
    }

    #[Test]
    public function newJobWithSalaryGradesGetsEffectiveSalaryOfAllGrades(): void
    {
        $dataHandler = $this->processDataMap([
            self::TABLE_JOB => [
                'NEW1' => [
                    'pid' => 2,
                    'title' => 'New job',
                    'employer' => 'Example Employer Ltd.',
                    'salary_mode' => 0,
                    'salary_grades' => '1,2,3',
                ],
            ],
        ]);

        self::assertSame(
            [1000.0, 4000.0, 1767398400],
            $this->fetchEffectiveSalary((int)$dataHandler->substNEWwithIDs['NEW1']),
        );
    }

    #[Test]
    public function updatingJobRecalculatesItAndItsTranslation(): void
    {
        $this->processDataMap([self::TABLE_JOB => [7 => ['salary_min' => '2100.00']]]);

        self::assertSame([2100.0, 2100.0, 0], $this->fetchEffectiveSalary(7));
        self::assertSame([2100.0, 2100.0, 0], $this->fetchEffectiveSalary(8));
    }

    #[Test]
    public function savingJobWithoutChangeRepairsOutdatedEffectiveSalary(): void
    {
        $this->setOutdatedEffectiveSalary(4);

        $this->processDataMap([self::TABLE_JOB => [4 => ['title' => 'Up-to-date flat grade']]]);

        self::assertSame([4000.0, 4000.0, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function changingSalaryModeOfJobRecalculatesIt(): void
    {
        $this->processDataMap([self::TABLE_JOB => [4 => ['salary_mode' => 1, 'salary_min' => '1234.00']]]);

        self::assertSame([1234.0, 1234.0, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function changingFlatAmountOfSalaryGradeRecalculatesItsJobsAndTranslations(): void
    {
        $this->processDataMap([self::TABLE_SALARY_GRADE => [2 => ['flat_amount' => '4200.00']]]);

        self::assertSame([4200.0, 4200.0, 0], $this->fetchEffectiveSalary(4));
        self::assertSame([4200.0, 4200.0, 0], $this->fetchEffectiveSalary(5));
        self::assertSame([3000.0, 3500.0, 0], $this->fetchEffectiveSalary(2));
    }

    #[Test]
    public function hidingSalaryGradeRemovesEffectiveSalaryOfItsJobs(): void
    {
        $this->processDataMap([self::TABLE_SALARY_GRADE => [2 => ['hidden' => 1]]]);

        self::assertSame([0.0, 0.0, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function settingFutureStarttimeOfSalaryGradeRemovesEffectiveSalaryUntilThen(): void
    {
        $this->processDataMap([self::TABLE_SALARY_GRADE => [2 => ['starttime' => self::NOW + 3600]]]);

        self::assertSame([0.0, 0.0, self::NOW + 3600], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function deletingAndRestoringSalaryGradeRecalculatesItsJobs(): void
    {
        $this->processCommandMap([self::TABLE_SALARY_GRADE => [2 => ['delete' => 1]]]);

        self::assertSame([0.0, 0.0, 0], $this->fetchEffectiveSalary(4));

        $this->processCommandMap([self::TABLE_SALARY_GRADE => [2 => ['undelete' => 1]]]);

        self::assertSame([4000.0, 4000.0, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function changingAmountOfSalaryStepRecalculatesJobsOfItsGrade(): void
    {
        $this->processDataMap([self::TABLE_SALARY_STEP => [1 => ['amount' => '2900.00']]]);

        self::assertSame([2900.0, 3500.0, 0], $this->fetchEffectiveSalary(2));
        self::assertSame([2900.0, 3500.0, 0], $this->fetchEffectiveSalary(3));
    }

    #[Test]
    public function hidingSalaryStepRecalculatesJobsOfItsGrade(): void
    {
        $this->processDataMap([self::TABLE_SALARY_STEP => [2 => ['hidden' => 1]]]);

        self::assertSame([3000.0, 3000.0, 0], $this->fetchEffectiveSalary(2));
    }

    #[Test]
    public function deletingSalaryStepRecalculatesJobsOfItsGrade(): void
    {
        $this->processCommandMap([self::TABLE_SALARY_STEP => [2 => ['delete' => 1]]]);

        self::assertSame([3000.0, 3000.0, 0], $this->fetchEffectiveSalary(2));
    }

    #[Test]
    public function addingNewSalaryStepToSalaryGradeRecalculatesItsJobs(): void
    {
        $this->processDataMap([
            self::TABLE_SALARY_STEP => [
                'NEW1' => ['pid' => 2, 'step_label' => '3', 'amount' => '5000.00'],
            ],
            self::TABLE_SALARY_GRADE => [
                1 => ['salary_steps' => '1,2,NEW1'],
            ],
        ]);

        self::assertSame([3000.0, 5000.0, 0], $this->fetchEffectiveSalary(2));
    }

    #[Test]
    public function movingSalaryStepToAnotherSalaryGradeRecalculatesJobsOfBothGrades(): void
    {
        // Step 3 belongs to grade 4 (job 10) and is assigned to grade 1 (job 2)
        $this->processDataMap([self::TABLE_SALARY_GRADE => [1 => ['salary_steps' => '1,2,3']]]);

        self::assertSame([3000.0, 6000.0, 0], $this->fetchEffectiveSalary(2));
        self::assertSame([0.0, 0.0, 0], $this->fetchEffectiveSalary(10));
    }

    #[Test]
    public function deletingSalaryTableRecalculatesJobsOfItsSalaryGrades(): void
    {
        $this->processCommandMap([self::TABLE_SALARY_TABLE => [1 => ['delete' => 1]]]);

        self::assertSame([0.0, 0.0, 0], $this->fetchEffectiveSalary(2));
        self::assertSame([4000.0, 4000.0, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function movingJobRecalculatesIt(): void
    {
        $this->setOutdatedEffectiveSalary(4);

        $this->processCommandMap([self::TABLE_JOB => [4 => ['move' => 2]]]);

        self::assertSame([4000.0, 4000.0, 0], $this->fetchEffectiveSalary(4));
    }

    #[Test]
    public function copyingJobRecalculatesOriginalAndCopy(): void
    {
        // Job 1 is outdated in the fixture (no effective salary yet)
        $dataHandler = $this->processCommandMap([self::TABLE_JOB => [1 => ['copy' => 2]]]);
        $copiedUid = (int)($dataHandler->copyMappingArray_merged[self::TABLE_JOB][1] ?? 0);

        self::assertGreaterThan(0, $copiedUid);
        self::assertSame([2500.0, 3200.0, 0], $this->fetchEffectiveSalary(1));
        self::assertSame([2500.0, 3200.0, 0], $this->fetchEffectiveSalary($copiedUid));
    }

    #[Test]
    public function localizingJobRecalculatesItsTranslation(): void
    {
        $dataHandler = $this->processCommandMap([self::TABLE_JOB => [1 => ['localize' => 1]]]);
        $translatedUid = (int)($dataHandler->copyMappingArray_merged[self::TABLE_JOB][1] ?? 0);

        self::assertGreaterThan(0, $translatedUid);
        self::assertSame([2500.0, 3200.0, 0], $this->fetchEffectiveSalary(1));
        self::assertSame([2500.0, 3200.0, 0], $this->fetchEffectiveSalary($translatedUid));
    }

    #[Test]
    public function deletingJobDoesNotFail(): void
    {
        $this->processCommandMap([self::TABLE_JOB => [4 => ['delete' => 1]]]);

        self::assertSame([4000.0, 4000.0, 0], $this->fetchEffectiveSalary(4));
    }

    /**
     * @return EffectiveSalaryUpdaterInterface&object{calls: array<int, array<string, int[]>>}
     */
    private function createUpdaterSpy(): EffectiveSalaryUpdaterInterface
    {
        return new class () implements EffectiveSalaryUpdaterInterface {
            /**
             * @var array<int, array<string, int[]>>
             */
            public array $calls = [];

            public function updateByRecords(array $uidsByTable, ?BackendUserAuthentication $backendUser = null): int
            {
                $this->calls[] = $uidsByTable;

                return 0;
            }

            public function updateJobs(array $jobUids, ?BackendUserAuthentication $backendUser = null): int
            {
                return 0;
            }
        };
    }

    /**
     * @param array<string, array<int|string, mixed>> $dataMap
     */
    private function createDataHandlerWithDataMap(array $dataMap, int $workspace = 0): DataHandler
    {
        $this->backendUser->workspace = $workspace;
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->BE_USER = $this->backendUser;
        $dataHandler->datamap = $dataMap;

        return $dataHandler;
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function ownFieldsDataProvider(): array
    {
        return [
            'effective salary fields' => [[
                'effective_salary_min' => '1.00',
                'effective_salary_max' => '1.00',
                'effective_salary_valid_until' => 0,
            ]],
            'effective salary fields with l10n_state of a translation' => [[
                'effective_salary_min' => '1.00',
                'l10n_state' => '{}',
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    #[Test]
    #[DataProvider('ownFieldsDataProvider')]
    public function nestedWriteOfEffectiveSalaryIsIgnored(array $fieldArray): void
    {
        $updater = $this->createUpdaterSpy();
        $subject = new UpdateEffectiveSalaryHook($updater, $this->get(EffectiveSalaryJobFinder::class));

        $subject->processDatamap_afterAllOperations($this->createDataHandlerWithDataMap([self::TABLE_JOB => [4 => $fieldArray]]));

        self::assertSame([], $updater->calls);
    }

    #[Test]
    public function writeOfOtherJobFieldsNextToEffectiveSalaryIsHandled(): void
    {
        $updater = $this->createUpdaterSpy();
        $subject = new UpdateEffectiveSalaryHook($updater, $this->get(EffectiveSalaryJobFinder::class));

        $subject->processDatamap_afterAllOperations($this->createDataHandlerWithDataMap([
            self::TABLE_JOB => [4 => ['effective_salary_min' => '1.00', 'title' => 'Changed']],
        ]));

        self::assertSame([[self::TABLE_JOB => [4]]], $updater->calls);
    }

    #[Test]
    public function recordsOfOtherTablesAreIgnored(): void
    {
        $updater = $this->createUpdaterSpy();
        $subject = new UpdateEffectiveSalaryHook($updater, $this->get(EffectiveSalaryJobFinder::class));

        $subject->processDatamap_afterAllOperations($this->createDataHandlerWithDataMap([
            'pages' => [2 => ['title' => 'Changed']],
        ]));

        self::assertSame([], $updater->calls);
    }

    #[Test]
    public function changesInWorkspaceAreIgnored(): void
    {
        $updater = $this->createUpdaterSpy();
        $subject = new UpdateEffectiveSalaryHook($updater, $this->get(EffectiveSalaryJobFinder::class));

        $subject->processDatamap_afterAllOperations($this->createDataHandlerWithDataMap(
            [self::TABLE_JOB => [4 => ['title' => 'Changed']]],
            1,
        ));
        $dataHandler = $this->createDataHandlerWithDataMap([], 1);
        $dataHandler->cmdmap = [self::TABLE_SALARY_GRADE => [2 => ['delete' => 1]]];
        $subject->processCmdmap_afterFinish($dataHandler);

        self::assertSame([], $updater->calls);
    }

    #[Test]
    public function deletedJobIsNotRecalculatedButDeletedSalaryGradeIs(): void
    {
        $updater = $this->createUpdaterSpy();
        $subject = new UpdateEffectiveSalaryHook($updater, $this->get(EffectiveSalaryJobFinder::class));
        $dataHandler = $this->createDataHandlerWithDataMap([]);
        $dataHandler->cmdmap = [
            self::TABLE_JOB => [4 => ['delete' => 1]],
            self::TABLE_SALARY_GRADE => [2 => ['delete' => 1]],
        ];

        $subject->processCmdmap_afterFinish($dataHandler);

        self::assertSame([[self::TABLE_SALARY_GRADE => [2]]], $updater->calls);
    }

    #[Test]
    public function previousSalaryGradeOfReassignedStepIsRecalculated(): void
    {
        $updater = $this->createUpdaterSpy();
        $subject = new UpdateEffectiveSalaryHook($updater, $this->get(EffectiveSalaryJobFinder::class));
        $dataHandler = $this->createDataHandlerWithDataMap([
            self::TABLE_SALARY_GRADE => [1 => ['salary_steps' => '1,2,3']],
        ]);

        $subject->processDatamap_preProcessFieldArray(['salary_steps' => '1,2,3,NEW1'], self::TABLE_SALARY_GRADE, 1, $dataHandler);
        $subject->processDatamap_afterAllOperations($dataHandler);

        self::assertCount(1, $updater->calls);
        self::assertEqualsCanonicalizing([1, 4], array_unique($updater->calls[0][self::TABLE_SALARY_GRADE]));
    }
}
