<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Empties values of the XML API, which DataHandler would reject with an
 * error (invalid email addresses and URLs). A job is still imported without
 * such a value, instead of failing completely.
 */
readonly class ImportRecordValidator
{
    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function emptyInvalidValues(string $table, array $record, string $vacancyId): array
    {
        $schema = $this->tcaSchemaFactory->get($table);
        foreach ($record as $fieldName => $value) {
            if (!is_string($value)
                || !$schema->hasField($fieldName)
                || $this->isValidValue($schema->getField($fieldName)->getConfiguration(), $value)
            ) {
                continue;
            }

            $this->logger->warning(sprintf(
                'Invalid value "%s" of field "%s" in table "%s" of job with vacancy ID "%s" removed',
                $value,
                $fieldName,
                $table,
                $vacancyId,
            ));
            $record[$fieldName] = '';
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $fieldConfiguration
     */
    private function isValidValue(array $fieldConfiguration, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        return match ($fieldConfiguration['type'] ?? '') {
            'email' => GeneralUtility::validEmail($value),
            'link' => ($fieldConfiguration['allowedTypes'] ?? null) !== ['url'] || GeneralUtility::isValidUrl($value),
            default => true,
        };
    }
}
