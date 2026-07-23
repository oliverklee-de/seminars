<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Event\EventTopicInterface;
use OliverKlee\Seminars\Domain\Model\Event\NullEventTopic;
use OliverKlee\Seminars\Domain\Model\Price;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Event\NullEventTopic
 */
final class NullEventTopicTest extends UnitTestCase
{
    private NullEventTopic $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new NullEventTopic();
    }

    /**
     * @test
     */
    public function isDomainObject(): void
    {
        self::assertInstanceOf(AbstractDomainObject::class, $this->subject);
    }

    /**
     * @test
     */
    public function implementsDomainObjectInterface(): void
    {
        self::assertInstanceOf(DomainObjectInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function implementsEventTopicInterface(): void
    {
        self::assertInstanceOf(EventTopicInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function getUidReturnsNull(): void
    {
        self::assertNull($this->subject->getUid());
    }

    /**
     * @test
     */
    public function getTitleReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function getCategoriesReturnsEmptyObjectStorage(): void
    {
        $categories = $this->subject->getCategories();

        self::assertInstanceOf(ObjectStorage::class, $categories);
        self::assertCount(0, $categories);
    }

    /**
     * @test
     */
    public function getTargetGroupsReturnsEmptyObjectStorage(): void
    {
        $targetGroups = $this->subject->getTargetGroups();

        self::assertInstanceOf(ObjectStorage::class, $targetGroups);
        self::assertCount(0, $targetGroups);
    }

    /**
     * @test
     */
    public function isHiddenReturnsFalse(): void
    {
        self::assertFalse($this->subject->isHidden());
    }

    /**
     * @test
     */
    public function setHiddenThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot set hidden on NullEventTopic');
        $this->expectExceptionCode(1757950691);

        $this->subject->setHidden(true);
    }

    /**
     * @test
     */
    public function isSingleEventReturnsFalse(): void
    {
        self::assertFalse($this->subject->isSingleEvent());
    }

    /**
     * @test
     */
    public function isEventDateReturnsFalse(): void
    {
        self::assertFalse($this->subject->isEventDate());
    }

    /**
     * @test
     */
    public function isEventTopicReturnsTrue(): void
    {
        self::assertTrue($this->subject->isEventTopic());
    }

    /**
     * @test
     */
    public function getInternalTitleRetursEmptyString(): void
    {
        self::assertSame('', $this->subject->getInternalTitle());
    }

    /**
     * @test
     */
    public function getDisplayTitleReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getDisplayTitle());
    }

    /**
     * @test
     */
    public function getDescriptionReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function getTeaserReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTeaser());
    }

    /**
     * @test
     */
    public function getStandardPriceReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getStandardPrice());
    }

    /**
     * @test
     */
    public function getEarlyBirdPriceReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getEarlyBirdPrice());
    }

    /**
     * @test
     */
    public function getSpecialPriceReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getSpecialPrice());
    }

    /**
     * @test
     */
    public function getSpecialEarlyBirdPriceReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getSpecialEarlyBirdPrice());
    }

    /**
     * @test
     */
    public function getEventTypeReturnsNull(): void
    {
        self::assertNull($this->subject->getEventType());
    }

    /**
     * @test
     */
    public function getOwnerUidReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getOwnerUid());
    }

    /**
     * @test
     */
    public function hasAdditionalTermsReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasAdditionalTerms());
    }

    /**
     * @test
     */
    public function isMultipleRegistrationPossibleReturnsFalse(): void
    {
        self::assertFalse($this->subject->isMultipleRegistrationPossible());
    }

    /**
     * @test
     */
    public function getPaymentMethodsReturnsEmptyObjectStorage(): void
    {
        $categories = $this->subject->getPaymentMethods();

        self::assertInstanceOf(ObjectStorage::class, $categories);
        self::assertCount(0, $categories);
    }

    /**
     * @test
     */
    public function isFreeOfChargeReturnsTrue(): void
    {
        self::assertTrue($this->subject->isFreeOfCharge());
    }

    /**
     * @test
     */
    public function getAllPricesReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getAllPrices());
    }

    /**
     * @test
     */
    public function getPriceByPriceCodeThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('NullEventTopic does not have any prices.');
        $this->expectExceptionCode(1757951370);

        $this->subject->getPriceByPriceCode(Price::PRICE_EARLY_BIRD);
    }

    /**
     * @test
     */
    public function getRawDataReturnsNull(): void
    {
        self::assertNull($this->subject->getRawData());
    }

    /**
     * @test
     */
    public function getStatisticsReturnsNull(): void
    {
        self::assertNull($this->subject->getStatistics());
    }

    /**
     * @test
     */
    public function getSlugReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getSlug());
    }

    /**
     * @test
     */
    public function setSlugThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('NullEventTopic cannot have a slug.');
        $this->expectExceptionCode(1757951419);

        $this->subject->setSlug('some-slug');
    }

    /**
     * @test
     */
    public function hasDownloadableCertificateAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasDownloadableCertificate());
    }
}
