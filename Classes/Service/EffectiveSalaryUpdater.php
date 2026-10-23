<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes the effective salary through DataHandler instead of raw SQL: this
 * way, TYPO3 clears the page caches of changed jobs (cache tags and
 * "TCEMAIN.clearCacheCmd" of the storage folder) and other extensions can
 * react on the changes via the usual DataHandler hooks.
 *
 * Only jobs with changed values are written, so a recalculation without any
 * change neither clears caches nor recurses through the DataHandler hook.
 * The default language is written only, DataHandler copies the values into
 * the translations (l10n_mode "exclude").
 */
final readonly class EffectiveSalaryUpdater implements EffectiveSalaryUpdaterInterface
{
    /**
     * Number of jobs calculated and written within one DataHandler run
     */
    private const CHUNK_SIZE = 100;

    public function __construct(
        private EffectiveSalaryCalculatorInterface $effectiveSalaryCalculator,
        private EffectiveSalaryJobFinder $effectiveSalaryJobFinder,
        private Context $context,
        private LoggerInterface $logger,
    ) {}

    public function updateByRecords(array $uidsByTable, ?BackendUserAuthentication $backendUser = null): int
    {
        $salaryGradeUids = [
            ...($uidsByTable[self::TABLE_SALARY_GRADE] ?? []),
            ...$this->effectiveSalaryJobFinder->findSalaryGradeUidsBySalarySteps(
                $uidsByTable[self::TABLE_SALARY_STEP] ?? [],
            ),
            ...$this->effectiveSalaryJobFinder->findSalaryGradeUidsBySalaryTables(
                $uidsByTable[self::TABLE_SALARY_TABLE] ?? [],
            ),
        ];

        $jobUids = [
            ...($uidsByTable[self::TABLE_JOB] ?? []),
            ...$this->effectiveSalaryJobFinder->findJobUidsBySalaryGrades(array_values(array_unique($salaryGradeUids))),
        ];

        return $this->updateJobs($jobUids, $backendUser);
    }

    public function updateJobs(array $jobUids, ?BackendUserAuthentication $backendUser = null): int
    {
        $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp');
        $defaultLanguageJobUids = $this->effectiveSalaryJobFinder->findDefaultLanguageJobUids(
            array_values(array_unique($jobUids)),
        );

        $numberOfChangedJobs = 0;
        foreach (array_chunk($defaultLanguageJobUids, self::CHUNK_SIZE) as $jobUidsOfChunk) {
            $numberOfChangedJobs += $this->writeEffectiveSalaries(
                $this->getChangedEffectiveSalaries($jobUidsOfChunk, $now),
                $backendUser,
            );
        }

        return $numberOfChangedJobs;
    }

    /**
     * A job is changed, if the stored values of the job or of any of its
     * translations differ from the calculated values.
     *
     * @param int[] $jobUids
     * @return array<int, EffectiveSalary>
     */
    private function getChangedEffectiveSalaries(array $jobUids, int $now): array
    {
        $storedEffectiveSalaries = $this->effectiveSalaryJobFinder->findStoredEffectiveSalaries($jobUids);

        $changedEffectiveSalaries = [];
        foreach ($this->effectiveSalaryCalculator->calculateForJobs($jobUids, $now) as $jobUid => $effectiveSalary) {
            foreach ($storedEffectiveSalaries[$jobUid] ?? [] as $storedEffectiveSalary) {
                if (!$storedEffectiveSalary->equals($effectiveSalary)) {
                    $changedEffectiveSalaries[$jobUid] = $effectiveSalary;
                    break;
                }
            }
        }

        return $changedEffectiveSalaries;
    }

    /**
     * The effective salary is derived data, not an edit of the current
     * user: an editor changing a salary grade must update all jobs using it,
     * even without write access to these jobs, their pages or languages.
     * Therefore, record/page/language access checks are bypassed for this
     * DataHandler run, which only ever contains the effective salary fields.
     * No sys_log entries are written for derived values. sys_history is still
     * written by DataHandler, so editors can see why the effective salary of
     * a job has changed.
     *
     * @param array<int, EffectiveSalary> $effectiveSalaries
     */
    private function writeEffectiveSalaries(array $effectiveSalaries, ?BackendUserAuthentication $backendUser): int
    {
        if ($effectiveSalaries === []) {
            return 0;
        }

        $dataMap = [
            self::TABLE_JOB => array_map(
                static fn(EffectiveSalary $effectiveSalary): array => $effectiveSalary->toFieldArray(),
                $effectiveSalaries,
            ),
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->enableLogging = false;
        $dataHandler->start($dataMap, [], $backendUser);
        $dataHandler->bypassAccessCheckForRecords = true;
        $dataHandler->admin = true;
        $dataHandler->process_datamap();

        foreach ($dataHandler->errorLog as $errorLog) {
            $this->logger->error('Effective salary could not be updated: ' . $errorLog);
        }

        return count($effectiveSalaries);
    }
}
