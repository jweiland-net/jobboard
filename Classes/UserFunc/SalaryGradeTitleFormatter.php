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
 * Builds the SalaryGrade record title as "A7, Grundgehaltssaetze Baden-
 * Wuerttemberg" wherever it is used - except inside the parent
 * SalaryTable's own inline view, where the table name would just repeat
 * information the editor already sees. salary_table stays a plain
 * passthrough field so it never offers an "edit parent" link from within
 * the child; the title is resolved through it here instead.
 */
final class SalaryGradeTitleFormatter
{
    public function formatTitle(array &$parameters): void
    {
        $parameters['title'] = $this->buildTitle($parameters['row'], true);
    }

    public function formatInlineChildTitle(array &$parameters): void
    {
        $parameters['title'] = $this->buildTitle($parameters['row'], false);
    }

    private function buildTitle(array $row, bool $includeSalaryTable): string
    {
        $title = (string)($row['title'] ?? '');
        $salaryTableTitle = $includeSalaryTable
            ? $this->resolveSalaryTableTitle((int)($row['salary_table'] ?? 0))
            : '';

        return $salaryTableTitle === '' ? $title : $title . ', ' . $salaryTableTitle;
    }

    private function resolveSalaryTableTitle(int $uid): string
    {
        if ($uid <= 0) {
            return '';
        }

        $salaryTableRow = BackendUtility::getRecordWSOL('tx_jobboard_domain_model_salarytable', $uid);
        if ($salaryTableRow === null) {
            return '';
        }

        return BackendUtility::getRecordTitle('tx_jobboard_domain_model_salarytable', $salaryTableRow);
    }
}
