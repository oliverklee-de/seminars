<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Model;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Mapper\RegistrationMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\Registration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\Model\Event
 */
final class EventTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RegistrationMapper $registrationMapper;

    private Event $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->get(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->get(ConfigurationRegistry::class)->set('plugin.tx_seminars', new DummyConfiguration());

        $this->registrationMapper = $this->get(MapperRegistry::class)->getByClassName(RegistrationMapper::class);

        $this->subject = new Event();
    }

    /**
     * @test
     */
    public function getRegularRegistrationsReturnsRegularRegistrations(): void
    {
        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $registration = $this->registrationMapper->getLoadedTestingModel(['registration_queue' => 0]);
        $registrations->add($registration);
        $this->subject->setRegistrations($registrations);

        self::assertEquals(
            $registration->getUid(),
            $this->subject->getRegularRegistrations()->getUids(),
        );
    }

    /**
     * @test
     */
    public function getRegularRegistrationsNotReturnsQueueRegistrations(): void
    {
        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $registration = $this->registrationMapper->getLoadedTestingModel(['registration_queue' => 1]);
        $registrations->add($registration);
        $this->subject->setRegistrations($registrations);

        self::assertTrue(
            $this->subject->getRegularRegistrations()->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function getRegisteredSeatsCountsSingleSeatRegularRegistrations(): void
    {
        $registrations = new Collection();
        $registration = $this->registrationMapper->getLoadedTestingModel(['seats' => 1]);
        $registrations->add($registration);
        $event = $this->getMockBuilder(Event::class)->onlyMethods(['getRegularRegistrations'])->getMock();
        $event->setData([]);
        $event
            ->method('getRegularRegistrations')
            ->willReturn($registrations);

        self::assertEquals(
            1,
            $event->getRegisteredSeats(),
        );
    }

    /**
     * @test
     */
    public function getRegisteredSeatsCountsMultiSeatRegularRegistrations(): void
    {
        $registrations = new Collection();
        $registration = $this->registrationMapper->getLoadedTestingModel(['seats' => 2]);
        $registrations->add($registration);
        $event = $this->getMockBuilder(Event::class)->onlyMethods(['getRegularRegistrations'])->getMock();
        $event->setData([]);
        $event
            ->method('getRegularRegistrations')
            ->willReturn($registrations);

        self::assertEquals(
            2,
            $event->getRegisteredSeats(),
        );
    }

    /**
     * @test
     */
    public function getRegisteredSeatsNotCountsQueueRegistrations(): void
    {
        $queueRegistrations = new Collection();
        $registration = $this->registrationMapper->getLoadedTestingModel(['seats' => 1]);
        $queueRegistrations->add($registration);
        $event = $this->createPartialMock(Event::class, ['getRegularRegistrations']);
        $event->setData([]);
        $event->method('getRegularRegistrations')->willReturn(new Collection());

        self::assertSame(0, $event->getRegisteredSeats());
    }
}
