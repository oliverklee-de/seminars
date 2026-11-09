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
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
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
    private const FIXTURES_PATH = __DIR__ . '/Fixtures';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/BackEndUser.csv');
        $GLOBALS['LANG'] = $this
            ->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithAllFields.csv');

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
        self::assertSame('James Bond', $result->getUserAgent());
    }

    /**
     * @test
     */
    public function mapsAllModelFieldsFromTheAttendeesTrait(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithAllFields.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithAllFields.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithAllFields.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithoutData.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getInvoiceNumber());
    }

    /**
     * @test
     */
    public function collmexCustomerNumberDefaultsToNull(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithoutData.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getCustomerNumber());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findAll/RegistrationOnPage.csv');

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

        $this->assertCSVDataSet(__DIR__ . '/Assertions/persistence/Registration.csv');
    }

    /**
     * @test
     */
    public function mapsEventAssociationWithSingleEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithSingleEvent.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(SingleEvent::class, $result->getEvent());
    }

    /**
     * @test
     */
    public function mapsDeletedEventAssociationAsNull(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithDeletedEvent.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getEvent());
    }

    /**
     * @test
     */
    public function mapsEventAssociationWithEventDate(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithEventDate.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(EventDate::class, $result->getEvent());
    }

    /**
     * @test
     */
    public function mapsUserAssociation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithUser.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(FrontendUser::class, $result->getUser());
    }

    /**
     * @test
     */
    public function mapsDeletedUserAssociationAsNull(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithDeletedUser.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getUser());
    }

    /**
     * @test
     */
    public function mapsAdditionalPersonsAssociation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithAdditionalPerson.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithAccommodationOption.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithFoodOption.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithRegistrationCheckbox.csv');

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithPaymentMethod.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertInstanceOf(PaymentMethod::class, $result->getPaymentMethod());
    }

    /**
     * @test
     */
    public function mapsDeletedPaymentMethodAssociationAsNull(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/RegistrationWithDeletedPaymentMethod.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(Registration::class, $result);

        self::assertNull($result->getPaymentMethod());
    }

    /**
     * @test
     */
    public function findOneByUidIncludingHiddenForNoMatchReturnsNull(): void
    {
        $result = $this->subject->findOneByUidIncludingHidden(1);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function findOneByUidIncludingHiddenCanFindVisibleRecord(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findOneByUidIncludingHidden/VisibleRegistration.csv');

        $result = $this->subject->findOneByUidIncludingHidden(1);

        self::assertInstanceOf(Registration::class, $result);
    }

    /**
     * @test
     */
    public function findOneByUidIncludingHiddenCanFindRecordOnPage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findOneByUidIncludingHidden/RegistrationOnPage.csv');

        $result = $this->subject->findOneByUidIncludingHidden(1);

        self::assertInstanceOf(Registration::class, $result);
    }

    /**
     * @test
     */
    public function findOneByUidIncludingHiddenCanFindHiddenRecord(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findOneByUidIncludingHidden/HiddenRegistration.csv');

        $result = $this->subject->findOneByUidIncludingHidden(1);

        self::assertInstanceOf(Registration::class, $result);
    }

    /**
     * @test
     */
    public function findOneByUidIncludingHiddenIgnoresDeletedRecord(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findOneByUidIncludingHidden/DeletedRegistration.csv');

        $result = $this->subject->findOneByUidIncludingHidden(1);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function existsRegistrationForEventAndUserForZeroUserUidReturnsFalse(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/existsRegistrationForEventAndUser/RegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/existsRegistrationForEventAndUser/RegistrationWithEventAndUser.csv',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 2));
    }

    /**
     * @test
     */
    public function existsRegistrationForOtherEventAndThisUserUidReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/RegistrationWithEventAndUserAndAdditionalEvent.csv');
        $event = $this->eventRepository->findByUid(2);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationForHiddenMatchingRegistrationReturnsFalse(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/existsRegistrationForEventAndUser/HiddenRegistrationWithEventAndUser.csv',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertFalse($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationForDeletedMatchingRegistrationReturnsFalse(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/existsRegistrationForEventAndUser/DeletedRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/existsRegistrationForEventAndUser/RegistrationWithEventAndUser.csv',
        );
        $event = $this->eventRepository->findByUid(1);

        self::assertTrue($this->subject->existsRegistrationForEventAndUser($event, 1));
    }

    /**
     * @test
     */
    public function existsRegistrationTwoMatchingRegistrationsReturnsTrue(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/existsRegistrationForEventAndUser/TwoRegistrationsWithSameEventAndUser.csv',
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularRegistrationsByPageUid/RegistrationOnPage.csv');

        self::assertSame(1, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresRegistrationsOnOtherPage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularRegistrationsByPageUid/RegistrationOnPage.csv');

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(2));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresWaitingListRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularRegistrationsByPageUid/WaitingListRegistrationOnPage.csv',
        );

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresNonbindingReservationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularRegistrationsByPageUid/NonbindingReservationOnPage.csv',
        );

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresHiddenRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularRegistrationsByPageUid/HiddenRegistrationOnPage.csv',
        );

        self::assertSame(0, $this->subject->countRegularRegistrationsByPageUid(1));
    }

    /**
     * @test
     */
    public function countRegularRegistrationsByPageUidIgnoresDeletedRegistrationsOnTheGivenPage(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularRegistrationsByPageUid/DeletedRegistrationOnPage.csv',
        );

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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularSeatsByEvent/RegularRegistrationWithZeroSeats.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventSumsUpSingleSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularSeatsByEvent/TwoRegistrationsWithSameEventAndUser.csv',
        );

        self::assertSame(2, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresSeatFromOtherEvents(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularSeatsByEvent/RegistrationWithEventAndAdditionalEvent.csv',
        );

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(2));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventSumsUpMultiSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countRegularSeatsByEvent/TwoMultiSeatRegistrationsWithSameEvent.csv',
        );

        self::assertSame(5, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresHiddenRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularSeatsByEvent/HiddenRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresDeletedRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularSeatsByEvent/DeletedRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresWaitingListRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularSeatsByEvent/WaitingListRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countRegularSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countRegularSeatsByEventIgnoresNonbindingReservation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countRegularSeatsByEvent/NonbindingReservationWithEvent.csv');

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
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/WaitingListRegistrationWithZeroSeats.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventSumsUpSingleSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/TwoWaitingListRegistrationsWithSameEventAndUser.csv',
        );

        self::assertSame(2, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresSeatFromOtherEvents(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/TwoWaitingListRegistrationsWithSameEventAndUser.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(2));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventSumsUpMultiSeatRegistrations(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/TwoMultiSeatWaitingListRegistrationsWithSameEvent.csv',
        );

        self::assertSame(5, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresHiddenRegistration(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/HiddenWaitingListRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresDeletedRegistration(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/DeletedWaitingListRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresRegularRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/RegularRegistrationWithEvent.csv');

        self::assertSame(0, $this->subject->countWaitingListSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countWaitingListSeatsByEventIgnoresNonbindingReservation(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countWaitingListSeatsByEvent/NonbindingReservationWithEvent.csv',
        );

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
            self::FIXTURES_PATH . '/countNonbindingReservationSeatsByEvent/NonbindingReservationWithZeroSeats.csv',
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
            self::FIXTURES_PATH . '/countNonbindingReservationSeatsByEvent/RegularRegistrationWithEvent.csv',
        );

        self::assertSame(0, $this->subject->countNonbindingReservationSeatsByEvent(1));
    }

    /**
     * @test
     */
    public function countNonbindingReservationSeatsByEventIgnoresWaitingListRegistration(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/countNonbindingReservationSeatsByEvent/WaitingListRegistrationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/RegularRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/WaitingListRegistrationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/NonbindingReservationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/RegistrationWithEventAndUserOnPage.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/RegularRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/HiddenRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/DeletedRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findRegularRegistrationsByEvent/TwoRegistrationsWithSameEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findWaitingListRegistrationsByEvent/WaitingListRegistrationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findWaitingListRegistrationsByEvent/RegularRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findWaitingListRegistrationsByEvent/NonbindingReservationWithEvent.csv',
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
            self::FIXTURES_PATH
            . '/findWaitingListRegistrationsByEvent/WaitingListRegistrationWithEventAndUserOnPage.csv',
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
            self::FIXTURES_PATH . '/findWaitingListRegistrationsByEvent/WaitingListRegistrationWithEvent.csv',
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
            self::FIXTURES_PATH
            . '/findWaitingListRegistrationsByEvent/HiddenWaitingListRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findWaitingListRegistrationsByEvent/DeletedWaitingListRegistrationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/NonbindingReservationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/RegularRegistrationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/WaitingListRegistrationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/NonbindingReservationWithEventAndUserOnPage.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/NonbindingReservationWithEvent.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/HiddenNonbindingReservationWithEventAndUser.csv',
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
            self::FIXTURES_PATH . '/findNonbindingReservationsByEvent/DeletedNonbindingReservationWithEvent.csv',
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/enrichWithRawData/RegistrationWithAllFields.csv');
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/deleteViaDataHandler/RegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(1);

        $this->assertCSVDataSet(__DIR__ . '/Assertions/deleteViaDataHandler/DeletedRegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfExistingHiddenRegistrationMarksRegistrationAsDeleted(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(self::FIXTURES_PATH . '/deleteViaDataHandler/HiddenRegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(1);

        $this->assertCSVDataSet(__DIR__ . '/Assertions/deleteViaDataHandler/DeletedHiddenRegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfInexistentRegistrationKeepsOtherVisibleRegistrationsUnchanged(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(self::FIXTURES_PATH . '/deleteViaDataHandler/RegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(2);

        $this->assertCSVDataSet(__DIR__ . '/Assertions/deleteViaDataHandler/RegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function deleteViaDataHandlerWithUidOfExistingDeletedRegistrationKeepsRegistrationDeleted(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(self::FIXTURES_PATH . '/deleteViaDataHandler/DeletedRegistrationOnPage.csv');

        $this->subject->deleteViaDataHandler(1);

        $this->assertCSVDataSet(__DIR__ . '/Assertions/deleteViaDataHandler/DeletedRegistrationOnPage.csv');
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserCreatesResultWithRegistrations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/RegularRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        $firstResult = $result[0] ?? null;
        self::assertInstanceOf(Registration::class, $firstResult);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsActiveRegularRegistrationWithMatchingUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/RegularRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsRegistrationsOnAnyPage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/RegistrationOnPage.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserIgnoresRegistrationsWithDifferentUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/findActiveRegistrationsByUser/RegistrationFromDifferentUser.csv',
        );

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserIgnoresHiddenRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/HiddenRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserIgnoresDeletedRegistrations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/DeletedRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsWaitingListRegistrations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/WaitingListRegistration.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserFindsNonbindingReservations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/NonbindingReservation.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function findActiveRegistrationsByUserSortsByRegistrationUidInDescendingOrder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findActiveRegistrationsByUser/RegistrationsForTwoEvents.csv');

        $result = $this->subject->findActiveRegistrationsByUser(1);

        self::assertCount(2, $result);
        $firstResult = $result[0];
        self::assertInstanceOf(Registration::class, $firstResult);
        self::assertSame(2, $firstResult->getUid());
    }
}
