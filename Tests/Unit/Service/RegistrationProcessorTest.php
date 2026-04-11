<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Service;

use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\FrontendUserRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Service\RegistrationGuard;
use OliverKlee\Seminars\Service\RegistrationManager;
use OliverKlee\Seminars\Service\RegistrationProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\RegistrationProcessor
 */
final class RegistrationProcessorTest extends UnitTestCase
{
    /**
     * @var RegistrationRepository&MockObject
     */
    private RegistrationRepository $registrationRepositoryMock;

    /**
     * @var EventRepository&MockObject
     */
    private EventRepository $eventRepositoryMock;

    /**
     * @var FrontendUserRepository&MockObject
     */
    private FrontendUserRepository $frontendUserRepositoryMock;

    /**
     * @var RegistrationGuard&MockObject
     */
    private RegistrationGuard $registrationGuardMock;

    /**
     * @var RegistrationManager&MockObject
     */
    private RegistrationManager $registrationManagerMock;

    private RegistrationProcessor $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrationRepositoryMock = $this->createMock(RegistrationRepository::class);
        $this->eventRepositoryMock = $this->createMock(EventRepository::class);
        $this->frontendUserRepositoryMock = $this->createMock(FrontendUserRepository::class);
        $this->registrationGuardMock = $this->createMock(RegistrationGuard::class);
        $this->registrationManagerMock = $this->createMock(RegistrationManager::class);

        $this->subject = new RegistrationProcessor(
            $this->registrationRepositoryMock,
            $this->eventRepositoryMock,
            $this->frontendUserRepositoryMock,
            $this->registrationGuardMock,
            $this->registrationManagerMock,
        );
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function isSingleton(): void
    {
        self::assertInstanceOf(SingletonInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function enrichWithMetadataSetsEvent(): void
    {
        $event = new SingleEvent();
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertSame($event, $registration->getEvent());
    }

    /**
     * @test
     */
    public function enrichWithMetadataSetsUserFromSession(): void
    {
        $userUid = 99;
        $registration = new Registration();
        $user = new FrontendUser();
        $this->registrationGuardMock
            ->expects(self::once())->method('getFrontEndUserUidFromSession')
            ->willReturn($userUid);
        $this->frontendUserRepositoryMock
            ->expects(self::once())->method('findByUid')
            ->with($userUid)->willReturn($user);

        $this->subject->enrichWithMetadata($registration, new SingleEvent(), []);

        self::assertSame($user, $registration->getUser());
    }

    /**
     * @test
     */
    public function enrichWithMetadataWithoutUserUidInSessionThrowsException(): void
    {
        $registration = new Registration();
        $this->registrationGuardMock
            ->expects(self::once())->method('getFrontEndUserUidFromSession')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1668865776);
        $this->expectExceptionMessage('No user UID found in the session.');

        $this->subject->enrichWithMetadata($registration, new SingleEvent(), []);
    }

    /**
     * @test
     */
    public function enrichWithMetadataWithUserUidInSessionForInexistentUserThrowsException(): void
    {
        $userUid = 99;
        $registration = new Registration();
        $this->registrationGuardMock
            ->expects(self::once())->method('getFrontEndUserUidFromSession')
            ->willReturn($userUid);
        $this->frontendUserRepositoryMock
            ->expects(self::once())->method('findByUid')
            ->with($userUid)->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1668865839);
        $this->expectExceptionMessage('User with UID ' . $userUid . ' not found.');

        $this->subject->enrichWithMetadata($registration, new SingleEvent(), []);
    }

    /**
     * @test
     */
    public function enrichWithMetadataSetsFolderFromConfiguration(): void
    {
        $folderUid = 21;
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata(
            $registration,
            new SingleEvent(),
            ['registrationRecordsStorageFolder' => (string)$folderUid],
        );

        self::assertSame($folderUid, $registration->getPid());
    }

    /**
     * @test
     */
    public function calculateTotalPriceForRegistrationWithoutEventThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1669023165);
        $this->expectExceptionMessage('The registration has no associated event.');

        $this->subject->calculateTotalPrice(new Registration());
    }

    /**
     * @return array<string, array{0: Price::PRICE_*, 1: positive-int, 2: float}>
     */
    public function priceCalculationDataProvider(): array
    {
        return [
            '1 seat, standard price' => [Price::PRICE_STANDARD, 1, 100.0],
            '2 seats, standard price' => [Price::PRICE_STANDARD, 2, 200.0],
            '1 seat, early-bird price' => [Price::PRICE_EARLY_BIRD, 1, 90.0],
            '2 seats, early-bird price' => [Price::PRICE_EARLY_BIRD, 2, 180.0],
            '1 seat, special price' => [Price::PRICE_SPECIAL, 1, 80.0],
            '2 seats, special price' => [Price::PRICE_SPECIAL, 2, 160.0],
            '1 seat, special early-bird price' => [Price::PRICE_SPECIAL_EARLY_BIRD, 1, 70.0],
            '2 seats, special early-bird price' => [Price::PRICE_SPECIAL_EARLY_BIRD, 2, 140.0],
        ];
    }

    /**
     * @test
     *
     * @param Price::PRICE_* $priceCode
     * @param positive-int $seats
     *
     * @dataProvider priceCalculationDataProvider
     */
    public function calculateTotalPriceSetsTotalPriceToProductOfSeatsAndSelectedPriceAmount(
        string $priceCode,
        int $seats,
        float $expectedAmount
    ): void {
        $event = new SingleEvent();
        $event->setStandardPrice(100.0);
        $event->setEarlyBirdPrice(90.0);
        $event->setSpecialPrice(80.0);
        $event->setSpecialEarlyBirdPrice(70.0);

        $registration = new Registration();
        $registration->setEvent($event);
        $registration->setSeats($seats);
        $registration->setPriceCode($priceCode);

        $this->subject->calculateTotalPrice($registration);

        self::assertSame($expectedAmount, $registration->getTotalPrice());
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithFullyFilledUserDataAndZeroStorageAddsNoAttendees(): void
    {
        $registration = new Registration();
        $json = \json_encode([['name' => 'Baba Doe', 'email' => 'baba@example.com']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 0);

        self::assertEmpty($registration->getAdditionalPersons());
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithInvalidJsonAddsNoAttendees(): void
    {
        $registration = new Registration();
        $registration->setJsonEncodedAdditionAttendees('[} Du nicht nehmen Kerze!');

        $this->subject->createAdditionalPersons($registration, 17);

        self::assertEmpty($registration->getAdditionalPersons());
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithNonArraySingleDataUserAddsNoAttendees(): void
    {
        $registration = new Registration();
        $json = \json_encode(['Du nicht nehmen Kerze!']);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 17);

        self::assertEmpty($registration->getAdditionalPersons());
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithNonArrayJsonAddsNoAttendees(): void
    {
        $registration = new Registration();
        $json = \json_encode('Du nicht nehmen Kerze!');
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 17);

        self::assertEmpty($registration->getAdditionalPersons());
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithFullyFilledUserDataAndPositiveStorageAddsAttendee(): void
    {
        $registration = new Registration();
        $json = \json_encode([['name' => 'Baba Doe', 'email' => 'baba@example.com']]);
        $registration->setJsonEncodedAdditionAttendees($json);
        $this->subject->createAdditionalPersons($registration, 17);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
        }
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithMultipleDataSetsAddsMultipleAttendees(): void
    {
        $registration = new Registration();
        $json = \json_encode(
            [
                ['name' => 'Baba Doe', 'email' => 'baba@example.com'],
                ['name' => 'Boba Doe', 'email' => 'boba@example.com'],
            ],
        );
        $registration->setJsonEncodedAdditionAttendees($json);
        $this->subject->createAdditionalPersons($registration, 17);

        $result = $registration->getAdditionalPersons();
        self::assertCount(2, $result);
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithFullyFilledUserDataAndPositiveStorageAddsAttendeeWithGivenPid(): void
    {
        $registration = new Registration();
        $json = \json_encode([['name' => 'Baba Doe', 'email' => 'baba@example.com']]);
        $registration->setJsonEncodedAdditionAttendees($json);
        $storagePid = 17;

        $this->subject->createAdditionalPersons($registration, $storagePid);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
            self::assertSame($storagePid, $attendee->getPid());
        }
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithFullyFilledUserDataAndPositiveStorageAddsAttendeeWithName(): void
    {
        $registration = new Registration();
        $name = 'Baba Doe';
        $json = \json_encode([['name' => $name, 'email' => 'baba@example.com']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
            self::assertSame($name, $attendee->getName());
        }
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithNameAndEmptyEmailAddsAttendeeWithName(): void
    {
        $registration = new Registration();
        $name = 'Baba Doe';
        $json = \json_encode([['name' => $name, 'email' => '']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
            self::assertSame($name, $attendee->getName());
        }
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithNameAndMissingEmailAddsAttendeeWithName(): void
    {
        $registration = new Registration();
        $name = 'Baba Doe';
        $json = \json_encode([['name' => $name, 'email' => '']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
            self::assertSame($name, $attendee->getName());
        }
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithEmptyNameAndNonEmptyEmailNotAddsAttendee(): void
    {
        $registration = new Registration();
        $json = \json_encode([['name' => '', 'email' => 'boba@example.com']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithoutNameAndWithNonEmptyEmailNotAddsAttendee(): void
    {
        $registration = new Registration();
        $json = \json_encode([['email' => 'boba@example.com']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithEmptyUserDataNotAddsAttendee(): void
    {
        $registration = new Registration();
        $json = \json_encode([[]]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithoutUserDataNotAddsAttendee(): void
    {
        $registration = new Registration();
        $json = \json_encode([]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithFullyFilledUserDataAddsAttendeeWithEmail(): void
    {
        $registration = new Registration();
        $email = 'baba@example.com';
        $json = \json_encode([['name' => 'Baba Doe', 'email' => $email]]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
            self::assertSame($email, $attendee->getEmail());
        }
    }

    /**
     * @test
     */
    public function createAdditionalPersonsWithCreatesRandomUserName(): void
    {
        $registration = new Registration();
        $json = \json_encode([['name' => 'Baba Doe']]);
        $registration->setJsonEncodedAdditionAttendees($json);

        $this->subject->createAdditionalPersons($registration, 1);

        $result = $registration->getAdditionalPersons();
        self::assertCount(1, $result);
        foreach ($result as $attendee) {
            self::assertInstanceOf(FrontendUser::class, $attendee);
            self::assertMatchesRegularExpression('/^additional-attendee-[\\da-f]{32}$/', $attendee->getUsername());
        }
    }

    /**
     * @test
     */
    public function persistForRegistationWithoutEventThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1669023165);
        $this->expectExceptionMessage('The registration has no associated event.');

        $this->subject->persist(new Registration());
    }

    /**
     * @test
     */
    public function persistPersistsRegistration(): void
    {
        $registration = new Registration();
        $registration->setEvent(new SingleEvent());

        $this->registrationRepositoryMock->expects(self::once())->method('add')->with($registration);
        $this->registrationRepositoryMock->expects(self::once())->method('persistAll');

        $this->subject->persist($registration);
    }

    /**
     * @test
     */
    public function persistUpdateEventRegistrationsCounterCache(): void
    {
        $event = new SingleEvent();
        $registration = new Registration();
        $registration->setEvent($event);

        $this->eventRepositoryMock->expects(self::once())->method('updateRegistrationCounterCache')->with($event);

        $this->subject->persist($registration);
    }

    /**
     * @test
     */
    public function sendEmailsForRegistrationWithoutUidThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1668939288);
        $this->expectExceptionMessage('The registration has not been persisted yet.');

        $this->subject->sendEmails(new Registration());
    }

    /**
     * @test
     */
    public function sendEmailsForRegistrationWithZeroUidThrowsException(): void
    {
        $registration = $this->createMock(Registration::class);
        $registration->method('getUid')->willReturn(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1668939288);
        $this->expectExceptionMessage('The registration has not been persisted yet.');

        $this->subject->sendEmails($registration);
    }

    /**
     * @test
     */
    public function sendEmailsSendsEmailsWithLegacyRegistrationAndLegacyConfiguration(): void
    {
        $registrationUid = 15;
        $registration = $this->createMock(Registration::class);
        $registration->method('getUid')->willReturn($registrationUid);

        $legacyRegistrationMock = $this->createMock(LegacyRegistration::class);
        GeneralUtility::addInstance(LegacyRegistration::class, $legacyRegistrationMock);

        $this->registrationManagerMock
            ->expects(self::once())->method('sendEmailsForNewRegistration')
            ->with($legacyRegistrationMock);

        $this->subject->sendEmails($registration);
    }

    /**
     * @test
     */
    public function enrichWithForEventWithVacanciesWithoutWaitingListKeepsRegistrationRegular(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(false);
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->registrationGuardMock->method('getVacancies')->with($event)->willReturn(1);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertFalse($registration->isOnWaitingList());
    }

    /**
     * @test
     */
    public function enrichWithForEventWithUnlimitedVacanciesWithoutWaitingListKeepsRegistrationRegular(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(false);
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->registrationGuardMock->method('getVacancies')->with($event)->willReturn(null);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertFalse($registration->isOnWaitingList());
    }

    /**
     * @test
     *
     * In the future, we should catch this case and redirect to the previous page (or the error page).
     */
    public function enrichWithForEventWithZeroVacanciesWithoutWaitingListKeepsRegistrationRegular(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(false);
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->registrationGuardMock->method('getVacancies')->with($event)->willReturn(0);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertFalse($registration->isOnWaitingList());
    }

    /**
     * @test
     */
    public function enrichWithForEventWithVacanciesWithWaitingListKeepsRegistrationRegular(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(true);
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->registrationGuardMock->method('getVacancies')->with($event)->willReturn(1);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertFalse($registration->isOnWaitingList());
    }

    /**
     * @test
     */
    public function enrichWithForEventWithUnlimitedVacanciesWithWaitingListKeepsRegistrationRegular(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(true);
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->registrationGuardMock->method('getVacancies')->with($event)->willReturn(null);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertFalse($registration->isOnWaitingList());
    }

    /**
     * @test
     *
     * In the future, we should catch this case and redirect to the previous page (or the error page).
     */
    public function enrichWithForEventWithZeroVacanciesWithWaitingListMovesRegistrationToWaitingList(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(true);
        $registration = new Registration();
        $this->registrationGuardMock->method('getFrontEndUserUidFromSession')->willReturn(15);
        $this->registrationGuardMock->method('getVacancies')->with($event)->willReturn(0);
        $this->frontendUserRepositoryMock->method('findByUid')->with(self::anything())->willReturn(new FrontendUser());

        $this->subject->enrichWithMetadata($registration, $event, []);

        self::assertTrue($registration->isOnWaitingList());
    }
}
