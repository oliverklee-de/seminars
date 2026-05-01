<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Mapper;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\Mapper\OrganizerMapper;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Organizer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class EventMapperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private EventMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->subject = MapperRegistry::get(EventMapper::class);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    // Tests regarding getOrganizers().

    /**
     * @test
     */
    public function getOrganizersReturnsListInstance(): void
    {
        $testingModel = $this->subject->getLoadedTestingModel([]);

        self::assertInstanceOf(Collection::class, $testingModel->getOrganizers());
    }

    /**
     * @test
     */
    public function getOrganizersWithOneOrganizerReturnsListOfOrganizers(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1],
        );
        $organizerUid = MapperRegistry::get(OrganizerMapper::class)->getNewGhost()->getUid();
        \assert($organizerUid > 0);
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $uid,
            $organizerUid,
        );

        $model = $this->subject->find($uid);
        self::assertInstanceOf(Organizer::class, $model->getOrganizers()->first());
    }

    /**
     * @test
     */
    public function getOrganizersWithOneOrganizerReturnsOneOrganizer(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1],
        );
        $organizerUid = MapperRegistry::get(OrganizerMapper::class)->getNewGhost()->getUid();
        \assert($organizerUid > 0);
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $uid,
            $organizerUid,
        );

        $model = $this->subject->find($uid);
        self::assertSame(
            (string)$organizerUid,
            $model->getOrganizers()->getUids(),
        );
    }

    // Tests regarding getOwner().

    /**
     * @test
     */
    public function getOwnerWithoutOwnerReturnsNull(): void
    {
        $testingModel = $this->subject->getLoadedTestingModel([]);

        self::assertNull($testingModel->getOwner());
    }

    /**
     * @test
     */
    public function getOwnerWithOwnerReturnsOwnerInstance(): void
    {
        $frontEndUserUid = MapperRegistry::get(FrontEndUserMapper::class)->getNewGhost()->getUid();
        \assert($frontEndUserUid > 0);
        $testingModel = $this->subject->getLoadedTestingModel(['owner_feuser' => $frontEndUserUid]);

        self::assertInstanceOf(FrontEndUser::class, $testingModel->getOwner());
    }

    ///////////////////////////////////////
    // Tests concerning the registrations
    ///////////////////////////////////////

    /**
     * @test
     */
    public function getRegistrationsWithOneRegistrationReturnsOneRegistration(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['registrations' => 1],
        );
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid],
        );

        $event = $this->subject->find($eventUid);
        self::assertEquals(
            $registrationUid,
            $event->getRegistrations()->getUids(),
        );
    }

    // Tests concerning findForAutomaticStatusChange

    /**
     * @test
     */
    public function findForAutomaticStatusChangeForNoEventsReturnsEmptyList(): void
    {
        $result = $this->subject->findForAutomaticStatusChange();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function findForAutomaticStatusChangeFindsPlannedEventWithAutomaticStatusChange(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_PLANNED, 'automatic_confirmation_cancelation' => 1],
        );

        $result = $this->subject->findForAutomaticStatusChange();

        self::assertFalse($result->isEmpty());
        self::assertSame((string)$uid, $result->getUids());
    }

    /**
     * @test
     */
    public function findForAutomaticStatusChangeNotFindsCanceledEventWithAutomaticStatusChange(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CANCELED, 'automatic_confirmation_cancelation' => 1],
        );

        $result = $this->subject->findForAutomaticStatusChange();

        self::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function findForAutomaticStatusChangeNotFindsConfirmedEventWithAutomaticStatusChange(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CONFIRMED, 'automatic_confirmation_cancelation' => 1],
        );

        $result = $this->subject->findForAutomaticStatusChange();

        self::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function findForAutomaticStatusChangeNotFindsPlannedEventWithoutAutomaticStatusChange(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_PLANNED, 'automatic_confirmation_cancelation' => 0],
        );

        $result = $this->subject->findForAutomaticStatusChange();

        self::assertTrue($result->isEmpty());
    }
}
