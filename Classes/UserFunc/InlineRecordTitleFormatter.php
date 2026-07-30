<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\UserFunc;

/**
 * Prevents the label_alt salary_table title from repeating in the IRRE
 * inline child header. formattedLabel_userFunc allows HTML output, but
 * that is not used here - plain text only.
 */
final class InlineRecordTitleFormatter
{
    public function formatSalaryGradeTitle(array &$parameters): void
    {
        $parameters['title'] = (string)($parameters['row']['title'] ?? '');
    }
}
