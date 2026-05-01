<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\BagBuilder;

use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\RegistrationBag;
use OliverKlee\Seminars\BagBuilder\RegistrationBagBuilder;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\RegistrationBagBuilder
 */
final class RegistrationBagBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RegistrationBagBuilder $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->testingFramework = new TestingFramework();

        $this->subject = new RegistrationBagBuilder();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    ///////////////////////////////////////////
    // Tests for the basic builder functions.
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function bagBuilderBuildsARegistrationBag(): void
    {
        self::assertInstanceOf(RegistrationBag::class, $this->subject->build());
    }

    /**
     * @test
     */
    public function buildReturnsBagWhichIsSortedAscendingByCrDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'title' => 'Title 2',
                'crdate' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_DAY,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'title' => 'Title 1',
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
            ],
        );

        $registrationBag = $this->subject->build();
        self::assertEquals(
            2,
            $registrationBag->count(),
        );

        self::assertEquals(
            'Title 1',
            $registrationBag->current()->getTitle(),
        );
        $registrationBag->next();
        self::assertEquals(
            'Title 2',
            $registrationBag->current()->getTitle(),
        );
    }

    /**
     * @test
     */
    public function buildWithoutLimitReturnsBagWithAllRegistrations(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['title' => 'Attendance 1', 'seminar' => $eventUid1],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['title' => 'Attendance 2', 'seminar' => $eventUid1],
        );
        $registrationBag = $this->subject->build();

        self::assertEquals(
            2,
            $registrationBag->count(),
        );
    }

    /////////////////////////////
    // Tests for limitToEvent()
    /////////////////////////////

    /**
     * @test
     */
    public function limitToEventWithValidEventUidFindsRegistrationOfEvent(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['title' => 'Attendance 1', 'seminar' => $eventUid1],
        );
        $this->subject->limitToEvent($eventUid1);
        $registrationBag = $this->subject->build();

        self::assertEquals(
            'Attendance 1',
            $registrationBag->current()->getTitle(),
        );
    }

    /**
     * @test
     */
    public function limitToEventWithValidEventUidIgnoresRegistrationOfOtherEvent(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['title' => 'Attendance 2', 'seminar' => $eventUid2],
        );
        $this->subject->limitToEvent($eventUid1);
        $registrationBag = $this->subject->build();

        self::assertTrue(
            $registrationBag->isEmpty(),
        );
    }

    ///////////////////////////////
    // Tests for limitToOnQueue()
    ///////////////////////////////

    /**
     * @test
     */
    public function limitToOnQueueFindsWaitingListRegistration(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => Registration::STATUS_WAITING_LIST],
        );
        $this->subject->limitToOnQueue();
        /** @var LegacyRegistration $currentModel */
        $currentModel = $this->subject->build()->current();

        self::assertTrue($currentModel->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function limitToOnQueueIgnoresRegularRegistration(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => Registration::STATUS_REGULAR],
        );
        $this->subject->limitToOnQueue();
        $registrationBag = $this->subject->build();

        self::assertTrue($registrationBag->isEmpty());
    }

    /**
     * @test
     */
    public function limitToOnQueueIgnoresNonbindingReservation(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => Registration::STATUS_NONBINDING_RESERVATION],
        );
        $this->subject->limitToOnQueue();
        $registrationBag = $this->subject->build();

        self::assertTrue($registrationBag->isEmpty());
    }

    ///////////////////////////////
    // Tests for limitToRegular()
    ///////////////////////////////

    /**
     * @test
     */
    public function limitToRegularFindsRegularRegistration(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => Registration::STATUS_REGULAR],
        );
        $this->subject->limitToRegular();
        /** @var LegacyRegistration $currentModel */
        $currentModel = $this->subject->build()->current();

        self::assertFalse($currentModel->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function limitToRegularIgnoresWaitingListRegistration(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => Registration::STATUS_WAITING_LIST],
        );
        $this->subject->limitToRegular();
        $registrationBag = $this->subject->build();

        self::assertTrue($registrationBag->isEmpty());
    }

    /**
     * @test
     */
    public function limitToRegularIgnoresNonbindingReservation(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => Registration::STATUS_NONBINDING_RESERVATION],
        );
        $this->subject->limitToRegular();
        $registrationBag = $this->subject->build();

        self::assertTrue($registrationBag->isEmpty());
    }

    //////////////////////////////////////
    // Tests for removeQueueLimitation()
    //////////////////////////////////////

    /**
     * @test
     */
    public function removeQueueLimitationRemovesOnQueueLimit(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => 0],
        );
        $this->subject->limitToOnQueue();
        $this->subject->removeQueueLimitation();
        /** @var LegacyRegistration $currentModel */
        $currentModel = $this->subject->build()->current();

        self::assertFalse($currentModel->isOnRegistrationQueue());
    }

    /**
     * @test
     */
    public function removeQueueLimitationRemovesRegularLimit(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['registration_queue' => 1],
        );
        $this->subject->limitToRegular();
        $this->subject->removeQueueLimitation();
        /** @var LegacyRegistration $currentModel */
        $currentModel = $this->subject->build()->current();

        self::assertTrue($currentModel->isOnRegistrationQueue());
    }

    ///////////////////////////////////
    // Tests for limitToSeatsAtMost()
    ///////////////////////////////////

    /**
     * @test
     */
    public function limitToSeatsAtMostFindsRegistrationWithEqualSeats(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seats' => 2],
        );
        $this->subject->limitToSeatsAtMost(2);
        /** @var LegacyRegistration $currentModel */
        $currentModel = $this->subject->build()->current();

        self::assertEquals(2, $currentModel->getSeats());
    }

    /**
     * @test
     */
    public function limitToSeatsAtMostFindsRegistrationWithLessSeats(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seats' => 1],
        );
        $this->subject->limitToSeatsAtMost(2);
        /** @var LegacyRegistration $currentModel */
        $currentModel = $this->subject->build()->current();

        self::assertEquals(1, $currentModel->getSeats());
    }

    /**
     * @test
     */
    public function limitToSeatsAtMostIgnoresRegistrationWithMoreSeats(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seats' => 2],
        );
        $this->subject->limitToSeatsAtMost(1);
        $registrationBag = $this->subject->build();

        self::assertTrue(
            $registrationBag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToSeatsAtMostWithZeroSeatsFindsAllRegistrations(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seats' => 2],
        );
        $this->subject->limitToSeatsAtMost(1);
        $this->subject->limitToSeatsAtMost();
        $registrationBag = $this->subject->build();

        self::assertFalse(
            $registrationBag->isEmpty(),
        );
    }

    //////////////////////////////
    // Tests for limitToAttendee
    //////////////////////////////

    /**
     * @test
     */
    public function limitToAttendeeWithUserFindsRegistrationsWithAttendee(): void
    {
        $feUserUid = $this->testingFramework->createFrontEndUser();
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'user' => $feUserUid],
        );

        $user = MapperRegistry::get(FrontEndUserMapper::class)->find($feUserUid);
        $this->subject->limitToAttendee($user);
        $bag = $this->subject->build();

        self::assertEquals(
            $registrationUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToAttendeeWithUserFindsRegistrationsWithUserAsAdditionalRegisteredPerson(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'additional_persons' => 1],
        );
        $feUserUid = $this->testingFramework->createFrontEndUser(
            '',
            ['tx_seminars_registration' => $registrationUid],
        );

        $user = MapperRegistry::get(FrontEndUserMapper::class)->find($feUserUid);
        $this->subject->limitToAttendee($user);
        $bag = $this->subject->build();

        self::assertEquals(
            $registrationUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToAttendeeWithUserIgnoresRegistrationsWithoutAttendee(): void
    {
        $feUserUid = $this->testingFramework->createFrontEndUser();
        $this->testingFramework->createRecord('tx_seminars_seminars');

        $user = MapperRegistry::get(FrontEndUserMapper::class)->find($feUserUid);
        $this->subject->limitToAttendee($user);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToAttendeeWithUserIgnoresRegistrationsWithOtherAttendee(): void
    {
        $feUserGroupUid = $this->testingFramework->createFrontEndUserGroup();
        $feUserUid = $this->testingFramework->createFrontEndUser($feUserGroupUid);
        $feUserUid2 = $this->testingFramework->createFrontEndUser($feUserGroupUid);
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'user' => $feUserUid2],
        );

        $user = MapperRegistry::get(FrontEndUserMapper::class)->find($feUserUid);
        $this->subject->limitToAttendee($user);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToAttendeeWithNullFindsRegistrationsWithOtherAttendee(): void
    {
        $feUserGroupUid = $this->testingFramework->createFrontEndUserGroup();
        $feUserUid = $this->testingFramework->createFrontEndUser($feUserGroupUid);
        $feUserUid2 = $this->testingFramework->createFrontEndUser($feUserGroupUid);
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'user' => $feUserUid2],
        );

        $user = MapperRegistry::get(FrontEndUserMapper::class)->find($feUserUid);
        $this->subject->limitToAttendee($user);
        $this->subject->limitToAttendee();
        $bag = $this->subject->build();

        self::assertEquals(
            $registrationUid,
            $bag->current()->getUid(),
        );
    }

    // Tests for setOrderByEventColumn()

    /**
     * @test
     */
    public function setOrderByEventColumnCanSortAscendingByEventTitle(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'test title 1'],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'test title 2'],
        );
        $registrationUid1 = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid1],
        );
        $registrationUid2 = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid2],
        );

        $this->subject->setOrderByEventColumn(
            'tx_seminars_seminars.title ASC',
        );
        $bag = $this->subject->build();

        self::assertEquals(
            $bag->current()->getUid(),
            $registrationUid1,
        );
        $bag->next();
        self::assertEquals(
            $bag->current()->getUid(),
            $registrationUid2,
        );
    }

    /**
     * @test
     */
    public function setOrderByEventColumnCanSortDescendingByEventTitle(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'test title 1'],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'test title 2'],
        );
        $registrationUid1 = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid1],
        );
        $registrationUid2 = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid2],
        );

        $this->subject->setOrderByEventColumn(
            'tx_seminars_seminars.title DESC',
        );
        $bag = $this->subject->build();

        self::assertEquals(
            $bag->current()->getUid(),
            $registrationUid2,
        );
        $bag->next();
        self::assertEquals(
            $bag->current()->getUid(),
            $registrationUid1,
        );
    }

    //////////////////////////////////////////
    // Tests concerning limitToExistingUsers
    //////////////////////////////////////////

    /**
     * @test
     */
    public function limitToExistingUsersFindsRegistrationWithExistingUser(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['user' => $this->testingFramework->createFrontEndUser()],
        );
        $this->subject->limitToExistingUsers();
        $bag = $this->subject->build();

        self::assertFalse(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToExistingUsersDoesNotFindRegistrationWithDeletedUser(): void
    {
        $feUserUid = $this->testingFramework->createFrontEndUser();

        $this->testingFramework->changeRecord(
            'fe_users',
            $feUserUid,
            ['deleted' => 1],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['user' => $feUserUid],
        );
        $this->subject->limitToExistingUsers();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }
}
