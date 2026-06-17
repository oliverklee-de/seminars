<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model\Event;

use DateTime;
use OliverKlee\Seminars\Domain\Model\Event\TimeSlot;
use OliverKlee\Seminars\Domain\Model\Venue;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Event\TimeSlot
 */
final class TimeSlotTest extends UnitTestCase
{
    private TimeSlot $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TimeSlot();
    }

    /**
     * @test
     */
    public function setStartSetsStart(): void
    {
        $date = new DateTime('2025-04-02 10:00:00');

        $this->subject->setStart($date);

        self::assertSame($date, $this->subject->getStart());
    }

    /**
     * @test
     */
    public function setEndSetsEnd(): void
    {
        $date = new DateTime('2025-04-03 18:00:00');

        $this->subject->setEnd($date);

        self::assertSame($date, $this->subject->getEnd());
    }

    /**
     * @test
     */
    public function getVenueInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getVenue());
    }

    /**
     * @test
     */
    public function setVenueSetsVenue(): void
    {
        $venue = new Venue();

        $this->subject->setVenue($venue);

        self::assertSame($venue, $this->subject->getVenue());
    }

    /**
     * @test
     */
    public function getRoomInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getRoom());
    }

    /**
     * @test
     */
    public function setRoomSetsRoom(): void
    {
        $room = 'Leuchtturm';

        $this->subject->setRoom($room);

        self::assertSame($room, $this->subject->getRoom());
    }
}
