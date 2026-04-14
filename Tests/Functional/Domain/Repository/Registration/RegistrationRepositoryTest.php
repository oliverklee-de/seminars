<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository\Registration;

use OliverKlee\Seminars\Domain\Model\AccommodationOption;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\FoodOption;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Domain\Model\RegistrationCheckbox;
use OliverKlee\Seminars\Domain\Repository\AbstractRawDataCapableRepository;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Tests\Support\BackEndTestsTrait;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\RawDataTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\AttendeesTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\BillingAddressTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\PaymentTrait
 * @covers \OliverKlee\Seminars\Domain\Model\Registration\Registration
 * @covers \OliverKlee\Seminars\Domain\Repository\AbstractRawDataCapableRepository
 * @covers \OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository
 */
final class RegistrationRepositoryTest extends FunctionalTestCase
{
    use BackEndTestsTrait;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RegistrationRepository $subject;

    private EventRepository $eventRepository;

    private PersistenceManager $persistenceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(RegistrationRepository::class);
        $this->eventRepository = $this->get(EventRepository::class);
        $this->persistenceManager = $this->get(PersistenceManager::class);
    }

    private function initializeBackEndUser(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackEndUser.csv');
        $this->setUpBackendUser(1);
        $this->unifyBackEndLanguage();
    }

    /**
     * @test
     */
    public function isRepository(): void
    {
        self::assertInstanceOf(Repository::class, $this->subject);
    }

    /**
     * @test
     */
    public function isRawDataCapableRepository(): void
    {
        self::assertInstanceOf(AbstractRawDataCapableRepository::class, $this->subject);
    }

    /**
     * @test
     */
    public function mapsAllModelFieldsFromTheBaseModel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithAllFields.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertSame('some new registration', $result->getTitle());
        self::assertNull($result->getEvent());
        self::assertSame(Registration::STATUS_WAITING_LIST, $result->getStatus());
        self::assertSame('escapism', $result->getInterests());
        self::assertSame('fast escapes', $result->getExpectations());
        self::assertSame('Looking forward to the event!', $result->getComments());
        self::assertSame('the internet', $result->getKnownFrom());
        self::assertSame('crash course', $result->getBackgroundKnowledge());
        self::assertSame(Registration::ATTENDANCE_MODE_ON_SITE, $result->getAttendanceMode());
        self::assertEquals(new \DateTime('2026-03-01 09:00:00'), $result->getRegistrationDate());
    }

    /**
     * @test
     */
    public function mapsAllModelFieldsFromTheAttendeesTrait(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(Registration::class, $result);
        self::assertNull($result->getUser());
        self::assertSame(3, $result->getSeats());
        self::assertTrue($result->hasRegisteredThemselves());
        self::assertSame('Max und Moritz', $result->getAttendeesNames());
    }

    /**
     * @test
     */
    public function mapsAllModelFieldsFromTheBillingAddressTrait(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithAllFields.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertSame('ACME Inc.', $result->getBillingCompany());
        self::assertSame('Dan Chase', $result->getBillingFullName());
        self::assertSame('Abenteuerland 5', $result->getBillingStreetAddress());
        self::assertSame('01234', $result->getBillingZipCode());
        self::assertSame('Solar City', $result->getBillingCity());
        self::assertSame('Solar Country', $result->getBillingCountry());
        self::assertSame('+49 0000 1928765', $result->getBillingPhoneNumber());
        self::assertSame('billing@example.com', $result->getBillingEmailAddress());
    }

    /**
     * @test
     */
    public function mapsAllModelFieldsFromThePaymentTrait(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithAllFields.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertSame(Price::PRICE_EARLY_BIRD, $result->getPriceCode());
        self::assertSame('Standard price: 200€', $result->getHumanReadablePrice());
        self::assertSame(199.99, $result->getTotalPrice());
        self::assertNull($result->getPaymentMethod());
        self::assertSame('order-1234', $result->getOrderReference());
        self::assertEquals(new \DateTime('2025-04-01T10:00:00'), $result->getPaymentDate());
        self::assertEquals(new \DateTime('2023-04-01T10:00:00'), $result->getInvoiceDate());
        self::assertSame(45, $result->getCustomerNumber());
        self::assertSame(1000003, $result->getInvoiceNumber());
    }

    /**
     * @test
     */
    public function collmexInvoiceNumberDefaultsToNull(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithoutData.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getInvoiceNumber());
    }

    /**
     * @test
     */
    public function collmexCustomerNumberDefaultsToNull(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithoutData.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getCustomerNumber());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findAll/RegistrationOnPage.csv');

        $result = $this->subject->findAll();

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function canPersistModel(): void
    {
        $registration = new Registration();

        $this->subject->add($registration);
        $this->persistenceManager->persistAll();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/persistence/Registration.csv');
    }

    /**
     * @test
     */
    public function mapsEventAssociationWithSingleEvent(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithSingleEvent.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(SingleEvent::class, $result->getEvent());
    }

    /**
     * @test
     */
    public function mapsDeletedEventAssociationAsNull(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithDeletedEvent.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getEvent());
    }

    /**
     * @test
     */
    public function mapsEventAssociationWithEventDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithEventDate.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(EventDate::class, $result->getEvent());
    }

    /**
     * @test
     */
    public function mapsUserAssociation(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithUser.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(FrontendUser::class, $result->getUser());
    }

    /**
     * @test
     */
    public function mapsDeletedUserAssociationAsNull(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithDeletedUser.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getUser());
    }

    /**
     * @test
     */
    public function mapsAdditionalPersonsAssociation(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithAdditionalPerson.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        $association = $result->getAdditionalPersons();
        self::assertInstanceOf(ObjectStorage::class, $association);
        self::assertCount(1, $association);
        self::assertInstanceOf(FrontendUser::class, $association->toArray()[0]);
    }

    /**
     * @test
     */
    public function mapsAccommodationOptionsAssociation(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithAccommodationOption.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        $association = $result->getAccommodationOptions();
        self::assertInstanceOf(ObjectStorage::class, $association);
        self::assertCount(1, $association);
        self::assertInstanceOf(AccommodationOption::class, $association->toArray()[0]);
    }

    /**
     * @test
     */
    public function mapsFoodOptionsAssociation(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithFoodOption.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        $association = $result->getFoodOptions();
        self::assertInstanceOf(ObjectStorage::class, $association);
        self::assertCount(1, $association);
        self::assertInstanceOf(FoodOption::class, $association->toArray()[0]);
    }

    /**
     * @test
     */
    public function mapsRegistrationCheckboxesAssociation(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithRegistrationCheckbox.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        $association = $result->getRegistrationCheckboxes();
        self::assertInstanceOf(ObjectStorage::class, $association);
        self::assertCount(1, $association);
        self::assertInstanceOf(RegistrationCheckbox::class, $association->toArray()[0]);
    }

    /**
     * @test
     */
    public function mapsPaymentMethodAssociation(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithPaymentMethod.xml');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(PaymentMethod::class, $result->getPaymentMethod());
    }

    /**
     * @test
     */
    public function mapsDeletedPaymentMethodAssociationAsNull(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/propertyMapping/RegistrationWithDeletedPaymentMethod.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getPaymentMethod());
    }

    /**
     * @test
     */
    public function existsRegistrationForEventAndUserForZeroUserUidReturnsFalse(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/existsRegistrationForEventAndUser/RegistrationWithEventAndUser.csv',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 0));
    }

    /**
     * @test
     */
    public function existsRegistrationForEventAndOtherUserUidReturnsFalse(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/existsRegistrationForEventAndUser/RegistrationWithEventAndUser.csv',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 2));
    }

    /**
     * @test
     */
    public function existsRegistrationForOtherEventAndThisUserUidReturnsFalse(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/RegistrationWithEventAndUserAndAdditionalEvent.xml');
        $event = $this->eventRepository->findByUid(2);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationForHiddenMatchingRegistrationReturnsFalse(): void
    {
        $this->importDataSet(
            __DIR__ . '/Fixtures/existsRegistrationForEventAndUser/HiddenRegistrationWithEventAndUser.xml',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationForDeletedMatchingRegistrationReturnsFalse(): void
    {
        $this->importDataSet(
            __DIR__ . '/Fixtures/existsRegistrationForEventAndUser/DeletedRegistrationWithEventAndUser.xml',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationForEventAndUserForMatchingRegistrationReturnsTrue(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/existsRegistrationForEventAndUser/RegistrationWithEventAndUser.csv',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertTrue($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationTwoMatchingRegistrationsReturnsTrue(): void
    {
        $this->importDataSet(
            __DIR__ . '/Fixtures/existsRegistrationForEventAndUser/TwoRegistrationsWithSameEventAndUser.xml',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertTrue($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidForNoRegistrationsInDatabaseReturnsZero(): void
    {
        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidCountsRegularRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularRegistrationsByPageUid/RegistrationOnPage.csv');

        self::assertSame(1, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresRegistrationsOnOtherPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularRegistrationsByPageUid/RegistrationOnPage.csv');

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(2));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresWaitingListRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countRegularRegistrationsByPageUid/WaitingListRegistrationOnPage.csv',
        );

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresNonbindingReservationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countRegularRegistrationsByPageUid/NonbindingReservationOnPage.csv',
        );

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresHiddenRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularRegistrationsByPageUid/HiddenRegistrationOnPage.csv');

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresDeletedRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularRegistrationsByPageUid/DeletedRegistrationOnPage.csv');

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventForNonExistentEventUidReturnsZero(): void
    {
        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresRegularRegistrationsWithZeroSeats(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularSeatsByEvent/RegularRegistrationWithZeroSeats.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventSumsUpSingleSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countRegularSeatsByEvent/TwoRegistrationsWithSameEventAndUser.csv',
        );

        self::assertSame(2, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresSeatFromOtherEvents(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countRegularSeatsByEvent/RegistrationWithEventAndAdditionalEvent.csv',
        );

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(2));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventSumsUpMultiSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countRegularSeatsByEvent/TwoMultiSeatRegistrationsWithSameEvent.csv',
        );

        self::assertSame(5, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresHiddenRegistration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularSeatsByEvent/HiddenRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresDeletedRegistration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularSeatsByEvent/DeletedRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresWaitingListRegistration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularSeatsByEvent/WaitingListRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresNonbindingReservation(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countRegularSeatsByEvent/NonbindingReservationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventForNonExistentEventUidReturnsZero(): void
    {
        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresRegistrationsWithZeroSeats(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countWaitingListSeatsByEvent/WaitingListRegistrationWithZeroSeats.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventSumsUpSingleSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countWaitingListSeatsByEvent/TwoWaitingListRegistrationsWithSameEventAndUser.csv',
        );

        self::assertSame(2, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresSeatFromOtherEvents(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countWaitingListSeatsByEvent/TwoWaitingListRegistrationsWithSameEventAndUser.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(2));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventSumsUpMultiSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countWaitingListSeatsByEvent/TwoMultiSeatWaitingListRegistrationsWithSameEvent.csv',
        );

        self::assertSame(5, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresHiddenRegistration(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countWaitingListSeatsByEvent/HiddenWaitingListRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresDeletedRegistration(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countWaitingListSeatsByEvent/DeletedWaitingListRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresRegularRegistration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countWaitingListSeatsByEvent/RegularRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresNonbindingReservation(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/countWaitingListSeatsByEvent/NonbindingReservationWithEvent.csv');

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventForNonExistentEventUidReturnsZero(): void
    {
        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresReservationWithZeroSeats(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countNonbindingReservationSeatsByEvent/NonbindingReservationWithZeroSeats.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventSumsUpSingleSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/countNonbindingReservationSeatsByEvent/TwoNonbindingReservationsWithSameEventAndUser.csv',
        );

        self::assertSame(2, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresSeatFromOtherEvents(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/countNonbindingReservationSeatsByEvent/TwoNonbindingReservationsWithSameEventAndUser.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(2));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventSumsUpMultiSeatReservations(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/countNonbindingReservationSeatsByEvent/TwoMultiSeatNonbindingReservationsWithSameEvent.csv',
        );

        self::assertSame(5, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresHiddenReservation(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/countNonbindingReservationSeatsByEvent/HiddenNonbindingReservationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresDeletedReservation(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/countNonbindingReservationSeatsByEvent/DeletedNonbindingReservationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresRegularRegistration(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countNonbindingReservationSeatsByEvent/RegularRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresWaitingListRegistration(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/countNonbindingReservationSeatsByEvent/WaitingListRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventForNoDataReturnsEmptyArray(): void
    {
        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventFindsRegularRegistrationsForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/RegularRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertCount(1, $result);
        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventIgnoresWaitingListRegistrationsForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/WaitingListRegistrationWithEvent.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventIgnoresNonbindingReservationForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/NonbindingReservationWithEvent.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventFindsRegistrationsOnAnyPage(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/RegistrationWithEventAndUserOnPage.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertCount(1, $result);
        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventIgnoresRegistrationsForDifferentEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/RegularRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(2);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventIgnoresHiddenRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/HiddenRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsByEventIgnoresDeletedRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/DeletedRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findRegularRegistrationsOrdersByCreationDateNewestFirst(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findRegularRegistrationsByEvent/TwoRegistrationsWithSameEventAndUser.csv',
        );

        $result = $this->subject->findRegularRegistrationsByEvent(1);

        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
        self::assertSame(2, $firstRegistration->getUid());
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventForNoDataReturnsEmptyArray(): void
    {
        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventFindsWaitingListRegistrationsForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/WaitingListRegistrationWithEvent.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertCount(1, $result);
        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventIgnoresRegularRegistrationsForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/RegularRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventIgnoresNonbindingReservationForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/NonbindingReservationWithEvent.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventFindsRegistrationsOnAnyPage(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/WaitingListRegistrationWithEventAndUserOnPage.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertCount(1, $result);
        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventIgnoresRegistrationsForDifferentEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/WaitingListRegistrationWithEvent.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(2);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventIgnoresHiddenRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/HiddenWaitingListRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventIgnoresDeletedRegistrations(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findWaitingListRegistrationsByEvent/DeletedWaitingListRegistrationWithEvent.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findWaitingListRegistrationsByEventOrdersByCreationDateNewestFirst(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/findWaitingListRegistrationsByEvent/TwoWaitingListRegistrationsWithSameEventAndUser.csv',
        );

        $result = $this->subject->findWaitingListRegistrationsByEvent(1);

        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
        self::assertSame(2, $firstRegistration->getUid());
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventForNoDataReturnsEmptyArray(): void
    {
        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventFindsNonbindingReservationForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/NonbindingReservationWithEvent.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertCount(1, $result);
        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventIgnoresRegularRegistrationsForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/RegularRegistrationWithEventAndUser.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventIgnoresWaitingListRegistrationForTheGivenEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/WaitingListRegistrationWithEvent.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventFindsReservationOnAnyPage(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/NonbindingReservationWithEventAndUserOnPage.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertCount(1, $result);
        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventIgnoresNonbindingReservationForDifferentEvent(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/NonbindingReservationWithEvent.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(2);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventIgnoresHiddenReservation(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/HiddenNonbindingReservationWithEventAndUser.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventIgnoresDeletedReservation(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findNonbindingReservationsByEvent/DeletedNonbindingReservationWithEvent.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function findNonbindingReservationsByEventOrdersByCreationDateNewestFirst(): void
    {
        $this->importCSVDataSet(
            __DIR__
            . '/Fixtures/findNonbindingReservationsByEvent/TwoNonbindingReservationsWithSameEventAndUser.csv',
        );

        $result = $this->subject->findNonbindingReservationsByEvent(1);

        $firstRegistration = $result[0];
        self::assertInstanceOf(Registration::class, $firstRegistration);
        self::assertSame(2, $firstRegistration->getUid());
    }

    /**
     * @test
     */
    public function enrichWithRawDataCanBeCalledWithEmptyArray(): void
    {
        $events = [];

        $this->subject->enrichWithRawData($events);

        self::assertSame([], $events);
    }

    /**
     * @test
     */
    public function enrichWithRawDataAddsRawDataToRegistrations(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/enrichWithRawData/RegistrationWithAllFields.csv');
        $registration = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $registration);
        $registrations = [$registration];

        $this->subject->enrichWithRawData($registrations);

        $rawData = $registration->getRawData();
        self::assertIsArray($rawData);
        self::assertSame(1, $rawData['uid']);
        self::assertSame('some new registration', $rawData['title']);
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfExistingVisibleRegistrationMarksRegistrationAsDeleted(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/RegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(1);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/DeletedRegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfExistingHiddenRegistrationMarksRegistrationAsDeleted(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/HiddenRegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(1);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/DeletedHiddenRegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfInexistentRegistrationKeepsOtherVisibleRegistrationsUnchanged(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/RegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(2);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/RegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfExistingDeletedRegistrationKeepsRegistrationDeleted(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/DeletedRegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(1);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/deleteViaDataHandler/DeletedRegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserCreatesResultWithRegistrations(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/RegularRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        $firstResult = $result[0] ?? null;
        self::assertInstanceOf(Registration::class, $firstResult);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsActiveRegularRegistrationWithMatchingUser(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/RegularRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsRegistrationsOnAnyPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/RegistrationOnPage.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserIgnoresRegistrationsWithDifferentUser(): void
    {
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/findActiveRegistrationsByUser/RegistrationFromDifferentUser.csv',
        );

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserIgnoresHiddenRegistration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/HiddenRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserIgnoresDeletedRegistrations(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/DeletedRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsWaitingListRegistrations(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/WaitingListRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsNonbindingReservations(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/NonbindingReservation.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserSortsByRegistrationUidInDescendingOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/findActiveRegistrationsByUser/RegistrationsForTwoEvents.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(2, $result);
        $firstResult = $result[0];
        self::assertInstanceOf(Registration::class, $firstResult);
        self::assertSame(2, $firstResult->getUid());
    }
}
