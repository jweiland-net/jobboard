<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Database\Query\Restriction;

use JWeiland\Jobboard\Domain\Model\EffectiveSalary;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;

/**
 * QueryBuilder version of JobRepository::buildAndConstraintForOpenJobs() for
 * code without Extbase: the job needs a title, its application deadline must
 * not have passed and, for fixed-term positions, its employment must not have
 * ended already. Salary information (effective salary min or max > 0) is
 * only required with "$salaryRequired", see SalaryRequirement (site setting
 * "jobboard.salary.required"). Keep both in sync.
 *
 * "$today" is the timestamp of the start of today, so a job is listed
 * through the end of its deadline day, like "new \DateTime('today')" of
 * JobRepository. Only applied to the job table.
 */
final readonly class OpenJobRestriction implements QueryRestrictionInterface
{
    private const TABLE = 'tx_jobboard_domain_model_job';

    public function __construct(
        private int $today,
        private bool $salaryRequired = true,
    ) {}

    public static function forToday(bool $salaryRequired = true): self
    {
        return new self((new \DateTimeImmutable('today'))->getTimestamp(), $salaryRequired);
    }

    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        $constraints = [];
        foreach ($queriedTables as $tableAlias => $tableName) {
            if ($tableName === self::TABLE) {
                $constraints[] = $this->buildExpressionForTable((string)$tableAlias, $expressionBuilder);
            }
        }

        return $expressionBuilder->and(...$constraints);
    }

    private function buildExpressionForTable(string $tableAlias, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        $field = static fn(string $fieldName): string => $tableAlias . '.' . $fieldName;

        $constraints = [
            $expressionBuilder->neq($field('title'), $expressionBuilder->literal('')),
            $expressionBuilder->or(
                $expressionBuilder->eq($field('application_deadline'), 0),
                $expressionBuilder->gte($field('application_deadline'), $this->today),
            ),
            $expressionBuilder->or(
                $expressionBuilder->eq($field('ending_date'), 0),
                $expressionBuilder->gte($field('ending_date'), $this->today),
            ),
        ];

        if ($this->salaryRequired) {
            $constraints[] = $expressionBuilder->or(
                $expressionBuilder->gt($field(EffectiveSalary::FIELD_MIN), 0),
                $expressionBuilder->gt($field(EffectiveSalary::FIELD_MAX), 0),
            );
        }

        return $expressionBuilder->and(...$constraints);
    }
}
