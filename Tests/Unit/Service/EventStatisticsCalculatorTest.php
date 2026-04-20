<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Service;

use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\EventStatistics;
use OliverKlee\Seminars\Domain\Model\Event\EventTopic;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\EventStatisticsCalculator
 */
final class EventStatisticsCalculatorTest extends UnitTestCase
{
    /**
     * @var RegistrationRepository&MockObject
     */
    private RegistrationRepository $registrationRepositoryMock;

    private EventStatisticsCalculator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrationRepositoryMock = $this->createMock(RegistrationRepository::class);
        $this->subject = new EventStatisticsCalculator($this->registrationRepositoryMock);
    }

    /**
     * @test
     */
    public function classIsSingleton(): void
    {
        self::assertInstanceOf(SingletonInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventTopicSetsNoStatistics(): void
    {
        $event = new EventTopic();

        $this->subject->enrichWithStatistics($event);

        self::assertNull($event->getStatistics());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventDateWithoutRegistrationSetsStatistics(): void
    {
        $event = new EventDate();

        $this->subject->enrichWithStatistics($event);

        self::assertInstanceOf(EventStatistics::class, $event->getStatistics());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForSingleEventWithoutRegistrationSetsStatistics(): void
    {
        $event = new SingleEvent();

        $this->subject->enrichWithStatistics($event);

        self::assertInstanceOf(EventStatistics::class, $event->getStatistics());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventDateWithRegistrationSetsStatistics(): void
    {
        $event = new EventDate();
        $event->setRegistrationRequired(true);

        $this->subject->enrichWithStatistics($event);

        self::assertInstanceOf(EventStatistics::class, $event->getStatistics());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsCalledTwiceReturnsSameStatistics(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);

        $this->subject->enrichWithStatistics($event);
        $statisticsAfterFirstCall = $event->getStatistics();

        $this->subject->enrichWithStatistics($event);
        $statisticsAfterSecondCall = $event->getStatistics();

        self::assertSame($statisticsAfterFirstCall, $statisticsAfterSecondCall);
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForSingleEventWithRegistrationSetStatistics(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);

        $this->subject->enrichWithStatistics($event);

        self::assertInstanceOf(EventStatistics::class, $event->getStatistics());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsUsesSeatsLimitFromEvent(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $seatsLimit = 42;
        $event->setMaximumNumberOfRegistrations($seatsLimit);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($seatsLimit, $statistics->getSeatsLimit());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsUsesMinimumSeatsFromEvent(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $minimumSeats = 42;
        $event->setMinimumNumberOfRegistrations($minimumSeats);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($minimumSeats, $statistics->getMinimumRequiredSeats());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsUsesOfflineRegistrationsSeatsFromEvent(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $offlineRegistrations = 42;
        $event->setNumberOfOfflineRegistrations($offlineRegistrations);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($offlineRegistrations, $statistics->getRegularSeatsCount());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsRetrievesRegularSeatsFromRegistrations(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);

        $seatsFromRegistrations = 15;
        $this->registrationRepositoryMock
            ->expects(self::once())->method('countRegularSeatsByEvent')
            ->with($eventUid)->willReturn($seatsFromRegistrations);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($seatsFromRegistrations, $statistics->getRegularSeatsCount());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsRetrievesRegularSeatsFromRegistrationsEvenWithDisabledRegistration(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(false);

        $seatsFromRegistrations = 15;
        $this->registrationRepositoryMock
            ->expects(self::once())->method('countRegularSeatsByEvent')
            ->with($eventUid)->willReturn($seatsFromRegistrations);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($seatsFromRegistrations, $statistics->getRegularSeatsCount());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsRetrievesWaitingListSeatsFromRegistrations(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setWaitingList(true);

        $waitingListSeats = 15;
        $this->registrationRepositoryMock
            ->expects(self::once())->method('countWaitingListSeatsByEvent')
            ->with($eventUid)->willReturn($waitingListSeats);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($waitingListSeats, $statistics->getWaitingListSeatsCount());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsRetrievesNonbindingReservationSeatsFromRegistrations(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);

        $nonbindingReservationSeats = 8;
        $this->registrationRepositoryMock
            ->expects(self::once())->method('countNonbindingReservationSeatsByEvent')
            ->with($eventUid)->willReturn($nonbindingReservationSeats);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($nonbindingReservationSeats, $statistics->getNonbindingReservationSeatsCount());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsRetrievesWaitingListSeatsFromRegistrationsEvenWithDisabledWaitingList(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setWaitingList(false);

        $waitingListSeats = 15;
        $this->registrationRepositoryMock
            ->expects(self::once())->method('countWaitingListSeatsByEvent')
            ->with($eventUid)->willReturn($waitingListSeats);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertSame($waitingListSeats, $statistics->getWaitingListSeatsCount());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventWithUnlimitedSeatsMakesRegularVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(0);
        $event->setWaitingList(false);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertTrue($statistics->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventWithAvailableLimitedSeatsMakesRegularVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setWaitingList(false);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertTrue($statistics->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForFullyBookedEventWithoutWaitingSetsNoRegularVacancies(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setNumberOfOfflineRegistrations(5);
        $event->setWaitingList(false);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertFalse($statistics->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForFullyBookedEventWithWaitingSetsNoRegularVacancies(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setNumberOfOfflineRegistrations(5);
        $event->setWaitingList(true);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertFalse($statistics->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventWithUnlimitedSeatsMakesNoWaitingListVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(0);
        $event->setWaitingList(false);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertFalse($statistics->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventWithAvailableLimitedSeatsMakesNoWaitingListVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setWaitingList(false);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertFalse($statistics->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForEventWithVacanciesAndWaitingListMakesNoWaitingListVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setWaitingList(true);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertFalse($statistics->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForFullyBookedEventWithoutWaitingMakesNoWaitingListVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setNumberOfOfflineRegistrations(5);
        $event->setWaitingList(false);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertFalse($statistics->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function enrichWithStatisticsForFullyBookedEventWithWaitingSMakesWaitingListVacanciesAvailable(): void
    {
        $eventUid = 9;
        $event = $this->getMockBuilder(SingleEvent::class)->onlyMethods(['getUid'])->getMock();
        $event->method('getUid')->willReturn($eventUid);
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(5);
        $event->setNumberOfOfflineRegistrations(5);
        $event->setWaitingList(true);

        $this->subject->enrichWithStatistics($event);

        $statistics = $event->getStatistics();
        self::assertInstanceOf(EventStatistics::class, $statistics);
        self::assertTrue($statistics->hasWaitingListVacancies());
    }
}
