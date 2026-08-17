<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Domain\Model;

use JWeiland\Jobboard\Domain\Model\Benefit;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class BenefitTest extends UnitTestCase
{
    protected Benefit $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Benefit();
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
        $this->subject->setTitle('Company car');

        self::assertSame(
            'Company car',
            $this->subject->getTitle(),
        );
    }

    #[Test]
    public function getColorInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getColor(),
        );
    }

    #[Test]
    public function setColorSetsColor(): void
    {
        $this->subject->setColor('#A8D8EA');

        self::assertSame(
            '#A8D8EA',
            $this->subject->getColor(),
        );
    }

    #[Test]
    public function getDescriptionInitiallyReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getDescription(),
        );
    }

    #[Test]
    public function setDescriptionSetsDescription(): void
    {
        $this->subject->setDescription('Free parking on the company premises.');

        self::assertSame(
            'Free parking on the company premises.',
            $this->subject->getDescription(),
        );
    }

    #[Test]
    public function getImageInitiallyReturnsObjectStorage(): void
    {
        self::assertEquals(
            new ObjectStorage(),
            $this->subject->getImage(),
        );
    }

    #[Test]
    public function setImageSetsImage(): void
    {
        $object = new FileReference();

        $objectStorage = new ObjectStorage();
        $objectStorage->attach($object);

        $this->subject->setImage($objectStorage);

        self::assertSame(
            $objectStorage,
            $this->subject->getImage(),
        );
    }

    #[Test]
    public function addImageAddsImage(): void
    {
        $instance = new FileReference();
        $this->subject->addImage($instance);

        self::assertTrue(
            $this->subject->getImage()->contains($instance),
        );
    }

    #[Test]
    public function removeImageRemovesImage(): void
    {
        $instance = new FileReference();
        $this->subject->addImage($instance);
        $this->subject->removeImage($instance);

        self::assertFalse(
            $this->subject->getImage()->contains($instance),
        );
    }
}
