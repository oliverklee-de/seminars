<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\BagBuilder;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\BagBuilder\OrganizerBagBuilder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\OrganizerBagBuilder
 */
final class OrganizerBagBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private OrganizerBagBuilder $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->subject = new OrganizerBagBuilder();
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
    public function builderBuildsABag(): void
    {
        self::assertInstanceOf(AbstractBag::class, $this->subject->build());
    }

    /////////////////////////////
    // Tests for limitToEvent()
    /////////////////////////////

    /**
     * @test
     */
    public function limitToEventFindsOneOrganizerOfEvent(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid,
        );

        $this->subject->limitToEvent($eventUid);
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->countWithoutLimit(),
        );
    }

    /**
     * @test
     */
    public function limitToEventFindsTwoOrganizersOfEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 2],
        );
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid1,
        );
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid2,
        );

        $this->subject->limitToEvent($eventUid);
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->countWithoutLimit(),
        );
    }

    /**
     * @test
     */
    public function limitToEventIgnoresOrganizerOfOtherEvent(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1],
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid1,
            $organizerUid,
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->subject->limitToEvent($eventUid2);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventSortsByRelationSorting(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 2],
        );
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid2,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid1,
        );

        $this->subject->limitToEvent($eventUid);
        $bag = $this->subject->build();
        $bag->rewind();

        self::assertEquals(
            $organizerUid2,
            $bag->current()->getUid(),
        );
    }
}
