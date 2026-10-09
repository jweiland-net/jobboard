<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\JobAreaService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobAreaServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    private JobAreaService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobAreaService.csv');

        $this->subject = $this->get(JobAreaService::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    #[Test]
    public function getJobAreaUidWithExactTitleMatchReturnsUid(): void
    {
        self::assertSame(
            2,
            $this->subject->getJobAreaUid('Handwerk'),
        );
    }

    #[Test]
    public function getJobAreaUidWithEmptyStringReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getJobAreaUid(''),
        );
    }

    #[Test]
    public function getJobAreaUidWithUnknownTitleReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getJobAreaUid('Does not exist'),
        );
    }

    #[Test]
    public function getJobAreaUidListWithSingleTitleReturnsItsUid(): void
    {
        self::assertSame(
            '2',
            $this->subject->getJobAreaUidList('Handwerk'),
        );
    }

    #[Test]
    public function getJobAreaUidListWithLineSeparatedTitlesReturnsCommaSeparatedUids(): void
    {
        self::assertSame(
            '2,1',
            $this->subject->getJobAreaUidList("Handwerk\nIT\nHandwerk"),
        );
    }

    #[Test]
    public function getJobAreaUidListSkipsUnknownTitles(): void
    {
        self::assertSame(
            '1',
            $this->subject->getJobAreaUidList("Does not exist\nIT"),
        );
    }

    #[Test]
    public function getJobAreaUidListWithEmptyStringReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getJobAreaUidList(''),
        );
    }
}
