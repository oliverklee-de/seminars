<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\BagBuilder;

use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\EventBagBuilder
 */
final class EventBagBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private EventBagBuilder $subject;

    private TestingFramework $testingFramework;

    /**
     * @var int a UNIX timestamp in the past.
     */
    private int $past = 0;

    /**
     * @var int a UNIX timestamp in the future.
     */
    private int $future = 0;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));
        $this->future = (int)GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('date', 'timestamp') + 50;
        $this->past = (int)GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('date', 'timestamp') - 50;

        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->subject = new EventBagBuilder();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    // Tests for the basic builder functions.

    /**
     * @test
     */
    public function builderBuildsABag(): void
    {
        $bag = $this->subject->build();

        self::assertInstanceOf(AbstractBag::class, $bag);
    }

    /**
     * @test
     */
    public function builderFindsHiddenEventsInBackEndMode(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['hidden' => 1],
        );

        $this->subject->setBackEndMode();
        $bag = $this->subject->build();

        self::assertFalse(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function builderIgnoresTimedEventsByDefault(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'endtime' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - 1000,
            ],
        );
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function builderFindsTimedEventsInBackEndMode(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'endtime' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - 1000,
            ],
        );

        $this->subject->setBackEndMode();
        $bag = $this->subject->build();

        self::assertFalse(
            $bag->isEmpty(),
        );
    }

    ///////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain places
    ///////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToPlacesFindsEventsInOnePlace(): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToPlaces([$siteUid]);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToPlacesIgnoresEventsWithoutPlace(): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->subject->limitToPlaces([$siteUid]);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToPlacesFindsEventsInTwoPlaces(): void
    {
        $siteUid1 = $this->testingFramework->createRecord('tx_seminars_sites');
        $siteUid2 = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $siteUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid2,
            $siteUid2,
        );
        $this->subject->limitToPlaces([$siteUid1, $siteUid2]);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToPlacesWithEmptyPlacesArrayFindsAllEvents(): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->subject->limitToPlaces([$siteUid]);
        $this->subject->limitToPlaces();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToPlacesIgnoresEventsWithDifferentPlace(): void
    {
        $siteUid1 = $this->testingFramework->createRecord('tx_seminars_sites');
        $siteUid2 = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid1,
        );
        $this->subject->limitToPlaces([$siteUid2]);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToPlacesWithOnePlaceFindsEventInTwoPlaces(): void
    {
        $siteUid1 = $this->testingFramework->createRecord('tx_seminars_sites');
        $siteUid2 = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid2,
        );
        $this->subject->limitToPlaces([$siteUid1]);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToPlacesWithStringPlaceUidFindsMatchingEvent(): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars', ['place' => 1]);
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToPlaces([(string)$siteUid]);
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
        self::assertSame($eventUid, $bag->current()->getUid());
    }

    /**
     * @return array<string, array{0: int}>
     */
    public function nonPositiveIntegerDataProvider(): array
    {
        return [
            'zero' => [0],
            'negative int' => [-1],
        ];
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public function sqlCharacterDataProvider(): array
    {
        return [
            ';' => [';'],
            ',' => [','],
            '(' => ['('],
            ')' => [')'],
            'double quote' => ['"'],
            'single quote' => ["'"],
        ];
    }

    /**
     * @test
     *
     * @param int|non-empty-string $invalidUid
     *
     * @dataProvider nonPositiveIntegerDataProvider
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToPlacesSilentlyIgnoreInvalidUids($invalidUid): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars', ['place' => 1]);
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );

        $this->subject->limitToPlaces([$siteUid, $invalidUid]);
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
        self::assertSame($eventUid, $bag->current()->getUid());
    }

    //////////////////////////////////////
    // Tests concerning canceled events.
    //////////////////////////////////////

    /**
     * @test
     */
    public function builderFindsCanceledEventsByDefault(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => 1],
        );
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderIgnoresCanceledEventsWithHideCanceledEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => 1],
        );

        $this->subject->ignoreCanceledEvents();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function builderFindsConfirmedEventsWithHideCanceledEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CONFIRMED],
        );

        $this->subject->ignoreCanceledEvents();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderFindsCanceledEventsWithHideCanceledEventsDisabled(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => 1],
        );

        $this->subject->allowCanceledEvents();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderFindsCanceledEventsWithHideCanceledEventsEnabledThenDisabled(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => 1],
        );

        $this->subject->ignoreCanceledEvents();
        $this->subject->allowCanceledEvents();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderIgnoresCanceledEventsWithHideCanceledDisabledThenEnabled(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => 1],
        );

        $this->subject->allowCanceledEvents();
        $this->subject->ignoreCanceledEvents();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * validity checks
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameFailsWithEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The time-frame key "" is not valid.');

        // @phpstan-ignore-next-line We're explicitly testing for a contract violation here.
        $this->subject->setTimeFrame('');
    }

    /**
     * @test
     */
    public function setTimeFrameFailsWithInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The time-frame key "foo" is not valid.');

        // @phpstan-ignore-next-line We're explicitly testing for a contract violation here.
        $this->subject->setTimeFrame('foo');
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * past events
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFramePastFindsPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('past');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastFindsOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('past');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastIgnoresCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('past');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastIgnoresUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('past');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastIgnoresUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('past');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastIgnoresEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('past');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * past and current events
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFramePastAndCurrentFindsPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('pastAndCurrent');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastAndCurrentFindsOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('pastAndCurrent');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastAndCurrentFindsCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('pastAndCurrent');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastAndCurrentIgnoresUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('pastAndCurrent');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastAndCurrentIgnoresUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('pastAndCurrent');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFramePastAndCurrentIgnoresEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('pastAndCurrent');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * current events
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameCurrentIgnoresPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('current');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentIgnoresOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('current');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentFindsCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('current');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentIgnoresUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('current');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentIgnoresUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('current');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentIgnoresEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('current');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * current and upcoming events
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameCurrentAndUpcomingIgnoresPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('currentAndUpcoming');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentAndUpcomingIgnoresOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('currentAndUpcoming');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentAndUpcomingFindsCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('currentAndUpcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentAndUpcomingFindsUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('currentAndUpcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentAndUpcomingFindsUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('currentAndUpcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameCurrentAndUpcomingFindsEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('currentAndUpcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * upcoming events
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameUpcomingIgnoresPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('upcoming');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingIgnoresOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('upcoming');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingIgnoresCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('upcoming');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingFindsUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('upcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingFindsUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('upcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingFindsEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('upcoming');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * upcoming events with begin date
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameUpcomingWithBeginDateIgnoresPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('upcomingWithBeginDate');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingWithBeginDateIgnoresOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('upcomingWithBeginDate');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingWithBeginDateIgnoresCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('upcomingWithBeginDate');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingWithBeginDateFindsUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('upcomingWithBeginDate');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingWithBeginDateFindsUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('upcomingWithBeginDate');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameUpcomingWithBeginDateNotFindsEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('upcomingWithBeginDate');
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * events for which the registration deadline is not over yet
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverIgnoresPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'deadline_registration' => 0,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverIgnoresOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
                'deadline_registration' => 0,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverIgnoresCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'deadline_registration' => 0,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverFindsUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
                'deadline_registration' => 0,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverFindsUpcomingEventsWithUpcomingDeadline(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + 2 * Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
                'deadline_registration' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverIgnoresUpcomingEventsWithPassedDeadline(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
                'deadline_registration' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverFindsUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
                'deadline_registration' => 0,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameDeadlineNotOverFindsEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
                'deadline_registration' => 0,
            ],
        );

        $this->subject->setTimeFrame('deadlineNotOver');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * today
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameTodayFindsOpenEndedEventStartingToday(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayNotFindsOpenEndedEventStartingTomorrow(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayFindsEventStartingTodayEndingTomorrow(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayFindsEventStartingYesterdayEndingToday(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayFindsEventStartingYesterdayEndingTomorrow(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayIgnoresEventStartingLastWeekEndingYesterday(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayIgnoresEventStartingTomorrowEndingNextWeek(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameTodayIgnoresEventWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('today');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events in certain time-frames.
    //
    // * all events
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function setTimeFrameAllFindsPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('all');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameAllFindsOpenEndedPastEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_WEEK,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('all');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameAllFindsCurrentEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setTimeFrame('all');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameAllFindsUpcomingEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_WEEK,
            ],
        );

        $this->subject->setTimeFrame('all');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameAllFindsUpcomingOpenEndedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('all');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function setTimeFrameAllFindsEventsWithoutDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );

        $this->subject->setTimeFrame('all');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    ////////////////////////////////////////////////////////////////
    // Tests for limiting the bag to events of certain event types
    ////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function skippingLimitToEventTypesResultsInAllEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEmptyTypeUidResultsInAllEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );

        $this->subject->limitToEventTypes();
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEmptyTypeUidAfterLimitToNotEmptyTypesResultsInAllEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $this->subject->limitToEventTypes();
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesCanResultInOneEvent(): void
    {
        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesCanResultInTwoEvents(): void
    {
        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesWillExcludeUnassignedEvents(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesWillExcludeEventsOfOtherTypes(): void
    {
        $typeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid1,
            ],
        );

        $typeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid2,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid1]);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid1,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesResultsInAnEmptyBagIfThereAreNoMatches(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        $typeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid1,
            ],
        );

        $typeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );

        $this->subject->limitToEventTypes([$typeUid2]);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesFindsDateRecordForTopic(): void
    {
        $typeUid = $this->testingFramework->createRecord('tx_seminars_event_types');
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'event_type' => $typeUid,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $bag = $this->subject->build();

        self::assertSame(2, $bag->count());
        self::assertSame($topicUid . ',' . $dateUid, $bag->getUids());
    }

    /**
     * @test
     */
    public function limitToEventTypesFindsDateRecordForSingle(): void
    {
        $typeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesIgnoresTopicOfDateRecord(): void
    {
        $typeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid1,
            ],
        );

        $typeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'event_type' => $typeUid2,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid2]);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesCanFindEventsFromMultipleTypes(): void
    {
        $typeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid1,
            ],
        );

        $typeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $typeUid2,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid1, $typeUid2]);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTypesAndTopicsFindsTopicOfThisType(): void
    {
        $typeUid = $this->testingFramework->createRecord('tx_seminars_event_types');
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'event_type' => $typeUid,
            ],
        );

        $this->subject->limitToEventTypes([$typeUid]);
        $this->subject->limitToTopicRecords();

        /** @var EventBag $bag */
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
        self::assertSame((string)$topicUid, $bag->getUids());
    }

    /**
     * @test
     *
     * @param int|non-empty-string $invalidUid
     *
     * @dataProvider nonPositiveIntegerDataProvider
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToEventTypesSilentlyIgnoreInvalidUids($invalidUid): void
    {
        $typeUid = $this->testingFramework->createRecord('tx_seminars_event_types');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT, 'event_type' => $typeUid],
        );

        $this->subject->limitToEventTypes([$typeUid, $invalidUid]);
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
    }

    //////////////////////////////
    // Tests for limitToCities()
    //////////////////////////////

    /**
     * @test
     */
    public function limitToCitiesFindsEventsInOneCity(): void
    {
        $siteUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToCities(['test city 1']);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesIgnoresEventsInOtherCity(): void
    {
        $siteUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 2'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToCities(['test city 1']);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesWithTwoCitiesFindsEventsEachInOneOfBothCities(): void
    {
        $siteUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $siteUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 2'],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $siteUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid2,
            $siteUid2,
        );
        $this->subject->limitToCities(['test city 1', 'test city 2']);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesWithEmptyCitiesArrayFindsEventsWithCities(): void
    {
        $siteUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToCities(['test city 2']);
        $this->subject->limitToCities();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesIgnoresEventsWithDifferentCity(): void
    {
        $siteUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToCities(['test city 2']);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesIgnoresEventWithPlaceWithoutCity(): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid,
        );
        $this->subject->limitToCities(['test city 1']);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesWithTwoCitiesFindsOneEventInBothCities(): void
    {
        $siteUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $siteUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 2'],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $siteUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $siteUid2,
        );
        $this->subject->limitToCities(['test city 1', 'test city 2']);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesWithOneCityFindsEventInTwoCities(): void
    {
        $siteUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $siteUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 2'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 2],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $siteUid2,
        );
        $this->subject->limitToCities(['test city 1']);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToCitiesWithTwoCitiesOneDifferentFindsEventInOneOfTheCities(): void
    {
        $siteUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 1'],
        );
        $siteUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'test city 3'],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 2],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $siteUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $siteUid2,
        );
        $this->subject->limitToCities(['test city 2', 'test city 3']);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     *
     * @param non-empty-string $invalidName
     *
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToCitiesSilentlyIgnoreInvalidCityNames(string $invalidName): void
    {
        $siteUid = $this->testingFramework->createRecord('tx_seminars_sites', ['city' => 'test city 1']);
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars', ['place' => 1]);
        $this->testingFramework->createRelation('tx_seminars_seminars_place_mm', $eventUid, $siteUid);

        $this->subject->limitToCities(['test city 1', $invalidName]);
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
        self::assertSame($eventUid, $bag->current()->getUid());
    }

    ////////////////////////////////////
    // Tests for limitToTopicRecords()
    ////////////////////////////////////

    /**
     * @test
     */
    public function limitToTopicRecordsFindsTopicEventRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->subject->limitToTopicRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToTopicRecordsIgnoresSingleEventRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );
        $this->subject->limitToTopicRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToTopicRecordsIgnoresEventDateRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_DATE],
        );
        $this->subject->limitToTopicRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    //////////////////////////////////////////
    // Tests for removeLimitToTopicRecords()
    //////////////////////////////////////////

    /**
     * @test
     */
    public function removeLimitToTopicRecordsFindsSingleEventRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );
        $this->subject->limitToTopicRecords();
        $this->subject->removeLimitToTopicRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function removeLimitToTopicRecordsFindsEventDateRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_DATE],
        );
        $this->subject->limitToTopicRecords();
        $this->subject->removeLimitToTopicRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    ////////////////////////////////////////////
    // Tests for limitToDateAndSingleRecords()
    ////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToDateAndSingleRecordsFindsDateRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_DATE],
        );
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToDateAndSingleRecordsFindsSingleRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToDateAndSingleRecordsIgnoresTopicRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function removeLimitToDateAndSingleRecordsFindsTopicRecords(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->subject->limitToDateAndSingleRecords();
        $this->subject->removeLimitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    ////////////////////////////////////
    // Tests for limitToEventManager()
    ////////////////////////////////////

    /**
     * @test
     */
    public function limitToEventManagerWithNegativeFeUserUidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The parameter $feUserUid must be >= 0.');

        // @phpstan-ignore-next-line We are explicitly testing with a contract violation here.
        $this->subject->limitToEventManager(-1);
    }

    /**
     * @test
     */
    public function limitToEventManagerWithPositiveFeUserUidFindsEventsWithEventManager(): void
    {
        $feUserUid = $this->testingFramework->createFrontEndUser();
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['vips' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_feusers_mm',
            $eventUid,
            $feUserUid,
        );

        $this->subject->limitToEventManager($feUserUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventManagerWithPositiveFeUserUidIgnoresEventsWithoutEventManager(): void
    {
        $feUserUid = $this->testingFramework->createFrontEndUser();
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->subject->limitToEventManager($feUserUid);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventManagerWithZeroFeUserUidFindsEventsWithoutEventManager(): void
    {
        $feUserUid = $this->testingFramework->createFrontEndUser();
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->subject->limitToEventManager($feUserUid);
        $this->subject->limitToEventManager(0);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /////////////////////////////////////
    // Tests for limitToEventsNextDay()
    /////////////////////////////////////

    /**
     * @test
     */
    public function limitToEventsNextDayFindsEventsNextDay(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => Time::SECONDS_PER_DAY, 'end_date' => Time::SECONDS_PER_DAY + 60 * 60],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 2 * Time::SECONDS_PER_DAY,
                'end_date' => 60 * 60 + 2 * Time::SECONDS_PER_DAY,
            ],
        );
        $event = new LegacyEvent($eventUid1);
        $this->subject->limitToEventsNextDay($event);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid2,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsNextDayIgnoresEarlierEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => Time::SECONDS_PER_DAY, 'end_date' => Time::SECONDS_PER_DAY + 60 * 60],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 0,
                'end_date' => 60 * 60,
            ],
        );
        $event = new LegacyEvent($eventUid);
        $this->subject->limitToEventsNextDay($event);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsNextDayIgnoresEventsLaterThanOneDay(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => Time::SECONDS_PER_DAY, 'end_date' => Time::SECONDS_PER_DAY + 60 * 60],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => 3 * Time::SECONDS_PER_DAY,
                'end_date' => 60 * 60 + 3 * Time::SECONDS_PER_DAY,
            ],
        );
        $event = new LegacyEvent($eventUid);
        $this->subject->limitToEventsNextDay($event);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsNextDayWithEventWithEmptyEndDateThrowsException(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );
        $this->expectExceptionMessage(
            'The event object given in the first parameter $event must ' .
            'have an end date set.',
        );

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->subject->limitToEventsNextDay(
            new LegacyEvent($eventUid),
        );
    }

    //////////////////////////////////////////////
    // Tests for limitToOtherDatesForThisTopic()
    //////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToOtherDatesForTopicFindsOtherDateForTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $dateUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $dateUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $date = new LegacyEvent($dateUid1);
        $this->subject->limitToOtherDatesForTopic($date);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid2,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToOtherDatesForTopicWithTopicRecordFindsAllDatesForTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $topic = new LegacyEvent($topicUid);
        $this->subject->limitToOtherDatesForTopic($topic);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToOtherDatesForTopicWithSingleEventRecordThrowsException(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );
        $this->expectExceptionMessage(
            'The first parameter $event must be either a date or a topic record.',
        );

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );
        $event = new LegacyEvent($eventUid);
        $this->subject->limitToOtherDatesForTopic($event);
    }

    /**
     * @test
     */
    public function limitToOtherDatesForTopicIgnoresDateForOtherTopic(): void
    {
        $topicUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $topicUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $dateUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid1,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid2,
            ],
        );
        $date = new LegacyEvent($dateUid1);
        $this->subject->limitToOtherDatesForTopic($date);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToOtherDatesForTopicIgnoresSingleEventRecordWithTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'topic' => $topicUid,
            ],
        );
        $date = new LegacyEvent($dateUid);
        $this->subject->limitToOtherDatesForTopic($date);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function removeLimitToOtherDatesForTopicRemovesLimitAndFindsAllDateAndTopicRecords(): void
    {
        $topicUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $topicUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $dateUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid1,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid2,
            ],
        );
        $date = new LegacyEvent($dateUid1);
        $this->subject->limitToOtherDatesForTopic($date);
        $this->subject->removeLimitToOtherDatesForTopic();
        $bag = $this->subject->build();

        self::assertSame(
            4,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function removeLimitToOtherDatesForTopicFindsSingleEventRecords(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'topic' => $topicUid,
            ],
        );
        $date = new LegacyEvent($dateUid);
        $this->subject->limitToOtherDatesForTopic($date);
        $this->subject->removeLimitToOtherDatesForTopic();
        $bag = $this->subject->build();

        self::assertSame(
            3,
            $bag->count(),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests for limitToFullTextSearch() for single event records
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToFullTextSearchWithTwoCommasAsSearchWordFindsAllEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch(',,');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchWithTwoSearchWordsSeparatedByTwoSpacesFindsEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado  paprika');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchWithTwoCommasSeparatedByTwoSpacesFindsAllEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch(',  ,');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchWithTooShortSearchWordFindsAllEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch('o');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInAccreditationNumber(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'accreditation_number' => 'avocado paprika event',
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInAccreditationNumber(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'accreditation_number' => 'paprika event',
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTitle(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTitle(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInSubtitle(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['subtitle' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInSubtitle(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['subtitle' => 'paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInDescription(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['description' => 'avocado paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInDescription(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['description' => 'paprika event'],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInSpeakerTitle(): void
    {
        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            ['title' => 'avocado paprika speaker'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'speakers' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $eventUid,
            $speakerUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInSpeakerTitle(): void
    {
        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            ['title' => 'paprika speaker'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'speakers' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $eventUid,
            $speakerUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInPlaceTitle(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'avocado paprika place'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInPlaceTitle(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'paprika place'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInPlaceCity(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'avocado paprika city'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInPlaceCity(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'paprika city'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInEventTypeTitle(): void
    {
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'avocado paprika event type'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'event_type' => $eventTypeUid,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInEventTypeTitle(): void
    {
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'paprika event type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'event_type' => $eventTypeUid,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInCategoryTitle(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'avocado paprika category'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'categories' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInCategoryTitle(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'paprika category'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'categories' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchWithTwoSearchWordsSeparatedBySpaceFindsTwoEventsWithSearchWordsInTitle(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado event paprika'],
        );
        $this->subject->limitToFullTextSearch('avocado paprika');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchWithTwoSearchWordsSeparatedByCommaFindsTwoEventsWithSearchWordsInTitle(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'avocado event paprika'],
        );
        $this->subject->limitToFullTextSearch('avocado,paprika');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTargetGroupTitle(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['title' => 'avocado paprika place'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'target_groups' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_target_groups_mm',
            $eventUid,
            $targetGroupUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTargetGroupTitle(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['title' => 'paprika place'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'target_groups' => 1,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_target_groups_mm',
            $eventUid,
            $targetGroupUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    ////////////////////////////////////////////////////////////////
    // Tests for limitToFullTextSearch() for topic event records
    ////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTopicTitle(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'avocado paprika event',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTopicTitle(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'paprika event',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTopicSubtitle(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'subtitle' => 'avocado paprika event',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTopicSubtitle(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'subtitle' => 'paprika event',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTopicDescription(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'description' => 'avocado paprika event',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTopicDescription(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'description' => 'paprika event',
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTopicCategoryTitle(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'avocado paprika category'],
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'categories' => 1,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $topicUid,
            $categoryUid,
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTopicCategoryTitle(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'paprika category'],
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'categories' => 1,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $topicUid,
            $categoryUid,
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventWithSearchWordInTopicEventTypeTitle(): void
    {
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'avocado paprika event type'],
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'event_type' => $eventTypeUid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventWithoutSearchWordInTopicEventTypeTitle(): void
    {
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'paprika event type'],
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'event_type' => $eventTypeUid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $this->subject->limitToDateAndSingleRecords();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    ///////////////////////////////////////////////////////////////
    // Tests for limitToFullTextSearch() for event date records
    ///////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventDateWithSearchWordInAccreditationNumber(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'accreditation_number' => 'avocado paprika event',
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventDateWithoutSearchWordInAccreditationNumber(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'accreditation_number' => 'paprika event',
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventDateWithSearchWordInSpeakerTitle(): void
    {
        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            ['title' => 'avocado paprika speaker'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'speakers' => 1,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $eventUid,
            $speakerUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventDateWithoutSearchWordInSpeakerTitle(): void
    {
        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            ['title' => 'paprika speaker'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'speakers' => 1,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $eventUid,
            $speakerUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventDateWithSearchWordInPlaceTitle(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'avocado paprika place'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventDateWithoutSearchWordInPlaceTitle(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'paprika place'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchFindsEventDateWithSearchWordInPlaceCity(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'avocado paprika city'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $eventUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToFullTextSearchIgnoresEventDateWithoutSearchWordInPlaceCity(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'paprika city'],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'place' => 1,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->limitToFullTextSearch('avocado');
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    ///////////////////////////////////////////
    // Tests concerning limitToRequiredEvents
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function limitToRequiredEventsCanFindOneRequiredEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['requirements' => 1],
        );
        $requiredEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $eventUid,
            $requiredEventUid,
        );
        $this->subject->limitToRequiredEventTopics($eventUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToRequiredEventsCanFindTwoRequiredEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['requirements' => 1],
        );
        $requiredEventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $requiredEventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $eventUid,
            $requiredEventUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $eventUid,
            $requiredEventUid2,
        );

        $this->subject->limitToRequiredEventTopics($eventUid);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToRequiredEventsFindsOnlyRequiredEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['requirements' => 1],
        );
        $requiredEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $dependingEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $eventUid,
            $requiredEventUid,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid,
            $eventUid,
        );

        $this->subject->limitToRequiredEventTopics($eventUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertNotEquals(
            $dependingEventUid,
            $bag->current()->getUid(),
        );
    }

    ///////////////////////////////////////////
    // Tests concerning limitToDependingEvents
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function limitToDependingEventsCanFindOneDependingEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['requirements' => 1],
        );
        $dependingEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid,
            $eventUid,
        );
        $this->subject->limitToDependingEventTopics($eventUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToDependingEventsCanFindTwoDependingEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['requirements' => 1],
        );
        $dependingEventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $dependingEventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid1,
            $eventUid,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid2,
            $eventUid,
        );

        $this->subject->limitToDependingEventTopics($eventUid);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToDependingEventsFindsOnlyDependingEvents(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['requirements' => 1],
        );
        $dependingEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $requiredEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid,
            $eventUid,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $eventUid,
            $requiredEventUid,
        );

        $this->subject->limitToDependingEventTopics($eventUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertNotEquals(
            $requiredEventUid,
            $bag->current()->getUid(),
        );
    }

    //////////////////////////////////////////////////////
    // Test concerning limitToCancelationReminderNotSent
    //////////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToCancelationDeadlineReminderNotSentFindsEventWithCancelationReminderSentFlagFalse(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelation_deadline_reminder_sent' => 0],
        );

        $this->subject->limitToCancelationDeadlineReminderNotSent();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToCancelationDeadlineReminderNotSentNotFindsEventWithCancelationReminderSentFlagTrue(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelation_deadline_reminder_sent' => 1],
        );

        $this->subject->limitToCancelationDeadlineReminderNotSent();
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    //////////////////////////////////////////////////////////
    // Test concerning limitToEventTakesPlaceReminderNotSent
    //////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToEventTakesPlaceReminderNotSentFindsEventWithConfirmationInformationSentFlagFalse(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_takes_place_reminder_sent' => 0],
        );

        $this->subject->limitToEventTakesPlaceReminderNotSent();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventTakesPlaceReminderNotSentNotFindsEventWithConfirmationInformationSentFlagTrue(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_takes_place_reminder_sent' => 1],
        );

        $this->subject->limitToEventTakesPlaceReminderNotSent();
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    ///////////////////////////////////
    // Tests concerning limitToStatus
    ///////////////////////////////////

    /**
     * @test
     */
    public function limitToStatusFindsEventWithStatusCanceledIfLimitIsStatusCanceled(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CANCELED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_CANCELED);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusNotFindsEventWithStatusPlannedIfLimitIsStatusCanceled(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_PLANNED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_CANCELED);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusNotFindsEventWithStatusConfirmedIfLimitIsStatusCanceled(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CONFIRMED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_CANCELED);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusFindsEventWithStatusConfirmedIfLimitIsStatusConfirmed(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CONFIRMED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_CONFIRMED);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusNotFindsEventWithStatusPlannedIfLimitIsStatusConfirmed(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_PLANNED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_CONFIRMED);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusNotFindsEventWithStatusCanceledIfLimitIsStatusConfirmed(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CANCELED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_CONFIRMED);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusFindsEventWithStatusPlannedIfLimitIsStatusPlanned(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_PLANNED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_PLANNED);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusNotFindsEventWithStatusConfirmedIfLimitIsStatusPlanned(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CONFIRMED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_PLANNED);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToStatusNotFindsEventWithStatusCanceledIfLimitIsStatusPlanned(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['cancelled' => EventInterface::STATUS_CANCELED],
        );

        $this->subject->limitToStatus(EventInterface::STATUS_PLANNED);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    //////////////////////////////////////////////////
    // Tests concerning limitToDaysBeforeBeginDate
    //////////////////////////////////////////////////

    /**
     * @test
     */
    public function testlimitToDaysBeforeBeginDateFindsEventWithFutureBeginDateWithinProvidedDays(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->limitToDaysBeforeBeginDate(2);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function testlimitToDaysBeforeBeginDateFindsEventWithPastBeginDateWithinProvidedDays(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->limitToDaysBeforeBeginDate(3);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function testlimitToDaysBeforeBeginDateNotFindsEventWithFutureBeginDateOutOfProvidedDays(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + (2 * Time::SECONDS_PER_DAY),
            ],
        );

        $this->subject->limitToDaysBeforeBeginDate(1);
        $bag = $this->subject->build();

        self::assertSame(
            0,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function testlimitToDaysBeforeBeginDateFindsEventWithPastBeginDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - (2 * Time::SECONDS_PER_DAY),
            ],
        );

        $this->subject->limitToDaysBeforeBeginDate(1);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function testlimitToDaysBeforeBeginDateFindsEventWithNoBeginDate(): void
    {
        $this->testingFramework->createRecord('tx_seminars_seminars');

        $this->subject->limitToDaysBeforeBeginDate(1);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    // Tests concerning limitToEarliestBeginOrEndDate

    /**
     * @test
     */
    public function limitToEarliestBeginOrEndDateForEventWithoutBeginDateFindsThisEvent(): void
    {
        $this->testingFramework->createRecord('tx_seminars_seminars');
        $this->subject->limitToEarliestBeginOrEndDate(42);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEarliestBeginOrEndDateForEventWithBeginDateEqualToGivenTimestampFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 42],
        );
        $this->subject->limitToEarliestBeginOrEndDate(42);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEarliestBeginOrEndDateForEventWithGreaterBeginDateThanGivenTimestampFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 42],
        );
        $this->subject->limitToEarliestBeginOrEndDate(21);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEarliestBeginOrEndDateForEventWithBeginDateLowerThanGivenTimestampDoesNotFindThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 42],
        );
        $this->subject->limitToEarliestBeginOrEndDate(84);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEarliestBeginOrEndDateForZeroGivenAsTimestampUnsetsFilter(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 21],
        );

        $this->subject->limitToEarliestBeginOrEndDate(42);

        $this->subject->limitToEarliestBeginOrEndDate(0);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEarliestBeginOrEndDateForFindsEventStartingBeforeAndEndingAfterDeadline(): void
    {
        $this->testingFramework->createRecord('tx_seminars_seminars', ['begin_date' => 8, 'end_date' => 10]);
        $this->subject->limitToEarliestBeginOrEndDate(9);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    // Tests concerning limitToLatestBeginOrEndDate

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventWithoutDateDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord('tx_seminars_seminars');
        $this->subject->limitToLatestBeginOrEndDate(42);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventBeginDateEqualToGivenTimestampFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 42],
        );
        $this->subject->limitToLatestBeginOrEndDate(42);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventWithBeginDateAfterGivenTimestampDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 42],
        );
        $this->subject->limitToLatestBeginOrEndDate(21);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventBeginDateBeforeGivenTimestampFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['begin_date' => 42],
        );
        $this->subject->limitToLatestBeginOrEndDate(84);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventEndDateEqualToGivenTimestampFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['end_date' => 42],
        );
        $this->subject->limitToLatestBeginOrEndDate(42);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventWithEndDateAfterGivenTimestampDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['end_date' => 42],
        );
        $this->subject->limitToLatestBeginOrEndDate(21);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForEventEndDateBeforeGivenTimestampFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['end_date' => 42],
        );
        $this->subject->limitToLatestBeginOrEndDate(84);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToLatestBeginOrEndDateForZeroGivenUnsetsTheFilter(): void
    {
        $this->testingFramework->createRecord('tx_seminars_seminars');

        $this->subject->limitToLatestBeginOrEndDate(42);
        $this->subject->limitToLatestBeginOrEndDate(0);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    ///////////////////////////////////////
    // Tests concerning showHiddenRecords
    ///////////////////////////////////////

    /**
     * @test
     */
    public function showHiddenRecordsForHiddenEventFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['hidden' => 1],
        );

        $this->subject->showHiddenRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function showHiddenRecordsForVisibleEventFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['hidden' => 0],
        );

        $this->subject->showHiddenRecords();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    ////////////////////////////////////////////////
    // Tests concerning limitToEventsWithVacancies
    ////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithNoRegistrationNeededFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['needs_registration' => 0],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithUnlimitedVacanciesFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['needs_registration' => 1, 'attendees_max' => 0],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventNoVacanciesAndQueueDoesNotFindThisEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
                'queue_size' => 1,
            ],
        );

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'seats' => 1],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithNoVacanciesDoesNotFindThisEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['needs_registration' => 1, 'attendees_max' => 1],
        );

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'seats' => 1],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithNoVacanciesThroughOfflineRegistrationsDoesNotFindThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'needs_registration' => 1,
                'attendees_max' => 10,
                'offline_attendees' => 10,
            ],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithNoVacanciesThroughRegistrationsWithMultipleSeatsNotFindsIt(
    ): void {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'needs_registration' => 1,
                'attendees_max' => 10,
            ],
        );

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'seats' => 10],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithNoVacanciesThroughRegularAndOfflineRegistrationsNotFindsIt(
    ): void {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'needs_registration' => 1,
                'attendees_max' => 10,
                'offline_attendees' => 5,
            ],
        );

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $eventUid, 'seats' => 5],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithVacanciesAndNoAttendeesFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['needs_registration' => 1, 'attendees_max' => 10],
        );

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    ///////////////////////////////////////
    // Tests concerning limitToOrganizers
    ///////////////////////////////////////

    /**
     * @test
     */
    public function limitToOrganizersForOneProvidedOrganizerAndEventWithThisOrganizerFindsThisEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        $this->subject->limitToOrganizers((string)$organizerUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToOrganizersForOneProvidedOrganizerAndEventWithoutOrganizerDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );

        $this->subject->limitToOrganizers((string)$organizerUid);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToOrganizersForOneProvidedOrganizerAndEventWithOtherOrganizerDoesNotFindThisEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid1,
            'organizers',
        );

        $this->subject->limitToOrganizers((string)$organizerUid2);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToOrganizersForTwoProvidedOrganizersAndEventWithFirstOrganizerFindsThisEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid1,
            'organizers',
        );

        $this->subject->limitToOrganizers($organizerUid1 . ',' . $organizerUid2);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToOrganizersForOneExistingAndOneInexistentOrganizerFindsEventWithExistingOrganizer(): void
    {
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $organizerUid1 = $this->testingFramework->createRecord('tx_seminars_organizers');
        $this->testingFramework
            ->createRelationAndUpdateCounter('tx_seminars_seminars', $eventUid, $organizerUid1, 'organizers');

        $this->subject->limitToOrganizers($organizerUid1 . ',' . ($organizerUid1 + 1));
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
    }

    /**
     * @test
     */
    public function limitToOrganizersForProvidedOrganizerAndTwoEventsWithThisOrganizerFindsTheseEvents(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid1,
            $organizerUid,
            'organizers',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid2,
            $organizerUid,
            'organizers',
        );

        $this->subject->limitToOrganizers((string)$organizerUid);
        $bag = $this->subject->build();

        self::assertSame(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToOrganizersForProvidedOrganizerAndTopicWithOrganizerReturnsTheTopicsDate(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $organizerUid,
            'organizers',
        );

        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );

        $this->subject->limitToOrganizers((string)$organizerUid);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToOrganizersForNoProvidedOrganizerFindsEventWithOrganizer(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        $this->subject->limitToOrganizers('');
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     *
     * @param int|non-empty-string $invalidUid
     *
     * @dataProvider nonPositiveIntegerDataProvider
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToOrganizersSilentlyIgnoreInvalidUids($invalidUid): void
    {
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $organizerUid1 = $this->testingFramework->createRecord('tx_seminars_organizers');
        $this->testingFramework
            ->createRelationAndUpdateCounter('tx_seminars_seminars', $eventUid, $organizerUid1, 'organizers');

        $this->subject->limitToOrganizers($organizerUid1 . ',' . $invalidUid);
        $bag = $this->subject->build();

        self::assertSame(1, $bag->count());
    }

    ////////////////////////////////
    // Tests concerning limitToAge
    ////////////////////////////////

    /**
     * @test
     */
    public function limitToAgeForAgeWithinEventsAgeRangeFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 50],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(6);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForAgeEqualToLowerLimitOfAgeRangeFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 15, 'maximum_age' => 50],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForAgeEqualToHigherLimitOfAgeRangeFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 15],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForNoLowerLimitAndAgeLowerThanMaximumAgeFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 0, 'maximum_age' => 50],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForAgeHigherThanMaximumAgeDoesNotFindThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 0, 'maximum_age' => 50],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(51);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForNoHigherLimitAndAgeHigherThanMinimumAgeFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 0],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForAgeLowerThanMinimumAgeFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 0],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(4);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForEventWithoutTargetGroupAndAgeProvidedFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->subject->limitToAge(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForEventWithTargetGroupWithNoLimitsFindsThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForEventWithTwoTargetGroupOneWithMatchingRangeAndOneWithoutMatchingRangeFindsThisEvent(
    ): void {
        $targetGroupUid1 = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 20, 'maximum_age' => 50],
        );
        $targetGroupUid2 = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 20],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid1,
            'target_groups',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid2,
            'target_groups',
        );

        $this->subject->limitToAge(21);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForEventWithTwoTargetGroupBothWithMatchingRangesFindsThisEventOnlyOnce(): void
    {
        $targetGroupUid1 = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 50],
        );
        $targetGroupUid2 = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 20],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid1,
            'target_groups',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid2,
            'target_groups',
        );

        $this->subject->limitToAge(6);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToAgeForAgeZeroGivenFindsEventWithAgeLimits(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 15],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->limitToAge(0);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /////////////////////////////////////////
    // Tests concerning limitToMaximumPrice
    /////////////////////////////////////////

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithRegularPriceLowerThanMaximumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 42],
        );

        $this->subject->limitToMaximumPrice(43);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithRegularPriceZeroFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 0],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithRegularPriceEqualToMaximumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 50],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithRegularPriceHigherThanMaximumDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 51],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithSpecialPriceLowerThanMaximumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 51, 'price_special' => 49],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithSpecialPriceEqualToMaximumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 43, 'price_special' => 42],
        );

        $this->subject->limitToMaximumPrice(42);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithSpecialPriceHigherThanMaximumDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 43, 'price_special' => 43],
        );

        $this->subject->limitToMaximumPrice(42);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForTopicWithRegularPriceLowerThanMaximumFindsTheDateForThisEvent(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 49,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
        self::assertSame(
            $dateUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForTopicWithRegularPriceHigherThanMaximumDoesNotFindTheDateForThisEvent(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInFutureAndRegularEarlyPriceLowerThanMaximumFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_regular_early' => 49,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInFutureAndRegularEarlyPriceEqualToMaximumFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_regular_early' => 50,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithDeadlineInFutureAndRegularEarlyPriceHigherThanMaxNotFindsIt(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_regular_early' => 51,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInPastAndRegularEarlyPriceLowerThanMaxNotFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_regular_early' => 49,
                'deadline_early_bird' => $this->past,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInFutureAndSpecialEarlyPriceLowerThanMaxFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_special_early' => 49,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInFutureAndSpecialEarlyPriceEqualToMaxFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_special_early' => 50,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithDeadlineInFutureAndSpecialEarlyPriceHigherThanMaxNotFindsIt(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_special_early' => 51,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForFutureEarlyBirdDeadlineAndNoEarlyBirdPriceDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInPastAndSpecialEarlyPriceLowerThanMaxNotFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_special_early' => 49,
                'deadline_early_bird' => $this->past,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceWithDeadlineInFutureAndRegularEarlyHigherThanAndRegularLowerThanMaxiNotFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 49,
                'price_regular_early' => 51,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceWithDeadlineInFutureAndNoSpecialEarlyAndRegularPriceLowerThanMaximumFindsIt(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 49,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInFutureAndNoEarlySpecialPriceAndSpecialPriceLowerThanMaximumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 51,
                'price_special' => 49,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForEventWithEarlyBirdDeadlineInFutureAndNoRegularEarlyPriceAndRegularPriceLowerThanMaximumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 49,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMaximumPrice(50);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMaximumPriceForZeroGivenFindsEventWithNonZeroPrice(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 15,
            ],
        );

        $this->subject->limitToMaximumPrice(0);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /////////////////////////////////////////
    // Tests concerning limitToMinimumPrice
    /////////////////////////////////////////

    /**
     * @test
     */
    public function limitToMinimumPriceForEventWithRegularPriceLowerThanMinimumDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 14],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForPriceGivenAndEventWithoutPricesDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord('tx_seminars_seminars');

        $this->subject->limitToMinimumPrice(16);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEventWithRegularPriceEqualToMinimumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 15],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEventWithRegularPriceGreaterThanMinimumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 16],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEventWithSpecialPriceGreaterThanMinimumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_special' => 16],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEventWithSpecialPriceEqualToMinimumFindsThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_special' => 15],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEventWithSpecialPriceLowerThanMinimumDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_special' => 14],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndRegularEarlyPriceZeroAndRegularPriceHigherThanMinimumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular' => 16,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureNoPriceSetDoesNotFindThisEvent(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['deadline_early_bird' => $this->future],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndRegularEarlyPriceLowerThanMinimumDoesNotFindThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular_early' => 14,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndRegularEarlyPriceEqualToMinimumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular_early' => 15,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndRegularEarlyPriceHigherThanMinimumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular_early' => 16,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndSpecialEarlyPriceZeroAndSpecialPriceHigherThanMinimumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_special' => 16,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndSpecialEarlyPriceLowerThanMinimumDoesNotFindThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_special_early' => 14,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndSpecialEarlyPriceEqualToMinimumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_special_early' => 15,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInFutureAndSpecialEarlyPriceHigherThanMinimumFindsThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_special_early' => 16,
                'deadline_early_bird' => $this->future,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInPastAndRegularEarlyPriceHigherThanMinimumDoesNotFindThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_regular_early' => 16,
                'deadline_early_bird' => $this->past,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForEarlyBirdDeadlineInPastAndSpecialEarlyPriceHigherThanMinimumDoesNotFindThisEvent(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'price_special_early' => 16,
                'deadline_early_bird' => $this->past,
            ],
        );

        $this->subject->limitToMinimumPrice(15);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToMinimumPriceForMinimumPriceZeroFindsEventWithRegularPrice(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['price_regular' => 16],
        );

        $this->subject->limitToMinimumPrice(0);
        $bag = $this->subject->build();

        self::assertSame(
            1,
            $bag->count(),
        );
    }
}
