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
use TYPO3\CMS\Core\SingletonInterface;
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
    public function getTitleInitiallyReturnsEmptyString(): void
    {
        // An empty title makes the PageTitleProviderManager fall back to the
        // next provider, so pages without a job keep their regular title.
        self::assertSame(
            '',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function setTitleSetsTitle(): void
    {
        $this->subject->setTitle('Head of planning office');

        self::assertSame(
            'Head of planning office',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function setTitleTrimsTitle(): void
    {
        $this->subject->setTitle('  Head of planning office ');

        self::assertSame(
            'Head of planning office',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function providerIsSingleton(): void
    {
        // Required, as the instance injected into JobboardController must be the
        // same instance the PageTitleProviderManager retrieves from the container.
        self::assertInstanceOf(
            SingletonInterface::class,
            $this->subject,
        );
    }
}
