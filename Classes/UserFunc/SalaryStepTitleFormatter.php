<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\UserFunc;

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Renders the SalaryStep record title as "A13, Table X - Step 3 - 3.421,84"
 * instead of the raw "3, 3421.84" default label_alt concatenation -
 * prefixing it with the parent SalaryGrade title, prefixing the step_label
 * with its own field label, and formatting the amount for the current
 * backend user's locale.
 *
 * Inside the parent SalaryGrade's own inline view the grade is omitted,
 * as it would just repeat information the editor already sees.
 */
final class SalaryStepTitleFormatter
{
    private const STEP_LABEL_LLL = 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarystep.step_label';

    private const SALARY_GRADE_TABLE = 'tx_jobboard_domain_model_salarygrade';

    public function formatTitle(array &$parameters): void
    {
        $parameters['title'] = $this->buildTitle($parameters['row'], true);
    }

    public function formatInlineChildTitle(array &$parameters): void
    {
        $parameters['title'] = $this->buildTitle($parameters['row'], false);
    }

    private function buildTitle(array $row, bool $includeSalaryGrade): string
    {
        $parts = [];
        if ($includeSalaryGrade) {
            $parts[] = $this->resolveSalaryGradeTitle($this->extractUid($row['salary_grade'] ?? 0));
        }
        if (($row['step_label'] ?? '') !== '') {
            $parts[] = trim($this->getStepLabelPrefix() . ' ' . $row['step_label']);
        }
        $parts[] = $this->formatAmount((float)($row['amount'] ?? 0.0));

        return implode(' - ', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }

    /**
     * salary_grade is the inline foreign_field pointer. Depending on the
     * calling context it may arrive as int, numeric string, CSV/"table_uid"
     * string or (FormEngine) array of uids/rows - reduce it to a plain uid.
     */
    private function extractUid(mixed $value): int
    {
        if (is_array($value)) {
            $value = reset($value);
            $value = is_array($value) ? ($value['uid'] ?? 0) : $value;
        }
        if (is_int($value)) {
            return $value;
        }
        $firstValue = explode(',', (string)$value)[0];

        return preg_match('/(\d+)$/', trim($firstValue), $matches) === 1 ? (int)$matches[1] : 0;
    }

    /**
     * SalaryGrade's own label_userFunc only resolves its SalaryTable, which
     * has a plain "title" label, so this can never loop back to SalaryStep.
     */
    private function resolveSalaryGradeTitle(int $uid): string
    {
        if ($uid <= 0) {
            return '';
        }

        $salaryGradeRow = BackendUtility::getRecordWSOL(self::SALARY_GRADE_TABLE, $uid);
        if ($salaryGradeRow === null) {
            return '';
        }

        return BackendUtility::getRecordTitle(self::SALARY_GRADE_TABLE, $salaryGradeRow);
    }

    private function getStepLabelPrefix(): string
    {
        return $GLOBALS['LANG']?->sL(self::STEP_LABEL_LLL) ?? 'Step';
    }

    private function formatAmount(float $amount): string
    {
        $locale = $GLOBALS['LANG']?->getLocale()?->getName() ?? 'en';
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);

        return (string)$formatter->format($amount);
    }
}
