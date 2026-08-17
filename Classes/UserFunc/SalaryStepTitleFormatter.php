<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\UserFunc;

/**
 * Renders the SalaryStep record title as "Step 3 - 3.421,84" instead of
 * the raw "3, 3421.84" default label_alt concatenation - prefixing the
 * step_label with its own field label, and formatting the amount for the
 * current backend user's locale.
 */
final class SalaryStepTitleFormatter
{
    private const STEP_LABEL_LLL = 'LLL:EXT:jobboard/Resources/Private/Language/locallang_db.xlf:tx_jobboard_domain_model_salarystep.step_label';

    public function formatTitle(array &$parameters): void
    {
        $row = $parameters['row'];
        $parts = [];
        if (($row['step_label'] ?? '') !== '') {
            $parts[] = trim($this->getStepLabelPrefix() . ' ' . $row['step_label']);
        }
        $parts[] = $this->formatAmount((float)($row['amount'] ?? 0.0));
        $parameters['title'] = implode(' - ', $parts);
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
