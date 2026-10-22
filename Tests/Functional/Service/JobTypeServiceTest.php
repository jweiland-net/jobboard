<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\JobTypeService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class JobTypeServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private JobTypeService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobTypeService.csv');

        $this->subject = $this->get(JobTypeService::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    #[Test]
    public function getJobTypeUidWithExactTitleMatchReturnsUid(): void
    {
        self::assertSame(
            2,
            $this->subject->getJobTypeUid('Teilzeit'),
        );
    }

    #[Test]
    public function getJobTypeUidWithEmptyStringReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getJobTypeUid(''),
        );
    }

    #[Test]
    public function getJobTypeUidWithUnknownTitleReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getJobTypeUid('Does not exist'),
        );
    }
}
