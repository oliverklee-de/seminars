<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingTimeSpan;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 */
final class AbstractTimeSpanTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private TestingTimeSpan $subject;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->subject = new TestingTimeSpan();
    }

    /**
     * @test
     */
    public function isAbstractModel(): void
    {
        self::assertInstanceOf(AbstractModel::class, $this->subject);
    }

    /**
     * @test
     */
    public function fromDataCreatesInstanceOfSubclass(): void
    {
        $result = TestingTimeSpan::fromData([]);

        self::assertInstanceOf(TestingTimeSpan::class, $result);
    }

    // Test for getting the begin and end date.

    /**
     * @test
     */
    public function hasDateInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasDate());
    }

    /**
     * @test
     */
    public function getBeginDateAsTimestampInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getBeginDateAsTimestamp());
    }

    /**
     * @test
     */
    public function hasBeginDateInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasBeginDate());
    }

    /**
     * @test
     */
    public function getEndDateAsTimestampInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getEndDateAsTimestamp());
    }

    /**
     * @test
     */
    public function hasEndDateInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasEndDate());
    }

    /**
     * @test
     */
    public function getEndDateAsTimestampEvenIfOpenEndedInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getEndDateAsTimestampEvenIfOpenEnded());
    }

    /**
     * @test
     */
    public function setBeginDateAndTimeSetsBeginDate(): void
    {
        $value = 42;
        $this->subject->setBeginDateAndTime($value);

        self::assertSame($value, $this->subject->getBeginDateAsTimestamp());
    }

    /**
     * @test
     */
    public function setBeginDateAndTimeMarksBeginDateAsSet(): void
    {
        $this->subject->setBeginDateAndTime(42);

        self::assertTrue($this->subject->hasBeginDate());
    }

    /**
     * @test
     */
    public function hasDateAfterSettingBeginDateReturnsTrue(): void
    {
        $this->subject->setBeginDateAndTime(42);

        self::assertTrue($this->subject->hasDate());
    }

    /**
     * @test
     */
    public function setEndDateAndTimeSetsEndDate(): void
    {
        $this->subject->setEndDateAndTime(42);

        self::assertSame(42, $this->subject->getEndDateAsTimestamp());
    }

    /**
     * @test
     */
    public function setEndDateAndTimeMarksEndDateAsSet(): void
    {
        $this->subject->setEndDateAndTime(42);

        self::assertTrue($this->subject->hasEndDate());
    }

    /**
     * @test
     */
    public function setEndDateAndTimeWithoutBegingDateNotMarksDateAsSet(): void
    {
        $this->subject->setEndDateAndTime(42);

        self::assertFalse($this->subject->hasDate());
    }

    /**
     * @test
     */
    public function getEndDateAsTimestampEvenIfOpenEndedAfterSettingEndDateOnlyReturnsZero(): void
    {
        $this->subject->setEndDateAndTime(42);

        self::assertSame(0, $this->subject->getEndDateAsTimestampEvenIfOpenEnded());
    }

    // Test for getting the time.

    /**
     * @test
     */
    public function hasTimeInitiallyReturnsZero(): void
    {
        self::assertFalse($this->subject->hasTime());
    }

    /**
     * @test
     */
    public function hasEndTimeWithEndTimeAtMidnightReturnsFalse(): void
    {
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        self::assertFalse($this->subject->hasEndTime());
    }

    /**
     * @test
     */
    public function hasEndTimeWithEndTimeDuringTheDayReturnsTrue(): void
    {
        $this->subject->setEndDateAndTime(\mktime(9, 0, 0, 1, 1, 2010));

        self::assertTrue($this->subject->hasEndTime());
    }

    // Test for open-ended events.

    /**
     * @test
     */
    public function isOpenEndedInitiallyReturnsTrue(): void
    {
        self::assertTrue($this->subject->isOpenEnded());
    }

    /**
     * @test
     */
    public function isOpenEndedAfterSettingOnlyTheBeginDateReturnsTrue(): void
    {
        $this->subject->setBeginDateAndTime(42);

        self::assertTrue($this->subject->isOpenEnded());
    }

    /**
     * @test
     */
    public function isOpenEndedAfterSettingOnlyTheEndDateToMorningReturnsFalse(): void
    {
        $this->subject->setEndDateAndTime(\mktime(9, 0, 0, 1, 1, 2010));

        self::assertFalse($this->subject->isOpenEnded());
    }

    /**
     * @test
     */
    public function isOpenEndedAfterSettingBeginAndEndDateToMorningReturnsFalse(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(8, 0, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(9, 0, 0, 1, 1, 2010));

        self::assertFalse($this->subject->isOpenEnded());
    }

    /**
     * @test
     */
    public function isOpenEndedIfEndsAtMidnightReturnsFalse(): void
    {
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        self::assertFalse($this->subject->isOpenEnded());
    }

    // Tests for getting the end date and time for open-ended events.

    /**
     * @test
     */
    public function endDateIsMidnightIfOpenEndedStartsAtOneOClock(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(1, 0, 0, 1, 1, 2010));

        self::assertTrue($this->subject->isOpenEnded());
        self::assertSame(\mktime(0, 0, 0, 1, 2, 2010), $this->subject->getEndDateAsTimestampEvenIfOpenEnded());
    }

    /**
     * @test
     */
    public function endDateIsMidnightIfOpenEndedStartsAtMorning(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 0, 0, 1, 1, 2010));

        self::assertTrue($this->subject->isOpenEnded());
        self::assertSame(\mktime(0, 0, 0, 1, 2, 2010), $this->subject->getEndDateAsTimestampEvenIfOpenEnded());
    }

    /**
     * @test
     */
    public function endDateIsMidnightIfOpenEndedStartsAtElevenPm(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(23, 0, 0, 1, 1, 2010));

        self::assertTrue($this->subject->isOpenEnded());
        self::assertSame(\mktime(0, 0, 0, 1, 2, 2010), $this->subject->getEndDateAsTimestampEvenIfOpenEnded());
    }

    /**
     * @test
     */
    public function endDateIsMidnightIfOpenEndedStartsAtMidnight(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        self::assertTrue($this->subject->isOpenEnded());
        self::assertSame(\mktime(0, 0, 0, 1, 2, 2010), $this->subject->getEndDateAsTimestampEvenIfOpenEnded());
    }

    // Tests for for the begin date.

    /**
     * @test
     */
    public function hasStartedForStartedEventReturnsTrue(): void
    {
        $this->subject->setBeginDateAndTime(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) - 42,
        );

        self::assertTrue($this->subject->hasStarted());
    }

    /**
     * @test
     */
    public function hasStartedForUpcomingEventReturnsFalse(): void
    {
        $this->subject->setBeginDateAndTime(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertFalse($this->subject->hasStarted());
    }

    /**
     * @test
     */
    public function hasStartedForEventWithoutBeginDateReturnsFalse(): void
    {
        $this->subject->setBeginDateAndTime(0);

        self::assertFalse($this->subject->hasStarted());
    }

    // Tests for getting the room.

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
        $value = 'the first test chamber';
        $this->subject->setRoom($value);

        self::assertSame($value, $this->subject->getRoom());
    }
}
