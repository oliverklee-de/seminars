<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Service;

use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\EventStatistics;
use OliverKlee\Seminars\Domain\Model\Event\EventTopic;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use OliverKlee\Seminars\Service\OneTimeAccountConnector;
use OliverKlee\Seminars\Service\RegistrationGuard;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\RegistrationGuard
 */
final class RegistrationGuardTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @var non-empty-string
     */
    private string $now = '2022-04-01 10:00:00';

    private Context $context;

    /**
     * @var RegistrationRepository&MockObject
     */
    private RegistrationRepository $registrationRepositoryMock;

    /**
     * @var EventStatisticsCalculator&MockObject
     */
    private EventStatisticsCalculator $eventStatisticsCalculatorMock;

    /**
     * @var OneTimeAccountConnector&MockObject
     */
    private OneTimeAccountConnector $oneTimeAccountConnectorMock;

    private RegistrationGuard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = GeneralUtility::makeInstance(Context::class);

        $this->registrationRepositoryMock = $this->createMock(RegistrationRepository::class);
        $this->eventStatisticsCalculatorMock = $this->createMock(EventStatisticsCalculator::class);
        $this->oneTimeAccountConnectorMock = $this->createMock(OneTimeAccountConnector::class);

        $this->subject = new RegistrationGuard(
            $this->registrationRepositoryMock,
            $this->eventStatisticsCalculatorMock,
            $this->oneTimeAccountConnectorMock,
        );
    }

    /**
     * @deprecated #1960 will be removed in seminars 6.0, use `DateTime::createFromImmutable()` instead (PHP >= 7.3)
     */
    private function createFromImmutable(\DateTimeInterface $dateTime): \DateTime
    {
        return \DateTime::createFromFormat(\DateTimeInterface::ATOM, $dateTime->format(\DateTime::ATOM));
    }

    /**
     * @test
     */
    public function createFromImmutableKeepsDatesComparable(): void
    {
        $dateTimeImmutable = new \DateTimeImmutable($this->now);
        $dateTime = $this->createFromImmutable($dateTimeImmutable);

        self::assertFalse($dateTimeImmutable < $dateTime);
        self::assertFalse($dateTimeImmutable > $dateTime);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->now);
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
    public function assertBookableEventTypeWithSingleEventThrowsNoException(): void
    {
        $this->subject->assertBookableEventType(new SingleEvent());
    }

    /**
     * @test
     */
    public function assertBookableEventTypeWithEventDateThrowsNoException(): void
    {
        $this->subject->assertBookableEventType(new EventDate());
    }

    /**
     * @test
     */
    public function assertBookableEventTypeWithEventTopicThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1669377348);
        $this->expectExceptionMessage('The event must be a SingleEvent or an EventDate.');

        $this->subject->assertBookableEventType(new EventTopic());
    }

    /**
     * @test
     */
    public function isRegistrationPossibleAtAnyTimeAtAllForEventTopicReturnsFalse(): void
    {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));
        $event = new EventTopic();

        self::assertFalse($this->subject->isRegistrationPossibleAtAnyTimeAtAll($event));
    }

    /**
     * @return array<string, array{0: SingleEvent|EventDate}>
     */
    public function nonTopicEventDataProvider(): array
    {
        $eventDate = new EventDate();
        $eventDate->setTopic(new EventTopic());

        return [
            'single event' => [new SingleEvent()],
            'event date with topic' => [$eventDate],
        ];
    }

    /**
     * @test
     *
     * @param SingleEvent|EventDate $event
     *
     * @dataProvider nonTopicEventDataProvider
     */
    public function isRegistrationPossibleAtAnyTimeAtAllEventThatRequiresNoRegistrationReturnsFalse(Event $event): void
    {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));
        $event->setRegistrationRequired(false);

        self::assertFalse($this->subject->isRegistrationPossibleAtAnyTimeAtAll($event));
    }

    /**
     * @test
     *
     * @param SingleEvent|EventDate $event
     *
     * @dataProvider nonTopicEventDataProvider
     */
    public function isRegistrationPossibleAtAnyTimeAtAllForEventThatRequiresRegistrationReturnsTrue(Event $event): void
    {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));
        $event->setRegistrationRequired(true);

        self::assertTrue($this->subject->isRegistrationPossibleAtAnyTimeAtAll($event));
    }

    /**
     * @test
     */
    public function isRegistrationPossibleAtAnyTimeAtAllForEventDateWithoutTopicReturnsFalse(): void
    {
        $eventDate = new EventDate();
        $eventDate->setRegistrationRequired(true);

        self::assertFalse($this->subject->isRegistrationPossibleAtAnyTimeAtAll($eventDate));
    }

    /**
     * @return array<string, array{
     *             start: ?\DateTime,
     *             registrationStart: ?\DateTime,
     *             registrationDeadline: ?\DateTime
     *         }>
     */
    public function registrationPossibleDataProvider(): array
    {
        $now = $this->createFromImmutable($this->now());
        // We need the clone because `modify` on `DateTime` modifies the original object instead of returning a new one
        // (which would be the case for `DateTimeImmutable`.
        $future = (clone $now)->modify('+1 day');
        $past = (clone $now)->modify('-1 day');

        return [
            'start in the future' => [
                'start' => $future,
                'registrationStart' => null,
                'registrationDeadline' => null,
            ],
            'start in the future and registration start in the past' => [
                'start' => $future,
                'registrationStart' => $past,
                'registrationDeadline' => null,
            ],
            'start in the future and registration start now' => [
                'start' => $future,
                'registrationStart' => $now,
                'registrationDeadline' => null,
            ],
            'registration deadline in the future' => [
                'start' => null,
                'registrationStart' => null,
                'registrationDeadline' => $future,
            ],
            'start now and registration deadline in the future' => [
                'start' => $now,
                'registrationStart' => null,
                'registrationDeadline' => $future,
            ],
            'no dates at all' => [
                'start' => null,
                'registrationStart' => null,
                'registrationDeadline' => null,
            ],
            'no start, but registration start in the past' => [
                'start' => null,
                'registrationStart' => $past,
                'registrationDeadline' => null,
            ],
            'no start, but registration start now' => [
                'start' => null,
                'registrationStart' => $now,
                'registrationDeadline' => null,
            ],
        ];
    }

    /**
     * @return array<string, array{
     *             start: ?\DateTime,
     *             registrationStart: ?\DateTime,
     *             registrationDeadline: ?\DateTime
     *         }>
     */
    public function registrationNotPossibleDataProvider(): array
    {
        $now = $this->createFromImmutable($this->now());
        // We need the clone because `modify` on `DateTime` modifies the original object instead of returning a new one
        // (which would be the case for `DateTimeImmutable`.
        $future = (clone $now)->modify('+1 day');
        $past = (clone $now)->modify('-1 day');

        return [
            'start in the past' => [
                'start' => $past,
                'registrationStart' => null,
                'registrationDeadline' => null,
            ],
            'start now' => [
                'start' => $now,
                'registrationStart' => null,
                'registrationDeadline' => null,
            ],
            'no start, but registration start in the future' => [
                'start' => null,
                'registrationStart' => $future,
                'registrationDeadline' => null,
            ],
            'start now and registration start in the past' => [
                'start' => $now,
                'registrationStart' => $past,
                'registrationDeadline' => null,
            ],
            'start and registration start in the future' => [
                'start' => $future,
                'registrationStart' => $future,
                'registrationDeadline' => null,
            ],
            'registration deadline in the past' => [
                'start' => null,
                'registrationStart' => null,
                'registrationDeadline' => $past,
            ],
            'registration deadline now' => [
                'start' => null,
                'registrationStart' => null,
                'registrationDeadline' => $now,
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider registrationPossibleDataProvider
     */
    public function isRegistrationPossibleByDateForEventDateWithRegistrationPossibleReturnsTrue(
        ?\DateTime $start,
        ?\DateTime $registrationStart,
        ?\DateTime $registrationDeadline
    ): void {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));

        $event = new EventDate();
        $event->setRegistrationRequired(true);
        $event->setStart($start);
        $event->setRegistrationStart($registrationStart);
        $event->setRegistrationDeadline($registrationDeadline);

        self::assertTrue($this->subject->isRegistrationPossibleByDate($event));
    }

    /**
     * @test
     *
     * @dataProvider registrationNotPossibleDataProvider
     */
    public function isRegistrationPossibleByDateForEventDateWithRegistrationNotPossibleReturnsFalse(
        ?\DateTime $start,
        ?\DateTime $registrationStart,
        ?\DateTime $registrationDeadline
    ): void {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));

        $event = new EventDate();
        $event->setRegistrationRequired(true);
        $event->setStart($start);
        $event->setRegistrationStart($registrationStart);
        $event->setRegistrationDeadline($registrationDeadline);

        self::assertFalse($this->subject->isRegistrationPossibleByDate($event));
    }

    /**
     * @test
     *
     * @dataProvider registrationPossibleDataProvider
     */
    public function isRegistrationPossibleByDateForSingleEventWithRegistrationPossibleReturnsTrue(
        ?\DateTime $start,
        ?\DateTime $registrationStart,
        ?\DateTime $registrationDeadline
    ): void {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));

        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setStart($start);
        $event->setRegistrationStart($registrationStart);
        $event->setRegistrationDeadline($registrationDeadline);

        self::assertTrue($this->subject->isRegistrationPossibleByDate($event));
    }

    /**
     * @test
     *
     * @dataProvider registrationNotPossibleDataProvider
     */
    public function isRegistrationPossibleByDateForSingleEventWithRegistrationNotPossibleReturnsFalse(
        ?\DateTime $start,
        ?\DateTime $registrationStart,
        ?\DateTime $registrationDeadline
    ): void {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));

        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setStart($start);
        $event->setRegistrationStart($registrationStart);
        $event->setRegistrationDeadline($registrationDeadline);

        self::assertFalse($this->subject->isRegistrationPossibleByDate($event));
    }

    /**
     * @test
     *
     * @dataProvider registrationPossibleDataProvider
     */
    public function setRegistrationPossibleByDateForEventsCanSetTruePossibilityForGivenEvents(
        ?\DateTime $start,
        ?\DateTime $registrationStart,
        ?\DateTime $registrationDeadline
    ): void {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));

        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setStart($start);
        $event->setRegistrationStart($registrationStart);
        $event->setRegistrationDeadline($registrationDeadline);

        $this->subject->setRegistrationPossibleByDateForEvents([$event]);

        self::assertTrue($event->isRegistrationPossibleByDate());
    }

    /**
     * @test
     *
     * @dataProvider registrationNotPossibleDataProvider
     */
    public function setRegistrationPossibleByDateForEventsCanSetFalsePossibilityForGivenEvents(
        ?\DateTime $start,
        ?\DateTime $registrationStart,
        ?\DateTime $registrationDeadline
    ): void {
        $this->context->setAspect('date', new DateTimeAspect($this->now()));

        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setStart($start);
        $event->setRegistrationStart($registrationStart);
        $event->setRegistrationDeadline($registrationDeadline);

        $this->subject->setRegistrationPossibleByDateForEvents([$event]);

        self::assertFalse($event->isRegistrationPossibleByDate());
    }

    /**
     * @test
     */
    public function isFreeFromRegistrationConflictsForNoConflictAndNoMultipleRegistrationsPossibleReturnsTrue(): void
    {
        $userUid = 47;
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $this->registrationRepositoryMock
            ->method('existsRegistrationForEventAndUser')
            ->with($event, $userUid)->willReturn(false);
        $event->setMultipleRegistrationPossible(true);

        self::assertTrue($this->subject->isFreeFromRegistrationConflicts($event, $userUid));
    }

    /**
     * @test
     */
    public function isFreeFromRegistrationConflictsForNoConflictAndMultipleRegistrationsPossibleReturnsTrue(): void
    {
        $userUid = 47;
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $this->registrationRepositoryMock
            ->method('existsRegistrationForEventAndUser')
            ->with($event, $userUid)->willReturn(false);
        $event->setMultipleRegistrationPossible(true);

        self::assertTrue($this->subject->isFreeFromRegistrationConflicts($event, $userUid));
    }

    /**
     * @test
     */
    public function isFreeFromRegistrationConflictsForConflictAndMultipleRegistrationsPossibleReturnsTrue(): void
    {
        $userUid = 47;
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $this->registrationRepositoryMock
            ->method('existsRegistrationForEventAndUser')
            ->with($event, $userUid)->willReturn(true);
        $event->setMultipleRegistrationPossible(true);

        self::assertTrue($this->subject->isFreeFromRegistrationConflicts($event, $userUid));
    }

    /**
     * @test
     */
    public function isFreeFromRegistrationConflictsForConflictAndMultipleRegistrationsNotPossibleReturnsFalse(): void
    {
        $userUid = 47;
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $this->registrationRepositoryMock
            ->method('existsRegistrationForEventAndUser')
            ->with($event, $userUid)->willReturn(true);
        $event->setMultipleRegistrationPossible(false);

        self::assertFalse($this->subject->isFreeFromRegistrationConflicts($event, $userUid));
    }

    /**
     * @test
     */
    public function existsFrontEndUserUidInSessionForNoLoginAndNoOneTimeAccountDataReturnsFalse(): void
    {
        $this->context->setAspect('frontend.user', new UserAspect());
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn(null);

        self::assertFalse($this->subject->existsFrontEndUserUidInSession());
    }

    /**
     * @test
     */
    public function existsFrontEndUserUidInSessionForLoginAndNoOneTimeAccountDataReturnsTrue(): void
    {
        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => 5];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn(null);

        self::assertTrue($this->subject->existsFrontEndUserUidInSession());
    }

    /**
     * @test
     */
    public function existsFrontEndUserUidInSessionForNoLoginAndOneTimeAccountDataReturnsTrue(): void
    {
        $this->context->setAspect('frontend.user', new UserAspect());
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn(5);

        self::assertTrue($this->subject->existsFrontEndUserUidInSession());
    }

    /**
     * @test
     */
    public function existsFrontEndUserUidInSessionForLoginAndOneTimeAccountDataReturnsTrue(): void
    {
        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => 3];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn(5);

        self::assertTrue($this->subject->existsFrontEndUserUidInSession());
    }

    /**
     * @test
     */
    public function getFrontEndUserUidFromSessionForNoLoginAndNoOneTimeAccountDataReturnsNull(): void
    {
        $this->context->setAspect('frontend.user', new UserAspect());
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn(null);

        self::assertNull($this->subject->getFrontEndUserUidFromSession());
    }

    /**
     * @test
     */
    public function getFrontEndUserUidFromSessionForLoginAndNoOneTimeAccountDataReturnsUidFromLogin(): void
    {
        $userUidFromLogin = 12;
        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => $userUidFromLogin];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));

        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn(null);

        self::assertSame($userUidFromLogin, $this->subject->getFrontEndUserUidFromSession());
    }

    /**
     * @test
     */
    public function getFrontEndUserUidFromSessionForNoLoginAndOneTimeAccountDataReturnsUidFromOneTimeAccount(): void
    {
        $userUidFromOneTimeAccount = 12;
        $this->context->setAspect('frontend.user', new UserAspect());
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn($userUidFromOneTimeAccount);

        self::assertSame($userUidFromOneTimeAccount, $this->subject->getFrontEndUserUidFromSession());
    }

    /**
     * @test
     */
    public function getFrontEndUserUidFromSessionForLoginAndOneTimeAccountDataReturnsUidFromLogin(): void
    {
        $userUidFromLogin = 12;
        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => $userUidFromLogin];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));

        $userUidFromOneTimeAccount = 9;
        $this->oneTimeAccountConnectorMock->method('getOneTimeAccountUserUid')->willReturn($userUidFromOneTimeAccount);

        self::assertSame($userUidFromLogin, $this->subject->getFrontEndUserUidFromSession());
    }

    /**
     * @test
     */
    public function getVacanciesWithEventTopicThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1669377348);
        $this->expectExceptionMessage('The event must be a SingleEvent or an EventDate.');

        $this->subject->getVacancies(new EventTopic());
    }

    /**
     * @test
     */
    public function getVacanciesWithSingleEventWithoutLimitAndNoOfflineRegistrationsReturnsNull(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(0);
        $event->setNumberOfOfflineRegistrations(0);

        self::assertNull($this->subject->getVacancies($event));
    }

    /**
     * @test
     */
    public function getVacanciesWithEventDateWithoutLimitAndNoOfflineRegistrationsReturnsNull(): void
    {
        $event = new EventDate();
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(0);
        $event->setNumberOfOfflineRegistrations(0);

        self::assertNull($this->subject->getVacancies($event));
    }

    /**
     * @test
     */
    public function getVacanciesWithoutLimitAndOfflineRegistrationsReturnsNull(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(0);
        $event->setNumberOfOfflineRegistrations(1);

        self::assertNull($this->subject->getVacancies($event));
    }

    /**
     * @test
     */
    public function getVacanciesWithProblemCreatingStatisticsThrowsException(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $event->setMaximumNumberOfRegistrations(10);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1670402765);
        $this->expectExceptionMessage('The event statistics should have been set.');

        $this->subject->getVacancies($event);
    }

    /**
     * @test
     */
    public function getVacanciesReturnsVacanciesFromStatistics(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $seatsLimit = 10;
        $event->setMaximumNumberOfRegistrations($seatsLimit);
        $offlineRegistrations = 5;
        $event->setNumberOfOfflineRegistrations($offlineRegistrations);

        $this->eventStatisticsCalculatorMock->expects(self::once())->method('enrichWithStatistics')->willReturnCallback(
            static function (SingleEvent $event) use ($offlineRegistrations, $seatsLimit): void {
                $statistics = new EventStatistics(0, $offlineRegistrations, 0, 0, $seatsLimit, false);
                $event->setStatistics($statistics);
            },
        );

        self::assertSame($seatsLimit - $offlineRegistrations, $this->subject->getVacancies($event));
    }

    /**
     * @test
     */
    public function getVacanciesCalledTwiceCachesStatistics(): void
    {
        $event = new SingleEvent();
        $event->setRegistrationRequired(true);
        $seatsLimit = 10;
        $event->setMaximumNumberOfRegistrations($seatsLimit);
        $offlineRegistrations = 5;
        $event->setNumberOfOfflineRegistrations($offlineRegistrations);

        $this->eventStatisticsCalculatorMock->expects(self::once())->method('enrichWithStatistics')->willReturnCallback(
            static function (SingleEvent $event) use ($offlineRegistrations, $seatsLimit): void {
                $statistics = new EventStatistics(0, $offlineRegistrations, 0, 0, $seatsLimit, false);
                $event->setStatistics($statistics);
            },
        );

        $this->subject->getVacancies($event);
        $this->subject->getVacancies($event);
    }
}
