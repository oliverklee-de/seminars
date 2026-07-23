<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Category;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Event\EventTopic;
use OliverKlee\Seminars\Domain\Model\Event\EventTopicInterface;
use OliverKlee\Seminars\Domain\Model\EventType;
use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\RawDataInterface;
use OliverKlee\Seminars\Domain\Model\TargetGroup;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Event\Event
 * @covers \OliverKlee\Seminars\Domain\Model\Event\EventTopic
 * @covers \OliverKlee\Seminars\Domain\Model\Event\EventTopicTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Event\EventTrait
 * @covers \OliverKlee\Seminars\Domain\Model\RawDataTrait
 */
final class EventTopicTest extends UnitTestCase
{
    private EventTopic $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new EventTopic();
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
    public function implementsDomainObjectInterface(): void
    {
        self::assertInstanceOf(DomainObjectInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function implementsRawDataInterface(): void
    {
        self::assertInstanceOf(RawDataInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function implementsEventInterface(): void
    {
        self::assertInstanceOf(EventInterface::class, $this->subject);
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
    public function isEvent(): void
    {
        self::assertInstanceOf(Event::class, $this->subject);
    }

    /**
     * @test
     */
    public function isHiddenInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isHidden());
    }

    /**
     * @test
     */
    public function setHiddenSetsHidden(): void
    {
        $this->subject->setHidden(true);

        self::assertTrue($this->subject->isHidden());
    }

    /**
     * @test
     */
    public function getInternalTitleInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getInternalTitle());
    }

    /**
     * @test
     */
    public function setInternalTitleSetsTitle(): void
    {
        $value = 'TYPO3 extension development';
        $this->subject->setInternalTitle($value);

        self::assertSame($value, $this->subject->getInternalTitle());
    }

    /**
     * @test
     */
    public function getDisplayTitleReturnsInternalTitle(): void
    {
        $value = 'TYPO3 extension development';
        $this->subject->setInternalTitle($value);

        self::assertSame($value, $this->subject->getDisplayTitle());
    }

    /**
     * @test
     */
    public function getDescriptionInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function setDescriptionSetsDescription(): void
    {
        $value = 'Club-Mate';
        $this->subject->setDescription($value);

        self::assertSame($value, $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function getTeaserInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTeaser());
    }

    /**
     * @test
     */
    public function setTeaserSetsTeaser(): void
    {
        $value = 'HEREIN HEREIN SPAZIERT';
        $this->subject->setTeaser($value);

        self::assertSame($value, $this->subject->getTeaser());
    }

    /**
     * @test
     */
    public function getStandardPriceInitiallyReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getStandardPrice());
    }

    /**
     * @return array<string, array{0: float}>
     */
    public function validPriceDataProvider(): array
    {
        return [
            'zero' => [0.0],
            'positive' => [1234.56],
        ];
    }

    /**
     * @test
     * @dataProvider validPriceDataProvider
     */
    public function setStandardPriceSetsStandardPrice(float $price): void
    {
        $this->subject->setStandardPrice($price);

        self::assertSame($price, $this->subject->getStandardPrice());
    }

    /**
     * @test
     */
    public function setStandardPriceWithNegativeNumberThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1666112500);
        $this->expectExceptionMessage('The price must be >= 0.0.');

        $this->subject->setStandardPrice(-1.0);
    }

    /**
     * @test
     */
    public function getEarlyBirdPriceInitiallyReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getEarlyBirdPrice());
    }

    /**
     * @test
     * @dataProvider validPriceDataProvider
     */
    public function setEarlyBirdPriceSetsEarlyBirdPrice(float $price): void
    {
        $this->subject->setEarlyBirdPrice($price);

        self::assertSame($price, $this->subject->getEarlyBirdPrice());
    }

    /**
     * @test
     */
    public function setEarlyBildPriceWithNegativeNumberThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1666112478);
        $this->expectExceptionMessage('The price must be >= 0.0.');

        $this->subject->setEarlyBirdPrice(-1.0);
    }

    /**
     * @test
     */
    public function getEventTypeInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getEventType());
    }

    /**
     * @test
     */
    public function setEventTypeSetsEventType(): void
    {
        $model = new EventType();
        $this->subject->setEventType($model);

        self::assertSame($model, $this->subject->getEventType());
    }

    /**
     * @test
     */
    public function setEventTypeCanSetEventTypeToNull(): void
    {
        $this->subject->setEventType(null);

        self::assertNull($this->subject->getEventType());
    }

    /**
     * @test
     */
    public function getOwnerUidInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getOwnerUid());
    }

    /**
     * @test
     */
    public function setOwnerUidSetsOwnerUid(): void
    {
        $value = 123456;
        $this->subject->setOwnerUid($value);

        self::assertSame($value, $this->subject->getOwnerUid());
    }

    /**
     * @test
     */
    public function hasAdditionalTermsInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasAdditionalTerms());
    }

    /**
     * @test
     */
    public function setAdditionalTermsSetsAdditionalTerms(): void
    {
        $this->subject->setAdditionalTerms(true);

        self::assertTrue($this->subject->hasAdditionalTerms());
    }

    /**
     * @test
     */
    public function isMultipleRegistrationPossibleInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isMultipleRegistrationPossible());
    }

    /**
     * @test
     */
    public function setMultipleRegistrationPossibleSetsMultipleRegistrationPossible(): void
    {
        $this->subject->setMultipleRegistrationPossible(true);

        self::assertTrue($this->subject->isMultipleRegistrationPossible());
    }

    /**
     * @test
     */
    public function getSpecialPriceInitiallyReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getSpecialPrice());
    }

    /**
     * @test
     * @dataProvider validPriceDataProvider
     */
    public function setSpecialPriceSetsSpecialPrice(float $price): void
    {
        $this->subject->setSpecialPrice($price);

        self::assertSame($price, $this->subject->getSpecialPrice());
    }

    /**
     * @test
     */
    public function getSpecialEarlyBirdPriceInitiallyReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getSpecialEarlyBirdPrice());
    }

    /**
     * @test
     * @dataProvider validPriceDataProvider
     */
    public function setSpecialEarlyBirdPriceSetsSpecialEarlyBirdPrice(float $price): void
    {
        $this->subject->setSpecialEarlyBirdPrice($price);

        self::assertSame($price, $this->subject->getSpecialEarlyBirdPrice());
    }

    /**
     * @test
     */
    public function getPaymentMethodsInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getPaymentMethods();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setPaymentMethodsSetsPaymentMethods(): void
    {
        /** @var ObjectStorage<PaymentMethod> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setPaymentMethods($associatedModels);

        self::assertSame($associatedModels, $this->subject->getPaymentMethods());
    }

    /**
     * @test
     */
    public function isFreeOfChargeForNoPricesSetReturnsTrue(): void
    {
        self::assertTrue($this->subject->isFreeOfCharge());
    }

    /**
     * @test
     */
    public function isFreeOfChargeForAllPricesSetToZeroReturnsTrue(): void
    {
        $this->subject->setStandardPrice(0.0);
        $this->subject->setEarlyBirdPrice(0.0);
        $this->subject->setSpecialPrice(0.0);
        $this->subject->setSpecialEarlyBirdPrice(0.0);

        self::assertTrue($this->subject->isFreeOfCharge());
    }

    /**
     * @test
     */
    public function isFreeOfChargeForNonZeroStandardPriceAndAllOtherPricesZeroReturnsFalse(): void
    {
        $this->subject->setStandardPrice(42.0);
        $this->subject->setEarlyBirdPrice(0.0);
        $this->subject->setSpecialPrice(0.0);
        $this->subject->setSpecialEarlyBirdPrice(0.0);

        self::assertFalse($this->subject->isFreeOfCharge());
    }

    /**
     * @test
     */
    public function isFreeOfChargeForAllPricesSetToNonZeroReturnsFalse(): void
    {
        $this->subject->setStandardPrice(1.0);
        $this->subject->setEarlyBirdPrice(2.0);
        $this->subject->setSpecialPrice(3.0);
        $this->subject->setSpecialEarlyBirdPrice(4.0);

        self::assertFalse($this->subject->isFreeOfCharge());
    }

    /**
     * @test
     */
    public function isFreeOfChargeForZeroStandardPriceAndAllOtherPricesNonZeroReturnsTrue(): void
    {
        $this->subject->setStandardPrice(0.0);
        $this->subject->setEarlyBirdPrice(2.0);
        $this->subject->setSpecialPrice(3.0);
        $this->subject->setSpecialEarlyBirdPrice(4.0);

        self::assertTrue($this->subject->isFreeOfCharge());
    }

    /**
     * @test
     */
    public function getAllPricesForNoPricesSetReturnsZeroStandardPrice(): void
    {
        $result = $this->subject->getAllPrices();

        self::assertCount(1, $result);
        $expected = [Price::PRICE_STANDARD => new Price(0.0, 'price.standard', Price::PRICE_STANDARD)];
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getAllPricesForAllPricesZeroReturnsZeroStandardPrice(): void
    {
        $this->subject->setStandardPrice(0.0);
        $this->subject->setEarlyBirdPrice(0.0);
        $this->subject->setSpecialPrice(0.0);
        $this->subject->setSpecialEarlyBirdPrice(0.0);

        $result = $this->subject->getAllPrices();

        self::assertCount(1, $result);
        $expected = [Price::PRICE_STANDARD => new Price(0.0, 'price.standard', Price::PRICE_STANDARD)];
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getAllPricesForZeroStandardPriceAndAllOtherPricesNonZeroReturnsZeroStandardPrice(): void
    {
        $this->subject->setStandardPrice(0.0);
        $this->subject->setEarlyBirdPrice(2.0);
        $this->subject->setSpecialPrice(3.0);
        $this->subject->setSpecialEarlyBirdPrice(4.0);

        $result = $this->subject->getAllPrices();

        self::assertCount(1, $result);
        $expected = [Price::PRICE_STANDARD => new Price(0.0, 'price.standard', Price::PRICE_STANDARD)];
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getAllPricesForForNonZeroStandardPriceAndAllOtherPricesZeroStandardPrice(): void
    {
        $standardPriceAmount = 200.0;
        $this->subject->setStandardPrice($standardPriceAmount);
        $this->subject->setEarlyBirdPrice(0.0);
        $this->subject->setSpecialPrice(0.0);
        $this->subject->setSpecialEarlyBirdPrice(0.0);

        $result = $this->subject->getAllPrices();

        self::assertCount(1, $result);
        $expected = [Price::PRICE_STANDARD => new Price($standardPriceAmount, 'price.standard', Price::PRICE_STANDARD)];
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getAllPricesForAllPricesNonZeroReturnsAllPrices(): void
    {
        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $specialPriceAmount = 3.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $this->subject->setStandardPrice($standardPriceAmount);
        $this->subject->setEarlyBirdPrice($earlyBirdPriceAmount);
        $this->subject->setSpecialPrice($specialPriceAmount);
        $this->subject->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->getAllPrices();

        self::assertCount(4, $result);
        $expected = [
            Price::PRICE_STANDARD => new Price($standardPriceAmount, 'price.standard', Price::PRICE_STANDARD),
            Price::PRICE_EARLY_BIRD => new Price($earlyBirdPriceAmount, 'price.earlyBird', Price::PRICE_EARLY_BIRD),
            Price::PRICE_SPECIAL => new Price($specialPriceAmount, 'price.special', Price::PRICE_SPECIAL),
            Price::PRICE_SPECIAL_EARLY_BIRD => new Price(
                $specialEarlyBirdPriceAmount,
                'price.specialEarlyBird',
                Price::PRICE_SPECIAL_EARLY_BIRD,
            ),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function getPriceByPriceCodeWithStandardPriceCodeForNoPricesReturnsFreeStandardPrice(): void
    {
        $expectedPrice = new Price(0.0, 'price.standard', Price::PRICE_STANDARD);
        self::assertEquals($expectedPrice, $this->subject->getPriceByPriceCode(Price::PRICE_STANDARD));
    }

    /**
     * @test
     */
    public function getPriceByPriceCodeForExistingNonStandardPriceWithThatCodeReturnsMatchingPrice(): void
    {
        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $this->subject->setStandardPrice($standardPriceAmount);
        $this->subject->setEarlyBirdPrice($earlyBirdPriceAmount);

        $expectedPrice = new Price($earlyBirdPriceAmount, 'price.earlyBird', Price::PRICE_EARLY_BIRD);
        self::assertEquals($expectedPrice, $this->subject->getPriceByPriceCode(Price::PRICE_EARLY_BIRD));
    }

    /**
     * @test
     */
    public function getPriceByPriceCodeForInexistentPriceThrowsException(): void
    {
        $standardPriceAmount = 1.0;
        $this->subject->setStandardPrice($standardPriceAmount);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1668096769);
        $this->expectExceptionMessage(
            'This event does not have a price with the code "' . Price::PRICE_EARLY_BIRD . '".',
        );

        $this->subject->getPriceByPriceCode(Price::PRICE_EARLY_BIRD);
    }

    /**
     * @test
     */
    public function getRawDataInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getRawData());
    }

    /**
     * @test
     */
    public function setRawDataSetsRawData(): void
    {
        $rawData = ['uid' => 5, 'title' => 'foo'];

        $this->subject->setRawData($rawData);

        self::assertSame($rawData, $this->subject->getRawData());
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
    public function isRegistrationPossibleByDateAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->subject->isRegistrationPossibleByDate());
    }

    /**
     * @test
     */
    public function getSlugInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getSlug());
    }

    /**
     * @test
     */
    public function setSlugSetsSlug(): void
    {
        $value = 'best-thing-ever/3';
        $this->subject->setSlug($value);

        self::assertSame($value, $this->subject->getSlug());
    }

    /**
     * @test
     */
    public function getCategoriesInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getCategories();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setCategoriesSetsCategories(): void
    {
        /** @var ObjectStorage<Category> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setCategories($associatedModels);

        self::assertSame($associatedModels, $this->subject->getCategories());
    }

    /**
     * @test
     */
    public function getTargetGroupsInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getTargetGroups();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setTargetGroupsSetsTargetGroups(): void
    {
        /** @var ObjectStorage<TargetGroup> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setTargetGroups($associatedModels);

        self::assertSame($associatedModels, $this->subject->getTargetGroups());
    }

    /**
     * @test
     */
    public function isSingleEventAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->subject->isSingleEvent());
    }

    /**
     * @test
     */
    public function isEventDateAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->subject->isEventDate());
    }

    /**
     * @test
     */
    public function isEventTopicAlwaysReturnsTrue(): void
    {
        self::assertTrue($this->subject->isEventTopic());
    }

    /**
     * @test
     */
    public function hasDownloadableCertificateAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasDownloadableCertificate());
    }
}
