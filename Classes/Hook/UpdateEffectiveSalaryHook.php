<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Hook;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use JWeiland\Jobboard\Service\EffectiveSalaryJobFinder;
use JWeiland\Jobboard\Service\EffectiveSalaryUpdaterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Recalculates the effective salary of jobs after jobs, salary grades,
 * salary steps or salary tables were created, updated, hidden, deleted,
 * restored, moved, copied or localized - in the backend and by the import.
 *
 * Runs after all operations of a DataHandler run, so NEW ids are resolved
 * and all MM relations are written. The values are written by a nested
 * DataHandler run (see EffectiveSalaryUpdater). Its data map only contains
 * the effective salary fields and is ignored here, which avoids recursion.
 * Its cache clearing is processed by the outermost DataHandler instance.
 *
 * A salary step assigned to another salary grade also changes the jobs of
 * its previous grade. "salary_grade" is no TCA column of the step (only the
 * foreign_field of the inline relation "salary_steps" of the grade), so a
 * step can only change its grade through "salary_steps" of the new grade.
 * The previous grades of these steps are captured before the write and
 * recalculated with the other records of the same run. "salary_table" of a
 * salary grade does not affect the effective salary.
 */
#[Autoconfigure(public: true)]
final readonly class UpdateEffectiveSalaryHook
{
    private const TABLES = [
        EffectiveSalaryUpdaterInterface::TABLE_JOB,
        EffectiveSalaryUpdaterInterface::TABLE_SALARY_GRADE,
        EffectiveSalaryUpdaterInterface::TABLE_SALARY_STEP,
        EffectiveSalaryUpdaterInterface::TABLE_SALARY_TABLE,
    ];

    /**
     * Fields, which may be in the data map of the nested DataHandler run.
     * "l10n_state" is added to translations by DataHandler.
     */
    private const OWN_FIELDS = [
        ...EffectiveSalary::FIELDS,
        'l10n_state',
    ];

    /**
     * Previous salary grade UIDs of reassigned salary steps per DataHandler
     * instance (nested runs use their own instance)
     *
     * @var \WeakMap<DataHandler, int[]>
     */
    private \WeakMap $previousSalaryGradeUids;

    public function __construct(
        private EffectiveSalaryUpdaterInterface $effectiveSalaryUpdater,
        private EffectiveSalaryJobFinder $effectiveSalaryJobFinder,
    ) {
        $this->previousSalaryGradeUids = new \WeakMap();
    }

    /**
     * Captures the current salary grades of the existing steps submitted in
     * "salary_steps" of a salary grade, before DataHandler writes the new
     * grade into their "salary_grade". NEW steps have no previous grade.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array $fieldArray,
        string $table,
        int|string $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== EffectiveSalaryUpdaterInterface::TABLE_SALARY_GRADE
            || !isset($fieldArray['salary_steps'])
        ) {
            return;
        }

        $salaryStepUids = array_filter(
            GeneralUtility::trimExplode(',', implode(',', (array)$fieldArray['salary_steps']), true),
            MathUtility::canBeInterpretedAsInteger(...),
        );

        $this->previousSalaryGradeUids[$dataHandler] = [
            ...($this->previousSalaryGradeUids[$dataHandler] ?? []),
            ...$this->effectiveSalaryJobFinder->findSalaryGradeUidsBySalarySteps(array_map(intval(...), $salaryStepUids)),
        ];
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $previousSalaryGradeUids = $this->previousSalaryGradeUids[$dataHandler] ?? [];
        unset($this->previousSalaryGradeUids[$dataHandler]);
        if (!$this->isLiveWorkspace($dataHandler)) {
            return;
        }

        $uidsByTable = $previousSalaryGradeUids === []
            ? []
            : [EffectiveSalaryUpdaterInterface::TABLE_SALARY_GRADE => $previousSalaryGradeUids];
        foreach (self::TABLES as $table) {
            foreach ($dataHandler->datamap[$table] ?? [] as $id => $fieldArray) {
                $uid = $this->resolveUid($dataHandler, $id);
                if ($uid > 0 && !$this->containsOwnFieldsOnly($table, $fieldArray)) {
                    $uidsByTable[$table][] = $uid;
                }
            }
        }

        $this->update($dataHandler, $uidsByTable);
    }

    /**
     * Copies and localizations get new UIDs, which are recalculated as well.
     * A salary grade or step is recalculated for every command, e.g. delete,
     * undelete or move. A deleted job does not need any recalculation.
     */
    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        if (!$this->isLiveWorkspace($dataHandler)) {
            return;
        }

        $uidsByTable = [];
        foreach (self::TABLES as $table) {
            foreach ($dataHandler->cmdmap[$table] ?? [] as $uid => $commands) {
                if ($table === EffectiveSalaryUpdaterInterface::TABLE_JOB && isset($commands['delete'])) {
                    continue;
                }
                $uidsByTable[$table][] = (int)$uid;
                $copiedUid = (int)($dataHandler->copyMappingArray_merged[$table][$uid] ?? 0);
                if ($copiedUid > 0) {
                    $uidsByTable[$table][] = $copiedUid;
                }
            }
        }

        $this->update($dataHandler, $uidsByTable);
    }

    /**
     * @param array<string, int[]> $uidsByTable
     */
    private function update(DataHandler $dataHandler, array $uidsByTable): void
    {
        if ($uidsByTable !== []) {
            $this->effectiveSalaryUpdater->updateByRecords($uidsByTable, $dataHandler->BE_USER);
        }
    }

    /**
     * The effective salary is calculated for live records only. Publishing
     * a workspace version runs a command, which is handled again in live.
     */
    private function isLiveWorkspace(DataHandler $dataHandler): bool
    {
        return (int)($dataHandler->BE_USER->workspace ?? 0) === 0;
    }

    private function resolveUid(DataHandler $dataHandler, int|string $id): int
    {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            return (int)$id;
        }

        return (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
    }

    private function containsOwnFieldsOnly(string $table, mixed $fieldArray): bool
    {
        return $table === EffectiveSalaryUpdaterInterface::TABLE_JOB
            && is_array($fieldArray)
            && $fieldArray !== []
            && array_diff(array_keys($fieldArray), self::OWN_FIELDS) === [];
    }
}
