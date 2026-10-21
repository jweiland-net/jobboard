<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\PageTitle;

use JWeiland\Jobboard\PageTitle\JobPageTitleProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class JobPageTitleProviderTest extends UnitTestCase
{
    protected JobPageTitleProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new JobPageTitleProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    #[Test]
    public function getTitleWithoutPluginArgumentsReturnsEmptyString(): void
    {
        // An empty title makes the PageTitleProviderManager fall back to the
        // next provider, so pages without a job keep their regular title.
        $this->subject->setRequest(new ServerRequest('https://example.com/'));

        self::assertSame(
            '',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function getTitleWithoutJobArgumentReturnsEmptyString(): void
    {
        $this->subject->setRequest(
            (new ServerRequest('https://example.com/'))->withQueryParams([
                'tx_jobboard_jobboard' => [
                    'action' => 'list',
                ],
            ]),
        );

        self::assertSame(
            '',
            $this->subject->getTitle(),
        );
    }
}
