<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyRegistration
 */
final class LegacyRegistrationTest extends UnitTestCase
{
    private LegacyRegistration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = LegacyRegistration::fromData([]);
    }

    /**
     * @test
     */
    public function isAbstractModel(): void
    {
        self::assertInstanceOf(AbstractModel::class, $this->subject);
    }

    /**
     * @test
     */
    public function fromDataCreatesInstanceOfSubclass(): void
    {
        $result = LegacyRegistration::fromData([]);

        self::assertInstanceOf(LegacyRegistration::class, $result);
    }

    /**
     * @test
     */
    public function getFrontEndUserWithoutUserUidReturnsNull(): void
    {
        $result = $this->subject->getFrontEndUser();

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function setFrontEndUserSetsFrontEndUser(): void
    {
        $user = new FrontEndUser();

        $this->subject->setFrontEndUser($user);

        self::assertSame($user, $this->subject->getFrontEndUser());
    }

    /**
     * @test
     */
    public function isOnRegistrationQueueByDefaultReturnsFalse(): void
    {
        self::assertFalse($this->subject->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function isOnRegistrationQueueForRegularRegistrationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_REGULAR);

        self::assertFalse($this->subject->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function isOnRegistrationQueueForWaitingListRegistrationReturnsTrue(): void
    {
        $this->subject->setStatus(Registration::STATUS_WAITING_LIST);

        self::assertTrue($this->subject->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function isOnRegistrationQueueForNonbindingReservationReturnsFalse(): void
    {
        $this->subject->setStatus(Registration::STATUS_NONBINDING_RESERVATION);

        self::assertFalse($this->subject->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function getMethodOfPaymentUidInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getMethodOfPaymentUid());
    }

    /**
     * @test
     */
    public function setMethodOfPaymentUidReturnsMethodOfPaymentUid(): void
    {
        $uid = 42;

        $subject = LegacyRegistration::fromData(['method_of_payment' => $uid]);

        self::assertSame($uid, $subject->getMethodOfPaymentUid());
    }

    /**
     * @test
     */
    public function setMethodOfPaymentUidSetsMethodOfPaymentUid(): void
    {
        $value = 123456;
        $this->subject->setMethodOfPaymentUid($value);

        self::assertSame($value, $this->subject->getMethodOfPaymentUid());
    }

    /**
     * @test
     */
    public function setMethodOfPaymentUidCanSetMethodOfPaymentUidToZero(): void
    {
        $value = 0;
        $this->subject->setMethodOfPaymentUid($value);

        self::assertSame($value, $this->subject->getMethodOfPaymentUid());
    }

    /**
     * @test
     */
    public function getUserDataForEmptyKeyReturnsEmptyString(): void
    {
        $result = $this->subject->getUserData('');

        self::assertSame('', $result);
    }

    /**
     * @test
     */
    public function getUserDataForInexistentKeyNameReturnsEmptyString(): void
    {
        $this->subject->setUserData(['name' => 'John Doe']);

        $result = $this->subject->getUserData('foo');

        self::assertSame('', $result);
    }

    /**
     * @test
     */
    public function setUserDataSetsUserData(): void
    {
        $key = 'www';
        $value = 'https://www.example.com/';

        $this->subject->setUserData([$key => $value]);

        self::assertSame($value, $this->subject->getUserData($key));
    }

    /**
     * @test
     */
    public function getUserDataReturnsIntegersAsString(): void
    {
        $key = 'pid';
        $value = 42;

        $this->subject->setUserData([$key => $value]);

        self::assertSame((string)$value, $this->subject->getUserData($key));
    }

    /**
     * @return array[]
     */
    public function nameUserDataDataProvider(): array
    {
        return [
            'first name' => [['username' => 'max', 'name' => '', 'first_name' => 'Max'], 'Max'],
            'last name' => [['username' => 'max', 'name' => '', 'last_name' => 'Caulfield'], 'Caulfield'],
            'first and last name' => [
                ['username' => 'max', 'name' => '', 'first_name' => 'Max', 'last_name' => 'Caulfield'],
                'Max Caulfield',
            ],
            'full only' => [['username' => 'max', 'name' => 'Max Caulfield'], 'Max Caulfield'],
        ];
    }

    /**
     * @test
     *
     * @param string[] $data
     * @param string $expectedName
     *
     * @dataProvider nameUserDataDataProvider
     */
    public function getUserDataForNameAssemblesNameFromSeveralSources(array $data, string $expectedName): void
    {
        $this->subject->setUserData($data);

        $user = new FrontEndUser();
        $user->setData($data);
        $this->subject->setFrontEndUser($user);

        self::assertSame($expectedName, $this->subject->getUserData('name'));
    }

    /**
     * @test
     */
    public function hasRegisteredThemselvesForRegisteredThemselvesByDefaultFalseReturnsFalse(): void
    {
        $result = $this->subject->hasRegisteredThemselves();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function hasRegisteredThemselvesReturnsRegisteredThemselves(): void
    {
        $subject = LegacyRegistration::fromData(['registered_themselves' => 1]);

        $result = $subject->hasRegisteredThemselves();

        self::assertTrue($result);
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function booleanDataProvider(): array
    {
        return [
            'false' => [false],
            'true' => [true],
        ];
    }

    /**
     * @test
     *
     * @dataProvider booleanDataProvider
     */
    public function setRegisteredThemselvesSetsRegisteredThemselves(bool $value): void
    {
        $this->subject->setRegisteredThemselves($value);

        $result = $this->subject->hasRegisteredThemselves();

        self::assertSame($value, $result);
    }

    // Tests regarding the billing address

    /**
     * @test
     */
    public function getBillingAddressWithCompanyContainsCompany(): void
    {
        $value = 'Psijic Order';
        $subject = LegacyRegistration::fromData(['company' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithNameContainsName(): void
    {
        $value = 'Max Doe';
        $subject = LegacyRegistration::fromData(['name' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithAddressContainsAddress(): void
    {
        $value = 'Main Street 123';
        $subject = LegacyRegistration::fromData(['address' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithZipCodeContainsZipCode(): void
    {
        $value = '12345';
        $subject = LegacyRegistration::fromData(['zip' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithCityContainsCity(): void
    {
        $value = 'Big City';
        $subject = LegacyRegistration::fromData(['city' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithCountryContainsCountry(): void
    {
        $value = 'Takka-Tukka-Land';
        $subject = LegacyRegistration::fromData(['country' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getRegistrationDataForEmptyKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // @phpstan-ignore-next-line We are explicitly testing a contract violation here.
        $this->subject->getRegistrationData('');
    }

    /**
     * @test
     */
    public function getRegistrationDataCanGetUid(): void
    {
        self::assertSame(
            (string)$this->subject->getUid(),
            $this->subject->getRegistrationData('uid'),
        );
    }

    /**
     * @test
     */
    public function getRegistrationDataForNotesWithCarriageReturnRemovesCarriageReturnFromNotes(): void
    {
        $subject = LegacyRegistration::fromData(['notes' => "foo\r\nbar"]);

        self::assertStringNotContainsString(
            "\r\n",
            $subject->getRegistrationData('notes'),
        );
    }

    /**
     * @test
     */
    public function getRegistrationDataForNotesWithCarriageReturnAndLineFeedReturnsNotesWithLinefeedAndNoCarriageReturn(): void
    {
        $subject = LegacyRegistration::fromData(['notes' => "foo\r\nbar"]);

        self::assertSame(
            "foo\nbar",
            $subject->getRegistrationData('notes'),
        );
    }

    /**
     * @test
     */
    public function getRegistrationDataForMultipleAttendeeNamesReturnsAttendeeNamesWithEnumeration(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => "foo\nbar"]);

        self::assertSame(
            "1. foo\n2. bar",
            $subject->getRegistrationData('attendees_names'),
        );
    }

    // Tests regarding the seats.

    /**
     * @test
     */
    public function getSeatsWithoutSeatsReturnsOne(): void
    {
        self::assertSame(
            1,
            $this->subject->getSeats(),
        );
    }

    /**
     * @test
     */
    public function setSeatsWithZeroSeatsSetsSeats(): void
    {
        $this->subject->setSeats(0);

        self::assertSame(
            1,
            $this->subject->getSeats(),
        );
    }

    /**
     * @test
     */
    public function setSeatsWithPositiveSeatsSetsSeats(): void
    {
        $this->subject->setSeats(42);

        self::assertSame(
            42,
            $this->subject->getSeats(),
        );
    }

    /**
     * @test
     */
    public function hasSeatsWithoutSeatsReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasSeats(),
        );
    }

    /**
     * @test
     */
    public function hasSeatsWithSeatsReturnsTrue(): void
    {
        $this->subject->setSeats(42);

        self::assertTrue(
            $this->subject->hasSeats(),
        );
    }

    // Tests regarding the attendees names.

    /**
     * @test
     */
    public function getAttendeesNamesWithoutAttendeesNamesReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getAttendeesNames(),
        );
    }

    /**
     * @test
     */
    public function setAttendeesNamesWithAttendeesNamesSetsAttendeesNames(): void
    {
        $this->subject->setAttendeesNames('John Doe');

        self::assertSame(
            'John Doe',
            $this->subject->getAttendeesNames(),
        );
    }

    /**
     * @test
     */
    public function hasAttendeesNamesWithoutAttendeesNamesReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasAttendeesNames(),
        );
    }

    /**
     * @test
     */
    public function hasAttendeesNamesWithAttendeesNamesReturnsTrue(): void
    {
        $this->subject->setAttendeesNames('John Doe');

        self::assertTrue(
            $this->subject->hasAttendeesNames(),
        );
    }

    // Tests regarding the kids.

    /**
     * @test
     */
    public function getNumberOfKidsWithoutKidsReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfKids(),
        );
    }

    /**
     * @test
     */
    public function setNumberOfKidsWithZeroNumberOfKidsSetsNumberOfKids(): void
    {
        $this->subject->setNumberOfKids(0);

        self::assertSame(
            0,
            $this->subject->getNumberOfKids(),
        );
    }

    /**
     * @test
     */
    public function setNumberOfKidsWithPositiveNumberOfKidsSetsNumberOfKids(): void
    {
        $this->subject->setNumberOfKids(42);

        self::assertSame(
            42,
            $this->subject->getNumberOfKids(),
        );
    }

    /**
     * @test
     */
    public function hasKidsWithoutKidsReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasKids(),
        );
    }

    /**
     * @test
     */
    public function hasKidsWithKidsReturnsTrue(): void
    {
        $this->subject->setNumberOfKids(42);

        self::assertTrue(
            $this->subject->hasKids(),
        );
    }

    // Tests regarding the price.

    /**
     * @test
     */
    public function getPriceWithoutPriceReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getPrice(),
        );
    }

    /**
     * @test
     */
    public function setPriceWithPriceSetsPrice(): void
    {
        $this->subject->setPrice('Regular price: 42.42');

        self::assertSame(
            'Regular price: 42.42',
            $this->subject->getPrice(),
        );
    }

    /**
     * @test
     */
    public function hasPriceWithoutPriceReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasPrice(),
        );
    }

    /**
     * @test
     */
    public function hasPriceWithPriceReturnsTrue(): void
    {
        $this->subject->setPrice('Regular price: 42.42');

        self::assertTrue(
            $this->subject->hasPrice(),
        );
    }

    // Tests regarding the method of payment.

    /**
     * @test
     */
    public function getMethodOfPaymentUidWithoutMethodOfPaymentReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getMethodOfPaymentUid(),
        );
    }

    /**
     * @test
     */
    public function setMethodOfPaymentUidWithZeroUidSetsMethodOfPaymentUid(): void
    {
        $this->subject->setMethodOfPaymentUid(0);

        self::assertSame(
            0,
            $this->subject->getMethodOfPaymentUid(),
        );
    }

    /**
     * @test
     */
    public function setMethodOfPaymentUidWithPositiveUidSetsMethodOfPaymentUid(): void
    {
        $this->subject->setMethodOfPaymentUid(42);

        self::assertSame(
            42,
            $this->subject->getMethodOfPaymentUid(),
        );
    }

    /**
     * @test
     */
    public function hasMethodOfPaymentWithoutMethodOfPaymentReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasMethodOfPayment(),
        );
    }

    /**
     * @test
     */
    public function hasMethodOfPaymentWithMethodOfPaymentReturnsTrue(): void
    {
        $this->subject->setMethodOfPaymentUid(42);

        self::assertTrue(
            $this->subject->hasMethodOfPayment(),
        );
    }

    // Tests concerning the food

    /**
     * @test
     */
    public function getFoodReturnsFood(): void
    {
        $food = 'a hamburger';
        $subject = LegacyRegistration::fromData(['food' => $food]);

        self::assertSame(
            $food,
            $subject->getFood(),
        );
    }

    /**
     * @test
     */
    public function hasFoodForEmptyFoodReturnsFalse(): void
    {
        $subject = LegacyRegistration::fromData(['food' => '']);

        self::assertFalse(
            $subject->hasFood(),
        );
    }

    /**
     * @test
     */
    public function hasFoodForNonEmptyFoodReturnsTrue(): void
    {
        $subject = LegacyRegistration::fromData(['food' => 'two donuts']);

        self::assertTrue(
            $subject->hasFood(),
        );
    }

    // Tests concerning the accommodation

    /**
     * @test
     */
    public function getAccommodationReturnsAccommodation(): void
    {
        $accommodation = 'a tent in the woods';

        $subject = LegacyRegistration::fromData(['accommodation' => $accommodation]);

        self::assertSame(
            $accommodation,
            $subject->getAccommodation(),
        );
    }

    /**
     * @test
     */
    public function hasAccommodationForEmptyAccommodationReturnsFalse(): void
    {
        $subject = LegacyRegistration::fromData(['accommodation' => '']);

        self::assertFalse(
            $subject->hasAccommodation(),
        );
    }

    /**
     * @test
     */
    public function hasAccommodationForNonEmptyAccommodationReturnsTrue(): void
    {
        $subject = LegacyRegistration::fromData(['accommodation' => 'a youth hostel']);

        self::assertTrue(
            $subject->hasAccommodation(),
        );
    }

    // Tests concerning the interests

    /**
     * @test
     */
    public function getInterestsReturnsInterests(): void
    {
        $interests = 'new experiences';

        $subject = LegacyRegistration::fromData(['interests' => $interests]);

        self::assertSame($interests, $subject->getInterests());
    }

    /**
     * @test
     */
    public function hasInterestsForEmptyInterestsReturnsFalse(): void
    {
        $subject = LegacyRegistration::fromData(['interests' => '']);

        self::assertFalse(
            $subject->hasInterests(),
        );
    }

    /**
     * @test
     */
    public function hasInterestsForNonEmptyInterestsReturnsTrue(): void
    {
        $subject = LegacyRegistration::fromData(['interests' => 'meeting people']);

        self::assertTrue(
            $subject->hasInterests(),
        );
    }
}
