<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

/**
 * A job location (tt_address) with its open jobs, as shown in the info
 * window of its marker on the map. Never contains an empty list of jobs.
 */
final readonly class JobLocation
{
    /**
     * @param JobLink[] $jobLinks
     */
    private function __construct(
        private string $name,
        private string $street,
        private string $zip,
        private string $city,
        private array $jobLinks,
    ) {}

    /**
     * Name is the company, otherwise first and last name of the address, like
     * the title of the PoiCollection (see TCA override of tt_address).
     *
     * @param array<string, mixed> $addressRow
     * @param JobLink[] $jobLinks
     */
    public static function fromAddressRow(array $addressRow, array $jobLinks): ?self
    {
        if ($jobLinks === []) {
            return null;
        }

        $name = trim((string)($addressRow['company'] ?? ''));

        return new self(
            $name !== '' ? $name : self::buildFullName($addressRow),
            trim((string)($addressRow['address'] ?? '')),
            trim((string)($addressRow['zip'] ?? '')),
            trim((string)($addressRow['city'] ?? '')),
            array_values($jobLinks),
        );
    }

    /**
     * @param array<string, mixed> $addressRow
     */
    private static function buildFullName(array $addressRow): string
    {
        return implode(' ', array_filter([
            trim((string)($addressRow['first_name'] ?? '')),
            trim((string)($addressRow['last_name'] ?? '')),
        ], static fn(string $part): bool => $part !== ''));
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Street and house number (column "address" of tt_address)
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @return JobLink[]
     */
    public function getJobLinks(): array
    {
        return $this->jobLinks;
    }

    public function getNumberOfJobs(): int
    {
        return count($this->jobLinks);
    }
}
