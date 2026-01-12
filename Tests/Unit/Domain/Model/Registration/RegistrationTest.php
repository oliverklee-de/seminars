<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model\Registration;

use OliverKlee\Seminars\Domain\Model\AccommodationOption;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\FoodOption;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\RawDataInterface;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Domain\Model\RegistrationCheckbox;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\RawDataTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\AttendeesTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\BillingAddressTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\PaymentTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\Registration
 */
final class RegistrationTest extends UnitTestCase
{
    private Registration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Registration();
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
    public function implementsRawDataInterface(): void
    {
        self::assertInstanceOf(RawDataInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function getTitleInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function setTitleSetsTitle(): void
    {
        $value = 'the latest registration';
        $this->subject->setTitle($value);

        self::assertSame($value, $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function getEventInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getEvent());
    }

    /**
     * @test
     */
    public function setEventCanSetEventToSingleEvent(): void
    {
        $model = new SingleEvent();
        $this->subject->setEvent($model);

        self::assertSame($model, $this->subject->getEvent());
    }

    /**
     * @test
     */
    public function setEventCanSetEventToEventDate(): void
    {
        $model = new EventDate();
        $this->subject->setEvent($model);

        self::assertSame($model, $this->subject->getEvent());
    }

    /**
     * @test
     */
    public function getUserInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getUser());
    }

    /**
     * @test
     */
    public function setUserSetsUser(): void
    {
        $model = new FrontendUser();
        $this->subject->setUser($model);

        self::assertSame($model, $this->subject->getUser());
    }

    /**
     * @test
     */
    public function belongsToUserForRegistrationWithoutUserAndWithZeroUserUidReturnsFalse(): void
    {
        self::assertFalse($this->subject->belongsToUser(0));
    }

    /**
     * @test
     */
    public function belongsToUserForRegistrationWithoutUserAndWithNonZeroUserUidReturnsFalse(): void
    {
        self::assertFalse($this->subject->belongsToUser(1));
    }

    /**
     * @test
     */
    public function belongsToUserForRegistrationWithUserAndWithZeroUserUidReturnsFalse(): void
    {
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn(3);
        $this->subject->setUser($user);

        self::assertFalse($this->subject->belongsToUser(0));
    }

    /**
     * @test
     */
    public function belongsToUserForRegistrationWithUserAndWithNegativeUserUidReturnsFalse(): void
    {
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn(3);
        $this->subject->setUser($user);

        self::assertFalse($this->subject->belongsToUser(-1));
    }

    /**
     * @test
     */
    public function belongsToUserForRegistrationWithUserAndWithNonZeroDifferentUserUidReturnsFalse(): void
    {
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn(3);
        $this->subject->setUser($user);

        self::assertFalse($this->subject->belongsToUser(4));
    }

    /**
     * @test
     */
    public function belongsToUserForRegistrationWithUserAndWithIdenticalUserUidReturnsTrue(): void
    {
        $userUid = 7;
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn($userUid);
        $this->subject->setUser($user);

        self::assertTrue($this->subject->belongsToUser($userUid));
    }

    /**
     * @test
     */
    public function getStatusInitiallyReturnsRegular(): void
    {
        self::assertSame(Registration::STATUS_REGULAR, $this->subject->getStatus());
    }

    /**
     * @return array<string, array{0: Registration::STATUS_*}>
     */
    public static function statusDataProvider(): array
    {
        return [
            'regular' => [Registration::STATUS_REGULAR],
            'waiting list' => [Registration::STATUS_WAITING_LIST],
            'nonbinding reservation' => [Registration::STATUS_NONBINDING_RESERVATION],
        ];
    }

    /**
     * @test
     * @param Registration::STATUS_* $status
     * @dataProvider statusDataProvider
     */
    public function setStatusSetsStatus(int $status): void
    {
        $this->subject->setStatus($status);

        self::assertSame($status, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function isRegularRegistrationForRegularRegistrationReturnsTrue(): void
    {
        $this->subject->setStatus(Registration::STATUS_REGULAR);

        self::assertTrue($this->subject->isRegularRegistration());
    }

    /**
     * @test
     */
    public function isRegularRegistrationForWaitingListRegistrationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_WAITING_LIST);

        self::assertFalse($this->subject->isRegularRegistration());
    }

    /**
     * @test
     */
    public function isRegularRegistrationForNonbindingReservationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_NONBINDING_RESERVATION);

        self::assertFalse($this->subject->isRegularRegistration());
    }

    /**
     * @test
     */
    public function isOnWaitingListForRegularRegistrationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_REGULAR);

        self::assertFalse($this->subject->isOnWaitingList());
    }

    /**
     * @test
     */
    public function isOnWaitingListForWaitingListRegistrationReturnsTrue(): void
    {
        $this->subject->setStatus(Registration::STATUS_WAITING_LIST);

        self::assertTrue($this->subject->isOnWaitingList());
    }

    /**
     * @test
     */
    public function isOnWaitingListForNonbindingReservationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_NONBINDING_RESERVATION);

        self::assertFalse($this->subject->isOnWaitingList());
    }

    /**
     * @test
     */
    public function isNonbindingReservationForRegularRegistrationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_REGULAR);

        self::assertFalse($this->subject->isNonbindingReservation());
    }

    /**
     * @test
     */
    public function isNonbindingReservationForWaitingListRegistrationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_WAITING_LIST);

        self::assertFalse($this->subject->isNonbindingReservation());
    }

    /**
     * @test
     */
    public function isNonbindingReservationForNonbindingReservationReturnsTrue(): void
    {
        $this->subject->setStatus(Registration::STATUS_NONBINDING_RESERVATION);

        self::assertTrue($this->subject->isNonbindingReservation());
    }

    /**
     * @test
     */
    public function convertToRegularRegistrationKeepsRegularRegistrationUnchanged(): void
    {
        $this->subject->setStatus(Registration::STATUS_REGULAR);

        $this->subject->convertToRegularRegistration();

        self::assertSame(Registration::STATUS_REGULAR, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function convertToRegularRegistrationConvertsWaitingListRegistration(): void
    {
        $this->subject->setStatus(Registration::STATUS_WAITING_LIST);

        $this->subject->convertToRegularRegistration();

        self::assertSame(Registration::STATUS_REGULAR, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function convertToRegularRegistrationConvertsNonbindingReservation(): void
    {
        $this->subject->setStatus(Registration::STATUS_NONBINDING_RESERVATION);

        $this->subject->convertToRegularRegistration();

        self::assertSame(Registration::STATUS_REGULAR, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function moveToWaitingListConvertsRegularRegistration(): void
    {
        $this->subject->setStatus(Registration::STATUS_REGULAR);

        $this->subject->moveToWaitingList();

        self::assertSame(Registration::STATUS_WAITING_LIST, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function moveToWaitingListKeepsWaitingListRegistrationUnchanged(): void
    {
        $this->subject->setStatus(Registration::STATUS_WAITING_LIST);

        $this->subject->moveToWaitingList();

        self::assertSame(Registration::STATUS_WAITING_LIST, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function moveToWaitingListConvertsNonbindingReservation(): void
    {
        $this->subject->setStatus(Registration::STATUS_NONBINDING_RESERVATION);

        $this->subject->moveToWaitingList();

        self::assertSame(Registration::STATUS_WAITING_LIST, $this->subject->getStatus());
    }

    /**
     * @test
     */
    public function getPriceCodeInitiallyReturnsStandardPrice(): void
    {
        self::assertSame(Price::PRICE_STANDARD, $this->subject->getPriceCode());
    }

    /**
     * @return array<string, array{0: Price::PRICE_*}>
     */
    public function validPriceCodeDataProvider(): array
    {
        return [
            'standard' => [Price::PRICE_STANDARD],
            'early bird' => [Price::PRICE_EARLY_BIRD],
            'special' => [Price::PRICE_SPECIAL],
            'special early bird' => [Price::PRICE_SPECIAL_EARLY_BIRD],
        ];
    }

    /**
     * @test
     * @param Price::PRICE_* $priceCode
     * @dataProvider validPriceCodeDataProvider
     */
    public function setPriceCodeSetsPriceCode(string $priceCode): void
    {
        $this->subject->setPriceCode($priceCode);

        self::assertSame($priceCode, $this->subject->getPriceCode());
    }

    /**
     * @test
     */
    public function getPriceCodeWithInvalidPriceCodeReturnsStandardPrice(): void
    {
        // @phpstan-ignore-next-line We're explicitly testing with a contract violation here.
        $this->subject->setPriceCode('invalid');

        self::assertSame(Price::PRICE_STANDARD, $this->subject->getPriceCode());
    }

    /**
     * @test
     */
    public function getHumanReadablePriceInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getHumanReadablePrice());
    }

    /**
     * @test
     */
    public function setHumanReadablePriceSetsHumanReadablePrice(): void
    {
        $value = 'Club-Mate';
        $this->subject->setHumanReadablePrice($value);

        self::assertSame($value, $this->subject->getHumanReadablePrice());
    }

    /**
     * @test
     */
    public function getSeatsInitiallyReturnsOne(): void
    {
        self::assertSame(1, $this->subject->getSeats());
    }

    /**
     * @test
     */
    public function setSeatsSetsSeats(): void
    {
        $value = 123456;
        $this->subject->setSeats($value);

        self::assertSame($value, $this->subject->getSeats());
    }

    /**
     * @test
     */
    public function hasRegisteredThemselvesInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasRegisteredThemselves());
    }

    /**
     * @test
     */
    public function setRegisteredThemselvesSetsRegisteredThemselves(): void
    {
        $this->subject->setRegisteredThemselves(true);

        self::assertTrue($this->subject->hasRegisteredThemselves());
    }

    /**
     * @test
     */
    public function getTotalPriceInitiallyReturnsZero(): void
    {
        self::assertSame(0.0, $this->subject->getTotalPrice());
    }

    /**
     * @test
     */
    public function setTotalPriceSetsTotalPrice(): void
    {
        $value = 1234.56;
        $this->subject->setTotalPrice($value);

        self::assertSame($value, $this->subject->getTotalPrice());
    }

    /**
     * @test
     */
    public function getAttendeesNamesInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getAttendeesNames());
    }

    /**
     * @test
     */
    public function setAttendeesNamesSetsAttendeesNames(): void
    {
        $value = 'Club-Mate';
        $this->subject->setAttendeesNames($value);

        self::assertSame($value, $this->subject->getAttendeesNames());
    }

    /**
     * @test
     */
    public function getBillingCompanyInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingCompany());
    }

    /**
     * @test
     */
    public function setBillingCompanySetsBillingCompany(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingCompany($value);

        self::assertSame($value, $this->subject->getBillingCompany());
    }

    /**
     * @test
     */
    public function getBillingFullNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingFullName());
    }

    /**
     * @test
     */
    public function setBillingFullNameSetsBillingFullName(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingFullName($value);

        self::assertSame($value, $this->subject->getBillingFullName());
    }

    /**
     * @test
     */
    public function getBillingStreetAddressInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingStreetAddress());
    }

    /**
     * @test
     */
    public function setBillingStreetAddressSetsBillingStreetAddress(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingStreetAddress($value);

        self::assertSame($value, $this->subject->getBillingStreetAddress());
    }

    /**
     * @test
     */
    public function getBillingZipCodeInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingZipCode());
    }

    /**
     * @test
     */
    public function setBillingZipCodeSetsBillingZipCode(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingZipCode($value);

        self::assertSame($value, $this->subject->getBillingZipCode());
    }

    /**
     * @test
     */
    public function getBillingCityInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingCity());
    }

    /**
     * @test
     */
    public function setBillingCitySetsBillingCity(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingCity($value);

        self::assertSame($value, $this->subject->getBillingCity());
    }

    /**
     * @test
     */
    public function getBillingCountryInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingCountry());
    }

    /**
     * @test
     */
    public function setBillingCountrySetsBillingCountry(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingCountry($value);

        self::assertSame($value, $this->subject->getBillingCountry());
    }

    /**
     * @test
     */
    public function getBillingPhoneNumberInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingPhoneNumber());
    }

    /**
     * @test
     */
    public function setBillingPhoneNumberSetsBillingPhoneNumber(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingPhoneNumber($value);

        self::assertSame($value, $this->subject->getBillingPhoneNumber());
    }

    /**
     * @test
     */
    public function getBillingEmailAddressInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBillingEmailAddress());
    }

    /**
     * @test
     */
    public function setBillingEmailAddressSetsBillingEmailAddress(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBillingEmailAddress($value);

        self::assertSame($value, $this->subject->getBillingEmailAddress());
    }

    /**
     * @test
     */
    public function getInterestsInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getInterests());
    }

    /**
     * @test
     */
    public function setInterestsSetsInterests(): void
    {
        $value = 'Club-Mate';
        $this->subject->setInterests($value);

        self::assertSame($value, $this->subject->getInterests());
    }

    /**
     * @test
     */
    public function getExpectationsInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getExpectations());
    }

    /**
     * @test
     */
    public function setExpectationsSetsExpectations(): void
    {
        $value = 'Club-Mate';
        $this->subject->setExpectations($value);

        self::assertSame($value, $this->subject->getExpectations());
    }

    /**
     * @test
     */
    public function getCommentsInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getComments());
    }

    /**
     * @test
     */
    public function setCommentsSetsComments(): void
    {
        $value = 'Club-Mate';
        $this->subject->setComments($value);

        self::assertSame($value, $this->subject->getComments());
    }

    /**
     * @test
     */
    public function getKnownFromInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getKnownFrom());
    }

    /**
     * @test
     */
    public function setKnownFromSetsKnownFrom(): void
    {
        $value = 'Club-Mate';
        $this->subject->setKnownFrom($value);

        self::assertSame($value, $this->subject->getKnownFrom());
    }

    /**
     * @test
     */
    public function getAdditionalPersonsInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getAdditionalPersons();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setAdditionalPersonsSetsAdditionalPersons(): void
    {
        /** @var ObjectStorage<FrontendUser> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setAdditionalPersons($associatedModels);

        self::assertSame($associatedModels, $this->subject->getAdditionalPersons());
    }

    /**
     * @test
     */
    public function addAdditionalPersonAddsAdditionalPerson(): void
    {
        $additionalPerson = new FrontendUser();

        $this->subject->addAdditionalPerson($additionalPerson);

        self::assertTrue($this->subject->getAdditionalPersons()->contains($additionalPerson));
    }

    /**
     * @test
     */
    public function getAccommodationOptionsInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getAccommodationOptions();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setAccommodationOptionsSetsAccommodationOptions(): void
    {
        /** @var ObjectStorage<AccommodationOption> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setAccommodationOptions($associatedModels);

        self::assertSame($associatedModels, $this->subject->getAccommodationOptions());
    }

    /**
     * @test
     */
    public function getAccommodationOptionTitlesForNoOptionsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getAccommodationOptionTitles());
    }

    /**
     * @test
     */
    public function getAccommodationOptionTitlesReturnsTitlesOfOptions(): void
    {
        /** @var ObjectStorage<AccommodationOption> $options */
        $options = new ObjectStorage();
        $title1 = 'this';
        $option1 = new AccommodationOption();
        $option1->setTitle($title1);
        $options->attach($option1);
        $title2 = 'that';
        $option2 = new AccommodationOption();
        $option2->setTitle($title2);
        $options->attach($option2);
        $this->subject->setAccommodationOptions($options);

        self::assertSame([$title1, $title2], $this->subject->getAccommodationOptionTitles());
    }

    /**
     * @test
     */
    public function getFoodOptionsInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getFoodOptions();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setFoodOptionsSetsFoodOptions(): void
    {
        /** @var ObjectStorage<FoodOption> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setFoodOptions($associatedModels);

        self::assertSame($associatedModels, $this->subject->getFoodOptions());
    }

    /**
     * @test
     */
    public function getFoodOptionTitlesForNoOptionsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getFoodOptionTitles());
    }

    /**
     * @test
     */
    public function getFoodOptionTitlesReturnsTitlesOfOptions(): void
    {
        /** @var ObjectStorage<FoodOption> $options */
        $options = new ObjectStorage();
        $title1 = 'this';
        $option1 = new FoodOption();
        $option1->setTitle($title1);
        $options->attach($option1);
        $title2 = 'that';
        $option2 = new FoodOption();
        $option2->setTitle($title2);
        $options->attach($option2);
        $this->subject->setFoodOptions($options);

        self::assertSame([$title1, $title2], $this->subject->getFoodOptionTitles());
    }

    /**
     * @test
     */
    public function getRegistrationCheckboxesInitiallyReturnsEmptyStorage(): void
    {
        $associatedModels = $this->subject->getRegistrationCheckboxes();

        self::assertInstanceOf(ObjectStorage::class, $associatedModels);
        self::assertCount(0, $associatedModels);
    }

    /**
     * @test
     */
    public function setRegistrationCheckboxesSetsCheckboxes(): void
    {
        /** @var ObjectStorage<RegistrationCheckbox> $associatedModels */
        $associatedModels = new ObjectStorage();
        $this->subject->setRegistrationCheckboxes($associatedModels);

        self::assertSame($associatedModels, $this->subject->getRegistrationCheckboxes());
    }

    /**
     * @test
     */
    public function getRegistrationCheckboxTitlesForNoOptionsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getRegistrationCheckboxTitles());
    }

    /**
     * @test
     */
    public function getRegistrationCheckboxTitlesReturnsTitlesOfOptions(): void
    {
        /** @var ObjectStorage<RegistrationCheckbox> $options */
        $options = new ObjectStorage();
        $title1 = 'this';
        $option1 = new RegistrationCheckbox();
        $option1->setTitle($title1);
        $options->attach($option1);
        $title2 = 'that';
        $option2 = new RegistrationCheckbox();
        $option2->setTitle($title2);
        $options->attach($option2);
        $this->subject->setRegistrationCheckboxes($options);

        self::assertSame([$title1, $title2], $this->subject->getRegistrationCheckboxTitles());
    }

    /**
     * @test
     */
    public function getPaymentMethodInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getPaymentMethod());
    }

    /**
     * @test
     */
    public function setPaymentMethodSetsPaymentMethod(): void
    {
        $model = new PaymentMethod();
        $this->subject->setPaymentMethod($model);

        self::assertSame($model, $this->subject->getPaymentMethod());
    }

    /**
     * @test
     */
    public function setPaymentMethodCanSetPaymentMethodToNull(): void
    {
        $this->subject->setPaymentMethod(null);

        self::assertNull($this->subject->getPaymentMethod());
    }

    /**
     * @test
     */
    public function getBackgroundKnowledgeInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getBackgroundKnowledge());
    }

    /**
     * @test
     */
    public function setBackgroundKnowledgeSetsBackgroundKnowledge(): void
    {
        $value = 'Club-Mate';
        $this->subject->setBackgroundKnowledge($value);

        self::assertSame($value, $this->subject->getBackgroundKnowledge());
    }

    /**
     * @test
     */
    public function hasConsentedToTermsAndConditionsInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasConsentedToTermsAndConditions());
    }

    /**
     * @test
     */
    public function setConsentedToTermsAndConditionsSetsConsentedToTermsAndConditions(): void
    {
        $this->subject->setConsentedToTermsAndConditions(true);

        self::assertTrue($this->subject->hasConsentedToTermsAndConditions());
    }

    /**
     * @test
     */
    public function hasConsentedToAdditionalTermsInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasConsentedToAdditionalTerms());
    }

    /**
     * @test
     */
    public function setConsentedToAdditionalTermsSetsConsentedToAdditionalTerms(): void
    {
        $this->subject->setConsentedToAdditionalTerms(true);

        self::assertTrue($this->subject->hasConsentedToAdditionalTerms());
    }

    /**
     * @test
     */
    public function getJsonEncodedAdditionAttendeesInitiallyReturnsEmptyStringJsonObject(): void
    {
        self::assertSame('{}', $this->subject->getJsonEncodedAdditionAttendees());
    }

    /**
     * @test
     */
    public function setJsonEncodedAdditionAttendeesSetsJsonEncodedAdditionAttendees(): void
    {
        $value = 'Club-Mate';
        $this->subject->setJsonEncodedAdditionAttendees($value);

        self::assertSame($value, $this->subject->getJsonEncodedAdditionAttendees());
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
    public function getAttendanceModeInitiallyReturnsNotSet(): void
    {
        self::assertSame(Registration::ATTENDANCE_MODE_NOT_SET, $this->subject->getAttendanceMode());
    }

    /**
     * @return array<string, array{0: Registration::ATTENDANCE_MODE_*}>
     */
    public static function attendanceModeDataProvider(): array
    {
        return [
            'not set' => [Registration::ATTENDANCE_MODE_NOT_SET],
            'on site' => [Registration::ATTENDANCE_MODE_ON_SITE],
            'online' => [Registration::ATTENDANCE_MODE_ONLINE],
            'hybrid' => [Registration::ATTENDANCE_MODE_HYBRID],
        ];
    }

    /**
     * @test
     *
     * @param Registration::ATTENDANCE_MODE_* $mode
     * @dataProvider attendanceModeDataProvider
     */
    public function setAttendanceModeCanSetAttendanceModeToAnyExistingMode(int $mode): void
    {
        $this->subject->setAttendanceMode($mode);

        self::assertSame($mode, $this->subject->getAttendanceMode());
    }

    /**
     * @return array<string, array{0: Registration::ATTENDANCE_MODE_*}>
     */
    public static function onSiteAttendanceModeDataProvider(): array
    {
        return [
            'on site' => [Registration::ATTENDANCE_MODE_ON_SITE],
            'hybrid' => [Registration::ATTENDANCE_MODE_HYBRID],
        ];
    }

    /**
     * @test
     * @param Registration::ATTENDANCE_MODE_* $mode
     * @dataProvider onSiteAttendanceModeDataProvider
     */
    public function isAtLeastPartiallyOnSiteForAtLeastPartiallyOnSiteAttendanceReturnsTrue(int $mode): void
    {
        $this->subject->setAttendanceMode($mode);

        self::assertTrue($this->subject->isAtLeastPartiallyOnSite());
    }

    /**
     * @return array<string, array{0: Registration::ATTENDANCE_MODE_*}>
     */
    public static function notOnSiteAttendanceModeDataProvider(): array
    {
        return [
            'not set' => [Registration::ATTENDANCE_MODE_NOT_SET],
            'online' => [Registration::ATTENDANCE_MODE_ONLINE],
        ];
    }

    /**
     * @test
     * @param Registration::ATTENDANCE_MODE_* $mode
     * @dataProvider notOnSiteAttendanceModeDataProvider
     */
    public function isAtLeastPartiallyOnSiteForAtNotLeastPartiallyOnSiteAttendanceReturnsFalse(int $mode): void
    {
        $this->subject->setAttendanceMode($mode);

        self::assertFalse($this->subject->isAtLeastPartiallyOnSite());
    }

    /**
     * @return array<string, array{0: Registration::ATTENDANCE_MODE_*}>
     */
    public static function onlineAttendanceModeDataProvider(): array
    {
        return [
            'online' => [Registration::ATTENDANCE_MODE_ONLINE],
            'hybrid' => [Registration::ATTENDANCE_MODE_HYBRID],
        ];
    }

    /**
     * @test
     * @param Registration::ATTENDANCE_MODE_* $mode
     * @dataProvider onlineAttendanceModeDataProvider
     */
    public function isAtLeastPartiallyOnlineForAtLeastPartiallyOnlineAttendanceReturnsTrue(int $mode): void
    {
        $this->subject->setAttendanceMode($mode);

        self::assertTrue($this->subject->isAtLeastPartiallyOnline());
    }

    /**
     * @return array<string, array{0: Registration::ATTENDANCE_MODE_*}>
     */
    public static function notOnlineAttendanceModeDataProvider(): array
    {
        return [
            'not set' => [Registration::ATTENDANCE_MODE_NOT_SET],
            'on site' => [Registration::ATTENDANCE_MODE_ON_SITE],
        ];
    }

    /**
     * @test
     * @param Registration::ATTENDANCE_MODE_* $mode
     * @dataProvider notOnlineAttendanceModeDataProvider
     */
    public function isAtLeastPartiallyOnlineForAtNotLeastPartiallyOnlineAttendanceReturnsFalse(int $mode): void
    {
        $this->subject->setAttendanceMode($mode);

        self::assertFalse($this->subject->isAtLeastPartiallyOnline());
    }

    /**
     * @test
     */
    public function getOrderReferenceInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getOrderReference());
    }

    /**
     * @test
     */
    public function setOrderReferenceSetsOrderReference(): void
    {
        $value = 'Club-Mate';
        $this->subject->setOrderReference($value);

        self::assertSame($value, $this->subject->getOrderReference());
    }

    /**
     * @test
     */
    public function getInvoiceDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getInvoiceDate());
    }

    /**
     * @test
     */
    public function setInvoiceDateSetsInvoiceDate(): void
    {
        $date = new \DateTime();
        $this->subject->setInvoiceDate($date);

        self::assertSame($date, $this->subject->getInvoiceDate());
    }

    /**
     * @test
     */
    public function getInvoiceNumberInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getInvoiceNumber());
    }

    /**
     * @test
     */
    public function setInvoiceNumberSetsInvoiceNumber(): void
    {
        $value = 123456;
        $this->subject->setInvoiceNumber($value);

        self::assertSame($value, $this->subject->getInvoiceNumber());
    }

    /**
     * @test
     */
    public function hasInvoiceWithoutInvoiceNumberAndWithoutInvoiceDateReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasInvoice());
    }

    /**
     * @test
     */
    public function hasInvoiceWithoutInvoiceNumberAndWithInvoiceDateReturnsFalse(): void
    {
        $this->subject->setInvoiceDate(new \DateTime());

        self::assertFalse($this->subject->hasInvoice());
    }

    /**
     * @test
     */
    public function hasInvoiceWithZeroInvoiceNumberAndWithInvoiceDateReturnsFalse(): void
    {
        $this->subject->setInvoiceNumber(0);
        $this->subject->setInvoiceDate(new \DateTime());

        self::assertFalse($this->subject->hasInvoice());
    }

    /**
     * @test
     */
    public function hasInvoiceWithInvoiceNumberAndWithoutInvoiceDateReturnsFalse(): void
    {
        $this->subject->setInvoiceNumber(123456);

        self::assertFalse($this->subject->hasInvoice());
    }

    /**
     * @test
     */
    public function hasInvoiceWithInvoiceNumberAndWithInvoiceDateReturnsTrue(): void
    {
        $this->subject->setInvoiceNumber(123456);
        $this->subject->setInvoiceDate(new \DateTime());

        self::assertTrue($this->subject->hasInvoice());
    }

    /**
     * @test
     */
    public function getCustomerNumberInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getCustomerNumber());
    }

    /**
     * @test
     */
    public function setCustomerNumberSetsCustomerNumber(): void
    {
        $value = 123456;
        $this->subject->setCustomerNumber($value);

        self::assertSame($value, $this->subject->getCustomerNumber());
    }

    /**
     * @test
     */
    public function getPaymentDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getPaymentDate());
    }

    /**
     * @test
     */
    public function setPaymentDateSetsPaymentDate(): void
    {
        $date = new \DateTime();
        $this->subject->setPaymentDate($date);

        self::assertSame($date, $this->subject->getPaymentDate());
    }

    /**
     * @test
     */
    public function isPaidWithoutPaymentDateReturnsFalse(): void
    {
        self::assertFalse($this->subject->isPaid());
    }

    /**
     * @test
     */
    public function isPaidWithPaymentDateReturnsTrue(): void
    {
        $model = new \DateTime();
        $this->subject->setPaymentDate($model);

        self::assertTrue($this->subject->isPaid());
    }

    /**
     * @test
     */
    public function hasDownloadableCertificateAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasDownloadableCertificate());
    }
}
