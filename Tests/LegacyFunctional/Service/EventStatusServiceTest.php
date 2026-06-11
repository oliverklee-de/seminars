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
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\EventStatusService
 */
final class EventStatusServiceTest extends FunctionalTestCase
{
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

    protected bool $initializeDatabase = false;

    private EventStatusService $subject;

    /**
     * @var EventMapper&MockObject
     */
    private EventMapper $eventMapper;

    private int $pastAsUnixTimestamp;

    private int $futureAsUnixTimestamp;

    protected function setUp(): void
    {
        parent::setUp();

        $now = new \DateTimeImmutable('2018-04-26 12:42:23');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect($now));
        $nowAsUnixTimestamp = $now->getTimestamp();
        self::assertGreaterThan(0, $nowAsUnixTimestamp);
        $this->pastAsUnixTimestamp = $nowAsUnixTimestamp - 1;
        $this->futureAsUnixTimestamp = $nowAsUnixTimestamp + 1;

        $this->eventMapper = $this->createMock(EventMapper::class);
        $this->get(MapperRegistry::class)->setByClassName(EventMapper::class, $this->eventMapper);

        $this->subject = $this->get(EventStatusService::class);
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(EventStatusService::class, $this->get(EventStatusService::class));
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
                'deadline_registration' => $this->futureAsUnixTimestamp,
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
                'deadline_registration' => $this->pastAsUnixTimestamp,
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
                'deadline_registration' => $this->pastAsUnixTimestamp,
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
                'deadline_registration' => $this->pastAsUnixTimestamp,
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
