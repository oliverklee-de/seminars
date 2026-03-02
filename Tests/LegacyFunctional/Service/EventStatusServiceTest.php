<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Service;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Service\EventStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\EventStatusService
 */
final class EventStatusServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected bool $initializeDatabase = false;

    private EventStatusService $subject;

    /**
     * @var EventMapper&MockObject
     */
    private EventMapper $eventMapper;

    private int $past = 0;

    private int $future = 0;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));
        $this->past = (int)GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('date', 'timestamp') - 1;
        $this->future = (int)GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('date', 'timestamp') + 1;

        $this->eventMapper = $this->createMock(EventMapper::class);
        MapperRegistry::set(EventMapper::class, $this->eventMapper);

        $this->subject = new EventStatusService();
    }

    protected function tearDown(): void
    {
        MapperRegistry::purgeInstance();

        parent::tearDown();
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
    public function updateStatusAndSaveForAlreadyConfirmedEventAndFlagSetReturnsFalse(): void
    {
        $event = new Event();
        $event->setData(['registrations' => new Collection(), 'automatic_confirmation_cancelation' => 1]);
        $event->setStatus(EventInterface::STATUS_CONFIRMED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithEnoughRegistrationsReturnsTrue(): void
    {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 1,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithEnoughRegistrationsConfirmsEvent(): void
    {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 1,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $this->subject->updateStatusAndSave($event);

        self::assertTrue($event->isConfirmed());
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithEnoughRegistrationsSavesEvent(): void
    {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 1,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $this->eventMapper->expects(self::once())->method('save')->with($event);

        $this->subject->updateStatusAndSave($event);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithEnoughRegistrationsWithoutAutomaticFlagReturnsFalse(): void
    {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 0,
                'attendees_min' => 1,
                'offline_attendees' => 1,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithEnoughRegistrationsWithoutAutomaticFlagKeepsEventAsPlanned(
    ): void {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 0,
                'attendees_min' => 1,
                'offline_attendees' => 1,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $this->subject->updateStatusAndSave($event);

        self::assertTrue($event->isPlanned());
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForAlreadyCanceledEventAndFlagSetReturnsFalse(): void
    {
        $event = new Event();
        $event->setData(['registrations' => new Collection(), 'automatic_confirmation_cancelation' => 1]);
        $event->setStatus(EventInterface::STATUS_CANCELED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithNotEnoughRegistrationsWithoutRegistrationDeadlineIsFalse(
    ): void {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 0,
                'deadline_registration' => 0,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedWithNotEnoughRegistrationsWithRegistrationDeadlineInFutureIsFalse(
    ): void {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 0,
                'deadline_registration' => $this->future,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithNotEnoughRegistrationsWithRegistrationDeadlineInPastIsTrue(
    ): void {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 0,
                'deadline_registration' => $this->past,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $result = $this->subject->updateStatusAndSave($event);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithNotEnoughRegistrationsWithDeadlineInPastCancelsEvent(): void
    {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 0,
                'deadline_registration' => $this->past,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $this->subject->updateStatusAndSave($event);

        self::assertTrue($event->isCanceled());
    }

    /**
     * @test
     */
    public function updateStatusAndSaveForPlannedEventWithNotEnoughRegistrationsWithDeadlineInPastSavesEvent(): void
    {
        $event = new Event();
        $event->setData(
            [
                'registrations' => new Collection(),
                'automatic_confirmation_cancelation' => 1,
                'attendees_min' => 1,
                'offline_attendees' => 0,
                'deadline_registration' => $this->past,
            ],
        );
        $event->setStatus(EventInterface::STATUS_PLANNED);

        $this->eventMapper->expects(self::once())->method('save')->with($event);

        $this->subject->updateStatusAndSave($event);
    }

    /**
     * @test
     */
    public function cancelAndSaveCancelsEvent(): void
    {
        $event = new Event();
        $event->setData([]);

        $this->subject->cancelAndSave($event);

        self::assertTrue($event->isCanceled());
    }

    /**
     * @test
     */
    public function cancelAndSaveSavesEvent(): void
    {
        $event = new Event();
        $event->setData([]);

        $this->eventMapper->expects(self::once())->method('save')->with($event);

        $this->subject->cancelAndSave($event);
    }

    /**
     * @test
     */
    public function confirmAndSaveConfirmsEvent(): void
    {
        $event = new Event();
        $event->setData([]);

        $this->subject->confirmAndSave($event);

        self::assertTrue($event->isConfirmed());
    }

    /**
     * @test
     */
    public function confirmAndSaveSavesEvent(): void
    {
        $event = new Event();
        $event->setData([]);

        $this->eventMapper->expects(self::once())->method('save')->with($event);

        $this->subject->confirmAndSave($event);
    }
}
