<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository\Event;

use OliverKlee\Seminars\Domain\Model\Event\TimeSlot;
use OliverKlee\Seminars\Domain\Model\Venue;
use OliverKlee\Seminars\Domain\Repository\Event\TimeSlotRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Repository\Event\TimeSlotRepository
 */
final class TimeSlotRepositoryTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures';

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TimeSlotRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(TimeSlotRepository::class);
    }

    /**
     * @test
     */
    public function isRepository(): void
    {
        self::assertInstanceOf(Repository::class, $this->subject);
    }

    /**
     * @test
     */
    public function mapsAllScalarModelFields(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/TimeSlotWithAllScalarFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(TimeSlot::class, $result);
        self::assertEquals(new \DateTime('2025-04-02 10:00'), $result->getStart());
        self::assertEquals(new \DateTime('2025-04-03 18:00'), $result->getEnd());
        self::assertSame('Leuchtturm', $result->getRoom());
    }

    /**
     * @test
     */
    public function mapsVenueRelation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/TimeSlotWithVenue.csv');

        $result = $this->subject->findByUid(1);
        self::assertInstanceOf(TimeSlot::class, $result);

        $venue = $result->getVenue();

        self::assertInstanceOf(Venue::class, $venue);
        self::assertSame('AKA', $venue->getTitle());
    }
}
