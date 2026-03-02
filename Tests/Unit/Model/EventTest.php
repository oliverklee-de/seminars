<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Model;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Model\Category;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\EventType;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Organizer;
use OliverKlee\Seminars\Model\Registration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\Model\Event
 */
final class EventTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private Event $subject;

    private DummyConfiguration $configuration;

    protected int $now = 1424751343;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $configurationRegistry = ConfigurationRegistry::getInstance();
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);

        $this->subject = new Event();
    }

    protected function tearDown(): void
    {
        ConfigurationRegistry::purgeInstance();
        parent::tearDown();
    }

    ///////////////////////////////////
    // Tests regarding isEventDate().
    ///////////////////////////////////

    /**
     * @test
     */
    public function isEventDateForSingleRecordReturnsFalse(): void
    {
        $this->subject->setData(
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        self::assertFalse(
            $this->subject->isEventDate(),
        );
    }

    /**
     * @test
     */
    public function isEventDateForTopicRecordReturnsFalse(): void
    {
        $this->subject->setData(
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );

        self::assertFalse(
            $this->subject->isEventDate(),
        );
    }

    /**
     * @test
     */
    public function isEventDateForDateRecordWithTopicReturnsTrue(): void
    {
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => new Event(),
            ],
        );

        self::assertTrue(
            $this->subject->isEventDate(),
        );
    }

    /**
     * @test
     */
    public function isEventDateForDateRecordWithoutTopicReturnsFalse(): void
    {
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => null,
            ],
        );

        self::assertFalse(
            $this->subject->isEventDate(),
        );
    }

    ////////////////////////////////
    // Tests concerning the title.
    ////////////////////////////////

    /**
     * @test
     */
    public function getTitleWithNonEmptyTitleReturnsTitle(): void
    {
        $this->subject->setData(['title' => 'Superhero']);

        self::assertSame(
            'Superhero',
            $this->subject->getTitle(),
        );
    }

    /**
     * @test
     */
    public function getRawTitleWithNonEmptyTitleReturnsTitle(): void
    {
        $this->subject->setData(['title' => 'Superhero']);

        self::assertSame(
            'Superhero',
            $this->subject->getRawTitle(),
        );
    }

    ///////////////////////////////////////////////
    // Tests regarding the registration deadline.
    ///////////////////////////////////////////////

    /**
     * @test
     */
    public function getRegistrationDeadlineAsUnixTimeStampWithoutRegistrationDeadlineReturnsZero(): void
    {
        $this->subject->setData([]);

        self::assertSame(
            0,
            $this->subject->getRegistrationDeadlineAsUnixTimeStamp(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationDeadlineAsUnixTimeStampWithPositiveRegistrationDeadlineReturnsRegistrationDeadline(
    ): void {
        $this->subject->setData(['deadline_registration' => 42]);

        self::assertSame(
            42,
            $this->subject->getRegistrationDeadlineAsUnixTimeStamp(),
        );
    }

    /**
     * @test
     */
    public function hasRegistrationDeadlineWithoutRegistrationDeadlineReturnsFalse(): void
    {
        $this->subject->setData([]);

        self::assertFalse(
            $this->subject->hasRegistrationDeadline(),
        );
    }

    /**
     * @test
     */
    public function hasRegistrationDeadlineWithRegistrationDeadlineReturnsTrue(): void
    {
        $this->subject->setData(['deadline_registration' => 42]);

        self::assertTrue(
            $this->subject->hasRegistrationDeadline(),
        );
    }

    //////////////////////////////////////
    // Tests regarding the details page.
    //////////////////////////////////////

    /**
     * @test
     */
    public function getDetailsPageWithoutDetailsPageReturnsEmptyString(): void
    {
        $this->subject->setData([]);

        self::assertSame(
            '',
            $this->subject->getDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function getDetailsPageWithDetailsPageReturnsDetailsPage(): void
    {
        $this->subject->setData(['details_page' => 'https://example.com']);

        self::assertSame(
            'https://example.com',
            $this->subject->getDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function hasDetailsPageWithoutDetailsPageReturnsFalse(): void
    {
        $this->subject->setData([]);

        self::assertFalse(
            $this->subject->hasDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function hasDetailsPageWithDetailsPageReturnsTrue(): void
    {
        $this->subject->setData(['details_page' => 'https://example.com']);

        self::assertTrue(
            $this->subject->hasDetailsPage(),
        );
    }

    ///////////////////////////////////////////////////
    // Tests concerning the combined single view page
    ///////////////////////////////////////////////////

    /**
     * @test
     */
    public function getCombinedSingleViewPageInitiallyReturnsEmptyString(): void
    {
        $this->subject->setData(['categories' => new Collection()]);

        self::assertSame(
            '',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForAvailableDetailsPageUidReturnsTheDetailsPageUid(): void
    {
        $this->subject->setData(
            [
                'details_page' => '5',
                'categories' => new Collection(),
            ],
        );

        self::assertSame(
            '5',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForAvailableDetailsPageUrlReturnsTheDetailsPageUrl(): void
    {
        $this->subject->setData(
            [
                'details_page' => 'www.example.com',
                'categories' => new Collection(),
            ],
        );

        self::assertSame(
            'www.example.com',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForAvailableEventTypeWithoutSingleViewPageReturnsEmptyString(): void
    {
        $eventType = new EventType();
        $eventType->setData([]);
        $this->subject->setData(
            [
                'event_type' => $eventType,
                'categories' => new Collection(),
            ],
        );

        self::assertSame(
            '',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForAvailableEventTypeWithSingleViewPageReturnsSingleViewPageFromEventType(
    ): void {
        $eventType = new EventType();
        $eventType->setData(['single_view_page' => 42]);
        $this->subject->setData(
            [
                'event_type' => $eventType,
                'categories' => new Collection(),
            ],
        );

        self::assertSame(
            '42',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForAvailableCategoryWithoutSingleViewPageReturnsEmptyString(): void
    {
        $category = new Category();
        $category->setData([]);
        $categories = new Collection();
        $categories->add($category);
        $this->subject->setData(['categories' => $categories]);

        self::assertSame(
            '',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForAvailableCategoryTypeWithSingleViewPageReturnsSingleViewPageFromCategory(
    ): void {
        $category = new Category();
        $category->setData(['single_view_page' => 42]);
        $categories = new Collection();
        $categories->add($category);
        $this->subject->setData(['categories' => $categories]);

        self::assertSame(
            '42',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageForTwoAvailableCategoriesWithSingleViewPageReturnsSingleViewPageFromFirstCategory(
    ): void {
        $category1 = new Category();
        $category1->setData(['single_view_page' => 42]);
        $category2 = new Category();
        $category2->setData(['single_view_page' => 12]);
        $categories = new Collection();
        $categories->add($category1);
        $categories->add($category2);
        $this->subject->setData(['categories' => $categories]);

        self::assertSame(
            '42',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function hasCombinedSingleViewPageForEmptySingleViewPageReturnsFalse(): void
    {
        $subject = $this->createPartialMock(
            Event::class,
            ['getCombinedSingleViewPage'],
        );
        $subject
            ->expects(self::atLeastOnce())
            ->method('getCombinedSingleViewPage')->willReturn('');

        self::assertFalse(
            $subject->hasCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function hasCombinedSingleViewPageForNonEmptySingleViewPageReturnsTrue(): void
    {
        $subject = $this->createPartialMock(
            Event::class,
            ['getCombinedSingleViewPage'],
        );
        $subject
            ->expects(self::atLeastOnce())
            ->method('getCombinedSingleViewPage')->willReturn('42');

        self::assertTrue(
            $subject->hasCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageUsesDetailsPageInsteadOfEventTypeIfBothAreAvailable(): void
    {
        $eventType = new EventType();
        $eventType->setData(['single_view_page' => 42]);

        $this->subject->setData(
            [
                'details_page' => '5',
                'event_type' => $eventType,
                'categories' => new Collection(),
            ],
        );

        self::assertSame(
            '5',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    /**
     * @test
     */
    public function getCombinedSingleViewPageUsesEventTypeInsteadOfCategoriesIfBothAreAvailable(): void
    {
        $eventType = new EventType();
        $eventType->setData(['single_view_page' => 42]);
        $category = new Category();
        $category->setData(['single_view_page' => 91]);
        $categories = new Collection();
        $categories->add($category);

        $this->subject->setData(
            [
                'event_type' => $eventType,
                'categories' => $categories,
            ],
        );

        self::assertSame(
            '42',
            $this->subject->getCombinedSingleViewPage(),
        );
    }

    ///////////////////////////////////////////
    // Tests regarding the minimum attendees.
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function getMinimumAttendeesWithoutMinimumAttendeesReturnsZero(): void
    {
        $this->subject->setData([]);

        self::assertSame(
            0,
            $this->subject->getMinimumAttendees(),
        );
    }

    /**
     * @test
     */
    public function getMinimumAttendeesWithPositiveMinimumAttendeesReturnsMinimumAttendees(): void
    {
        $this->subject->setData(['attendees_min' => 42]);

        self::assertSame(
            42,
            $this->subject->getMinimumAttendees(),
        );
    }

    ///////////////////////////////////////////
    // Tests regarding the maximum attendees.
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function getMaximumAttendeesWithoutMaximumAttendeesReturnsZero(): void
    {
        $this->subject->setData([]);

        self::assertSame(
            0,
            $this->subject->getMaximumAttendees(),
        );
    }

    /**
     * @test
     */
    public function getMaximumAttendeesWithMaximumAttendeesReturnsMaximumAttendees(): void
    {
        $this->subject->setData(['attendees_max' => 42]);

        self::assertSame(
            42,
            $this->subject->getMaximumAttendees(),
        );
    }

    // Tests regarding the status.

    /**
     * @test
     */
    public function getStatusWithoutStatusReturnsStatusPlanned(): void
    {
        $this->subject->setData([]);

        self::assertSame(
            EventInterface::STATUS_PLANNED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function getStatusWithStatusPlannedReturnsStatusPlanned(): void
    {
        $this->subject->setData(
            ['cancelled' => EventInterface::STATUS_PLANNED],
        );

        self::assertSame(
            EventInterface::STATUS_PLANNED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function getStatusWithStatusCanceledReturnStatusCanceled(): void
    {
        $this->subject->setData(
            ['cancelled' => EventInterface::STATUS_CANCELED],
        );

        self::assertSame(
            EventInterface::STATUS_CANCELED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function getStatusWithStatusConfirmedReturnsStatusConfirmed(): void
    {
        $this->subject->setData(
            ['cancelled' => EventInterface::STATUS_CONFIRMED],
        );

        self::assertSame(
            EventInterface::STATUS_CONFIRMED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function setStatusWithStatusPlannedSetsStatus(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertSame(
            EventInterface::STATUS_PLANNED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function setStatusWithStatusCanceledSetsStatus(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertSame(
            EventInterface::STATUS_CANCELED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function setStatusWithStatusConfirmedSetsStatus(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertSame(
            EventInterface::STATUS_CONFIRMED,
            $this->subject->getStatus(),
        );
    }

    /**
     * @test
     */
    public function isPlannedForPlannedStatusReturnsTrue(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertTrue($this->subject->isPlanned());
    }

    /**
     * @test
     */
    public function isPlannedForCanceledStatusReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertFalse($this->subject->isPlanned());
    }

    /**
     * @test
     */
    public function isPlannedForConfirmedStatusReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertFalse($this->subject->isPlanned());
    }

    /**
     * @test
     */
    public function isCanceledForPlannedStatusReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertFalse($this->subject->isCanceled());
    }

    /**
     * @test
     */
    public function isCanceledForCanceledStatusReturnsTrue(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertTrue($this->subject->isCanceled());
    }

    /**
     * @test
     */
    public function isCanceledForConfirmedStatusReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertFalse($this->subject->isCanceled());
    }

    /**
     * @test
     */
    public function isConfirmedForPlannedStatusReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertFalse($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function isConfirmedForCanceledStatusReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertFalse($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function isConfirmedForConfirmedStatusReturnsTrue(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertTrue($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function cancelCanMakePlannedEventCanceled(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        $this->subject->cancel();

        self::assertTrue($this->subject->isCanceled());
    }

    /**
     * @test
     */
    public function cancelCanMakeConfirmedEventCanceled(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        $this->subject->cancel();

        self::assertTrue($this->subject->isCanceled());
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function cancelForCanceledEventNotThrowsException(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        $this->subject->cancel();
    }

    /**
     * @test
     */
    public function confirmCanMakePlannedEventConfirmed(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        $this->subject->confirm();

        self::assertTrue($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function confirmCanMakeCanceledEventConfirmed(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        $this->subject->confirm();

        self::assertTrue($this->subject->isConfirmed());
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function confirmForConfirmedEventNotThrowsException(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        $this->subject->confirm();
    }

    // Tests concerning the offline registrations

    /**
     * @test
     */
    public function getOfflineRegistrationsForEventWithoutOfflineRegistrationsReturnsZero(): void
    {
        $this->subject->setData(['offline_attendees' => 0]);

        self::assertSame(
            0,
            $this->subject->getOfflineRegistrations(),
        );
    }

    /**
     * @test
     */
    public function getOfflineRegistrationsForEventWithTwoOfflineRegistrationsReturnsTwo(): void
    {
        $this->subject->setData(['offline_attendees' => 2]);

        self::assertSame(
            2,
            $this->subject->getOfflineRegistrations(),
        );
    }

    // Tests concerning the registrations

    /**
     * @test
     */
    public function getRegistrationsReturnsRegistrations(): void
    {
        $registrations = new Collection();

        $this->subject->setData(['registrations' => $registrations]);

        self::assertSame(
            $registrations,
            $this->subject->getRegistrations(),
        );
    }

    /**
     * @test
     */
    public function setRegistrationsSetsRegistrations(): void
    {
        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $this->subject->setRegistrations($registrations);

        self::assertSame(
            $registrations,
            $this->subject->getRegistrations(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationsAfterLastDigestReturnsNewerRegistrations(): void
    {
        $this->subject->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(1);

        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $registration = new Registration();
        $registration->setData(['crdate' => 2]);
        $registrations->add($registration);
        $this->subject->setRegistrations($registrations);

        self::assertContains($registration, $this->subject->getRegistrationsAfterLastDigest());
    }

    /**
     * @test
     */
    public function getRegistrationsAfterLastDigestNotReturnsOlderRegistrations(): void
    {
        $this->subject->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(2);

        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $registration = new Registration();
        $registration->setData(['crdate' => 1]);
        $registrations->add($registration);
        $this->subject->setRegistrations($registrations);

        self::assertTrue($this->subject->getRegistrationsAfterLastDigest()->isEmpty());
    }

    /**
     * @test
     */
    public function getRegistrationsAfterLastDigestNotReturnsRegistrationsExactlyAtDigestDate(): void
    {
        $this->subject->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(1);

        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $registration = new Registration();
        $registration->setData(['crdate' => 1]);
        $registrations->add($registration);
        $this->subject->setRegistrations($registrations);

        self::assertTrue($this->subject->getRegistrationsAfterLastDigest()->isEmpty());
    }

    ////////////////////////////////////////
    // Tests concerning getRegisteredSeats
    ////////////////////////////////////////

    /**
     * @test
     */
    public function getRegisteredSeatsForNoRegularRegistrationsReturnsZero(): void
    {
        $event = $this->createPartialMock(
            Event::class,
            ['getRegularRegistrations'],
        );
        $event->setData([]);
        $event
            ->method('getRegularRegistrations')
            ->willReturn(new Collection());

        self::assertSame(
            0,
            $event->getRegisteredSeats(),
        );
    }

    /**
     * @test
     */
    public function getRegisteredSeatsCountsOfflineRegistrations(): void
    {
        $event = $this->createPartialMock(
            Event::class,
            ['getRegularRegistrations'],
        );
        $event->setData(['offline_attendees' => 2]);
        $event
            ->method('getRegularRegistrations')
            ->willReturn(new Collection());

        self::assertSame(
            2,
            $event->getRegisteredSeats(),
        );
    }

    ////////////////////////////////////////////
    // Tests concerning hasEnoughRegistrations
    ////////////////////////////////////////////

    /**
     * @test
     */
    public function hasEnoughRegistrationsForZeroSeatsAndZeroNeededReturnsTrue(): void
    {
        $event = $this->createPartialMock(
            Event::class,
            ['getRegisteredSeats'],
        );
        $event->setData(['attendees_min' => 0]);
        $event
            ->method('getRegisteredSeats')
            ->willReturn(0);

        self::assertTrue(
            $event->hasEnoughRegistrations(),
        );
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsForLessSeatsThanNeededReturnsFalse(): void
    {
        $event = $this->createPartialMock(
            Event::class,
            ['getRegisteredSeats'],
        );
        $event->setData(['attendees_min' => 2]);
        $event
            ->method('getRegisteredSeats')
            ->willReturn(1);

        self::assertFalse(
            $event->hasEnoughRegistrations(),
        );
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsForAsManySeatsAsNeededReturnsTrue(): void
    {
        $event = $this->createPartialMock(
            Event::class,
            ['getRegisteredSeats'],
        );
        $event->setData(['attendees_min' => 2]);
        $event
            ->method('getRegisteredSeats')
            ->willReturn(2);

        self::assertTrue(
            $event->hasEnoughRegistrations(),
        );
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsForMoreSeatsThanNeededReturnsTrue(): void
    {
        $event = $this->createPartialMock(
            Event::class,
            ['getRegisteredSeats'],
        );
        $event->setData(['attendees_min' => 1]);
        $event
            ->method('getRegisteredSeats')
            ->willReturn(2);

        self::assertTrue(
            $event->hasEnoughRegistrations(),
        );
    }

    // Tests regarding the flag for automatic cancelation/confirmation

    /**
     * @test
     */
    public function shouldAutomaticallyConfirmOrCancelByDefaultReturnsFalse(): void
    {
        $this->subject->setData([]);

        self::assertFalse(
            $this->subject->shouldAutomaticallyConfirmOrCancel(),
        );
    }

    /**
     * @test
     */
    public function shouldAutomaticallyConfirmOrCancelReturnsTrueValueFromDatabase(): void
    {
        $this->subject->setData(
            ['automatic_confirmation_cancelation' => 1],
        );

        self::assertTrue(
            $this->subject->shouldAutomaticallyConfirmOrCancel(),
        );
    }

    // Tests concerning the organizers

    /**
     * @test
     */
    public function getOrganizersGetsOrganizers(): void
    {
        $organizers = new Collection();
        $this->subject->setData(['organizers' => $organizers]);

        $result = $this->subject->getOrganizers();

        self::assertSame($organizers, $result);
    }

    /**
     * @test
     */
    public function getFirstOrganizerForNoOrganizersThrowsException(): void
    {
        $organizers = new Collection();
        $this->subject->setData(['organizers' => $organizers]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1724277806);
        $this->expectExceptionMessage('This event does not have any organizers.');

        $this->subject->getFirstOrganizer();
    }

    /**
     * @test
     */
    public function getFirstOrganizerForOneOrganizerReturnsThatOrganizer(): void
    {
        $organizer = new Organizer();
        $organizers = new Collection();
        $organizers->add($organizer);
        $this->subject->setData(['organizers' => $organizers]);

        $result = $this->subject->getFirstOrganizer();

        self::assertSame($organizer, $result);
    }

    /**
     * @test
     */
    public function getFirstOrganizerForTwoOrganizersReturnsFirstOrganizer(): void
    {
        $firstOrganizer = new Organizer();
        $organizers = new Collection();
        $organizers->add($firstOrganizer);
        $organizers->add(new Organizer());
        $this->subject->setData(['organizers' => $organizers]);

        $result = $this->subject->getFirstOrganizer();

        self::assertSame($firstOrganizer, $result);
    }

    // Tests concerning getAttendeeNamesAfterLastDigest

    /**
     * @test
     */
    public function getAttendeeNamesAfterLastDigestUsesNewerRegistration(): void
    {
        $firstName = 'Oliver';
        $lastName = 'Klee';

        $user = new FrontEndUser();
        $user->setData(['first_name' => $firstName, 'last_name' => $lastName]);

        $registration = new Registration();
        $registration->setData(
            [
                'user' => $user,
                'registered_themselves' => true,
                'additional_persons' => new Collection(),
                'crdate' => 2,
            ],
        );
        $registrations = new Collection();
        $registrations->add($registration);

        $this->subject->setData(['registrations' => $registrations, 'date_of_last_registration_digest' => 1]);

        self::assertSame(
            [$firstName . ' ' . $lastName],
            $this->subject->getAttendeeNamesAfterLastDigest(),
        );
    }

    /**
     * @test
     */
    public function getAttendeeNamesAfterLastDigestIgnoresOlderRegistration(): void
    {
        $firstName = 'Oliver';
        $lastName = 'Klee';

        $user = new FrontEndUser();
        $user->setData(['first_name' => $firstName, 'last_name' => $lastName]);

        $registration = new Registration();
        $registration->setData(
            [
                'user' => $user,
                'registered_themselves' => true,
                'additional_persons' => new Collection(),
                'crdate' => 1,
            ],
        );
        $registrations = new Collection();
        $registrations->add($registration);

        $this->subject->setData(['registrations' => $registrations, 'date_of_last_registration_digest' => 2]);

        self::assertSame([], $this->subject->getAttendeeNamesAfterLastDigest());
    }

    // Tests regarding the date of the last registration digest email

    /**
     * @test
     */
    public function getDateOfLastRegistrationDigestEmailAsUnixTimeStampWithoutDateReturnsZero(): void
    {
        $this->subject->setData([]);

        self::assertSame(
            0,
            $this->subject->getDateOfLastRegistrationDigestEmailAsUnixTimeStamp(),
        );
    }

    /**
     * @test
     */
    public function getDateOfLastRegistrationDigestEmailAsUnixTimeStampWithPositiveDateReturnsIt(): void
    {
        $this->subject->setData(['date_of_last_registration_digest' => 42]);

        self::assertSame(42, $this->subject->getDateOfLastRegistrationDigestEmailAsUnixTimeStamp());
    }

    /**
     * @test
     */
    public function setDateOfLastRegistrationDigestEmailAsUnixTimeStampWithNegativeDateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subject->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(-1);
    }

    /**
     * @test
     */
    public function setDateOfLastRegistrationDigestEmailAsUnixTimeStampWithZeroDateSetsIt(): void
    {
        $this->subject->setData(['date_of_last_registration_digest' => 42]);
        $this->subject->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(0);

        self::assertSame(0, $this->subject->getDateOfLastRegistrationDigestEmailAsUnixTimeStamp());
    }

    /**
     * @test
     */
    public function setDateOfLastRegistrationDigestEmailAsUnixTimeStampWithPositiveDateSetsIs(): void
    {
        $this->subject->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(42);

        self::assertSame(42, $this->subject->getDateOfLastRegistrationDigestEmailAsUnixTimeStamp());
    }

    // Tests concerning the dates

    /**
     * @test
     */
    public function getBeginDateAsUnixTimeStampByDefaultReturnsZero(): void
    {
        $this->subject->setData([]);

        self::assertSame(0, $this->subject->getBeginDateAsUnixTimeStamp());
    }

    /**
     * @test
     */
    public function getBeginDateAsUnixTimeStampReturnsBeginDate(): void
    {
        $timeStamp = 455456;
        $this->subject->setData(['begin_date' => $timeStamp]);

        self::assertSame($timeStamp, $this->subject->getBeginDateAsUnixTimeStamp());
    }
}
