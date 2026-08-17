<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\ContractType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class ContractTypeTest extends UnitTestCase
{
    protected ContractType $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ContractType();
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
        self::assertSame(
            '',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function setTitleSetsTitle(): void
    {
        $this->subject->setTitle('Full-time');

        self::assertSame(
            'Full-time',
            $this->subject->getTitle(),
        );
    }
}
