<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Event\EventStatistics;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Event\EventStatistics
 */
final class EventStatisticsTest extends UnitTestCase
{
    /**
     * @test
     */
    public function getRegularSeatsCountReturnsSumOfRegularSeatsFromRegistrationsAndOfflineRegistrations(): void
    {
        $seatsFromRegistrations = 42;
        $offlineRegistrations = 13;
        $subject = new EventStatistics($seatsFromRegistrations, $offlineRegistrations, 0, 0, 0, 0, false);

        self::assertSame($seatsFromRegistrations + $offlineRegistrations, $subject->getRegularSeatsCount());
    }

    /**
     * @test
     */
    public function getWaitingListSeatsCountReturnsValueProvidedToConstructor(): void
    {
        $waitingListRegistrations = 13;
        $subject = new EventStatistics(0, 0, $waitingListRegistrations, 0, 0, 0, false);

        self::assertSame($waitingListRegistrations, $subject->getWaitingListSeatsCount());
    }

    /**
     * @test
     */
    public function getNonbindingReservationSeatsCountReturnsValueProvidedToConstructor(): void
    {
        $seatsCount = 12;
        $subject = new EventStatistics(0, 0, 0, $seatsCount, 0, 0, false);

        self::assertSame($seatsCount, $subject->getNonbindingReservationSeatsCount());
    }

    /**
     * @test
     */
    public function getMinimumRequiredSeatsReturnsValueProvidedToConstructor(): void
    {
        $minimumSeats = 13;
        $subject = new EventStatistics(0, 0, 0, 0, $minimumSeats, 0, false);

        self::assertSame($minimumSeats, $subject->getMinimumRequiredSeats());
    }

    /**
     * @test
     */
    public function getSeatsLimitReturnsValueProvidedToConstructor(): void
    {
        $seatsLimit = 13;
        $subject = new EventStatistics(0, 0, 0, 0, 0, $seatsLimit, false);

        self::assertSame($seatsLimit, $subject->getSeatsLimit());
    }

    /**
     * @test
     */
    public function hasUnlimitedSeatsForPositiveSeatsLimitReturnsFalse(): void
    {
        $subject = new EventStatistics(0, 0, 0, 0, 0, 1, false);

        self::assertFalse($subject->hasUnlimitedSeats());
    }

    /**
     * @test
     */
    public function hasUnlimitedSeatsForZeroSeatsLimitReturnsTrue(): void
    {
        $subject = new EventStatistics(0, 0, 0, 0, 0, 0, false);

        self::assertTrue($subject->hasUnlimitedSeats());
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsForOneMissingRegistrationReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 10, 0, false);

        self::assertFalse($subject->hasEnoughRegistrations());
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsForJustEnoughRegistrationReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 10, 0, false);

        self::assertTrue($subject->hasEnoughRegistrations());
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsForMoreRegistrationReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 10, 0, false);

        self::assertTrue($subject->hasEnoughRegistrations());
    }

    /**
     * @test
     */
    public function hasEnoughRegistrationsIgnoresWaitingListRegistrations(): void
    {
        $subject = new EventStatistics(5, 4, 1, 0, 10, 0, false);

        self::assertFalse($subject->hasEnoughRegistrations());
    }

    /**
     * @test
     */
    public function isFullyBookedForOneVacancyReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 0, 10, false);

        self::assertFalse($subject->isFullyBooked());
    }

    /**
     * @test
     */
    public function isFullyBookedForFullyBookedWithoutWaitingListReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, false);

        self::assertTrue($subject->isFullyBooked());
    }

    /**
     * @test
     */
    public function isFullyBookedForFullyBookedWithWaitingListReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, true);

        self::assertTrue($subject->isFullyBooked());
    }

    /**
     * @test
     */
    public function isFullyBookedForOverbookedReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 10, false);

        self::assertTrue($subject->isFullyBooked());
    }

    /**
     * @test
     */
    public function isFullyBookedIgnoresWaitingListRegistrations(): void
    {
        $subject = new EventStatistics(5, 4, 1, 0, 0, 10, false);

        self::assertFalse($subject->isFullyBooked());
    }

    /**
     * @test
     */
    public function isFullyBookedWithRegistrationsAndNoLimitReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 0, 0, false);

        self::assertFalse($subject->isFullyBooked());
    }

    /**
     * @test
     */
    public function hasExportableRegularRegistrationsNoRegistrationsAtAllReturnsFalse(): void
    {
        $subject = new EventStatistics(0, 0, 0, 0, 0, 0, false);

        self::assertFalse($subject->hasExportableRegularRegistrations());
    }

    /**
     * @test
     */
    public function hasExportableRegularRegistrationsForNonZeroRegularRegistrationsReturnsTrue(): void
    {
        $subject = new EventStatistics(1, 0, 0, 0, 0, 0, false);

        self::assertTrue($subject->hasExportableRegularRegistrations());
    }

    /**
     * @test
     */
    public function hasExportableRegularRegistrationsForNonOfflineListRegistrationsReturnsFalse(): void
    {
        $subject = new EventStatistics(0, 1, 0, 0, 0, 0, false);

        self::assertFalse($subject->hasExportableRegularRegistrations());
    }

    /**
     * @test
     */
    public function hasExportableRegularRegistrationsForNonZeroWaitingListRegistrationsReturnsFalse(): void
    {
        $subject = new EventStatistics(0, 0, 1, 0, 0, 0, false);

        self::assertFalse($subject->hasExportableRegularRegistrations());
    }

    /**
     * @test
     */
    public function getVacanciesForOneVacancyReturnsOne(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 0, 10, false);

        self::assertSame(1, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesDoesNotCountNonbindingReservations(): void
    {
        $subject = new EventStatistics(5, 4, 0, 50, 0, 10, false);

        self::assertSame(1, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesForFullyBookedReturnsZero(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, false);

        self::assertSame(0, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesForOverbookedReturnsZero(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 10, false);

        self::assertSame(0, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesForNoLimitReturnsNull(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 0, false);

        self::assertNull($subject->getVacancies());
    }

    /**
     * @test
     */
    public function hasRegularVacanciesForOneVacancyReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 0, 10, false);

        self::assertTrue($subject->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function hasRegularVacanciesForFullyBookedWithoutWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, false);

        self::assertFalse($subject->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function hasRegularVacanciesForFullyBookedWithWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, true);

        self::assertFalse($subject->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function hasRegularVacanciesForOverbookedWithoutWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 10, false);

        self::assertFalse($subject->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function hasRegularVacanciesForOverbookedWithWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 10, true);

        self::assertFalse($subject->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function hasRegularVacanciesForNoLimitReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 0, false);

        self::assertTrue($subject->hasRegularVacancies());
    }

    /**
     * @test
     */
    public function hasWaitingListVacanciesForOneVacancyReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 0, 10, false);

        self::assertFalse($subject->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function hasWaitingListVacanciesForFullyBookedWithoutWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, false);

        self::assertFalse($subject->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function hasWaitingListVacanciesForFullyBookedWithWaitingListReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, true);

        self::assertTrue($subject->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function hasWaitingListVacanciesForOverbookedWithoutWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 10, false);

        self::assertFalse($subject->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function hasWaitingListVacanciesForOverbookedWithWaitingListReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 10, true);

        self::assertTrue($subject->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function hasWaitingListVacanciesForNoLimitReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 0, false);

        self::assertFalse($subject->hasWaitingListVacancies());
    }

    /**
     * @test
     */
    public function hasAnyVacanciesForNoLimitReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 6, 0, 0, 0, 0, false);

        self::assertTrue($subject->hasAnyVacancies());
    }

    /**
     * @test
     */
    public function hasAnyVacanciesForOneVacancyReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 4, 0, 0, 0, 10, false);

        self::assertTrue($subject->hasAnyVacancies());
    }

    /**
     * @test
     */
    public function hasAnyVacanciesForFullyBookedWithoutWaitingListReturnsFalse(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, false);

        self::assertFalse($subject->hasAnyVacancies());
    }

    /**
     * @test
     */
    public function hasAnyVacanciesForFullyBookedWithWaitingListReturnsTrue(): void
    {
        $subject = new EventStatistics(5, 5, 0, 0, 0, 10, true);

        self::assertTrue($subject->hasAnyVacancies());
    }
}
