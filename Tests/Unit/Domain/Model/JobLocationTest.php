<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\JobLink;
use JWeiland\Jobboard\Domain\Model\JobLocation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
final class JobLocationTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $addressRow
     */
    private function createJobLocation(array $addressRow, int $numberOfJobs = 1): JobLocation
    {
        $jobLinks = [];
        for ($uid = 1; $uid <= $numberOfJobs; $uid++) {
            $jobLinks[] = new JobLink($uid, 'Job ' . $uid, '/job-' . $uid, false);
        }

        $jobLocation = JobLocation::fromAddressRow($addressRow, $jobLinks);
        self::assertInstanceOf(JobLocation::class, $jobLocation);

        return $jobLocation;
    }

    #[Test]
    public function fromAddressRowWithoutJobsReturnsNull(): void
    {
        self::assertNull(JobLocation::fromAddressRow(['company' => 'Example Employer Ltd.'], []));
    }

    #[Test]
    public function getNameReturnsCompany(): void
    {
        $jobLocation = $this->createJobLocation([
            'company' => ' Example Employer Ltd. ',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        self::assertSame('Example Employer Ltd.', $jobLocation->getName());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function nameWithoutCompanyDataProvider(): array
    {
        return [
            'first and last name' => [['company' => ' ', 'first_name' => 'Jane', 'last_name' => 'Doe'], 'Jane Doe'],
            'first name only' => [['first_name' => 'Jane', 'last_name' => ''], 'Jane'],
            'last name only' => [['first_name' => ' ', 'last_name' => 'Doe'], 'Doe'],
            'no name at all' => [['city' => 'Example City'], ''],
        ];
    }

    /**
     * @param array<string, mixed> $addressRow
     */
    #[Test]
    #[DataProvider('nameWithoutCompanyDataProvider')]
    public function getNameWithoutCompanyReturnsFullName(array $addressRow, string $expectedName): void
    {
        self::assertSame($expectedName, $this->createJobLocation($addressRow)->getName());
    }

    #[Test]
    public function getStreetReturnsTrimmedAddressColumn(): void
    {
        self::assertSame('Example Street 1', $this->createJobLocation(['address' => ' Example Street 1 '])->getStreet());
    }

    #[Test]
    public function getStreetWithoutAddressColumnReturnsEmptyString(): void
    {
        self::assertSame('', $this->createJobLocation(['company' => 'Example Employer Ltd.'])->getStreet());
    }

    #[Test]
    public function getZipAndGetCityReturnTrimmedValues(): void
    {
        $jobLocation = $this->createJobLocation(['zip' => ' 12345 ', 'city' => ' Example City ']);

        self::assertSame('12345', $jobLocation->getZip());
        self::assertSame('Example City', $jobLocation->getCity());
    }

    #[Test]
    public function zipCityAndStreetAreEmptyWithoutColumns(): void
    {
        $jobLocation = $this->createJobLocation([]);

        self::assertSame('', $jobLocation->getStreet());
        self::assertSame('', $jobLocation->getZip());
        self::assertSame('', $jobLocation->getCity());
    }

    #[Test]
    public function getJobLinksReturnsListOfJobLinksInGivenOrder(): void
    {
        $first = new JobLink(7, 'First', '/first', false);
        $second = new JobLink(3, 'Second', 'https://example.org/', true);

        $jobLocation = JobLocation::fromAddressRow([], ['a' => $first, 'b' => $second]);

        self::assertSame([$first, $second], $jobLocation?->getJobLinks());
    }

    #[Test]
    public function getNumberOfJobsReturnsNumberOfJobLinks(): void
    {
        self::assertSame(3, $this->createJobLocation([], 3)->getNumberOfJobs());
    }
}
