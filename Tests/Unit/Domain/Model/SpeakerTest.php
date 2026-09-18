<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model;

use OliverKlee\Oelib\Interfaces\MailRole;
use OliverKlee\Seminars\Domain\Model\Speaker;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Speaker
 */
final class SpeakerTest extends UnitTestCase
{
    private Speaker $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Speaker();
    }

    /**
     * @test
     */
    public function isAbstractEntity(): void
    {
        self::assertInstanceOf(AbstractEntity::class, $this->subject);
    }

    /**
     * @test
     */
    public function implementsMailRole(): void
    {
        self::assertInstanceOf(MailRole::class, $this->subject);
    }

    /**
     * @test
     */
    public function getNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getName());
    }

    /**
     * @test
     */
    public function setNameSetsName(): void
    {
        $value = 'Cactus Bill';
        $this->subject->setName($value);

        self::assertSame($value, $this->subject->getName());
    }

    /**
     * @test
     */
    public function getEmailAddressInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getEmailAddress());
    }

    /**
     * @test
     */
    public function setEmailAddressSetsEmailAddress(): void
    {
        $value = 'bill@example.com';
        $this->subject->setEmailAddress($value);

        self::assertSame($value, $this->subject->getEmailAddress());
    }

    /**
     * @test
     */
    public function getOrganizationInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getOrganization());
    }

    /**
     * @test
     */
    public function setOrganizationSetsOrganization(): void
    {
        $value = 'Speaking Inc.';
        $this->subject->setOrganization($value);

        self::assertSame($value, $this->subject->getOrganization());
    }

    /**
     * @test
     */
    public function getHomepageInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getHomepage());
    }

    /**
     * @test
     */
    public function setHomepageSetsHomepage(): void
    {
        $value = 'https://www.example.com/';
        $this->subject->setHomepage($value);

        self::assertSame($value, $this->subject->getHomepage());
    }

    /**
     * @test
     */
    public function getImageInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getImage());
    }

    /**
     * @test
     */
    public function setImageSetsImage(): void
    {
        $image = new FileReference();
        $this->subject->setImage($image);

        self::assertSame($image, $this->subject->getImage());
    }

    /**
     * @test
     */
    public function setImageIsNullable(): void
    {
        $this->subject->setImage(null);

        self::assertNull($this->subject->getImage());
    }

}
