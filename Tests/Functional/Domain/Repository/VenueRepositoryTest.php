<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\Venue;
use OliverKlee\Seminars\Domain\Repository\VenueRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Venue
 * @covers \OliverKlee\Seminars\Domain\Repository\VenueRepository
 */
final class VenueRepositoryTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/VenueRepository';

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private VenueRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(VenueRepository::class);
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
    public function mapsAllModelFields(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/VenueWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(Venue::class, $result);
        self::assertSame('JH Köln-Deutz', $result->getTitle());
        self::assertSame('Alex', $result->getContactPerson());
        self::assertSame('alex@example.com', $result->getEmailAddress());
        self::assertSame('+49 1234 56789', $result->getPhoneNumber());
        self::assertSame('Markplatz 1, 12345 Bonn', $result->getFullAddress());
        self::assertSame('Bonn', $result->getCity());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findAll/VenueOnPage.csv');

        $result = $this->subject->findAll();

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function sortsRecordsByTitleInAscendingOrder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findAll/TwoVenuesInReverseOrder.csv');

        $result = $this->subject->findAll();

        self::assertCount(2, $result);
        $first = $result->getFirst();
        self::assertInstanceOf(Venue::class, $first);
        self::assertSame('Earlier', $first->getTitle());
    }

    /**
     * @test
     */
    public function findVenuesByUidsWithoutVenuesReturnsEmptyResult(): void
    {
        $result = $this->subject->findVenuesByUids([1]);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findVenuesByUidsFindsVenueWithMatchingOnlyUid(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/Venue.csv');

        $result = $this->subject->findVenuesByUids([1]);

        self::assertCount(1, $result);
        self::assertInstanceOf(Venue::class, $result->getFirst());
    }

    /**
     * @test
     */
    public function findVenuesByUidsFindsVenueWithMatchingFirstUidOfTwo(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/Venue.csv');

        $result = $this->subject->findVenuesByUids([1, 2]);

        self::assertCount(1, $result);
        self::assertInstanceOf(Venue::class, $result->getFirst());
    }

    /**
     * @test
     */
    public function findVenuesByUidsFindsVenueWithMatchingLastUidOfTwo(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/Venue.csv');

        $result = $this->subject->findVenuesByUids([2, 1]);

        self::assertCount(1, $result);
        self::assertInstanceOf(Venue::class, $result->getFirst());
    }

    /**
     * @test
     */
    public function findVenuesByUidsIgnoresVenueWithNonMatchingUid(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/Venue.csv');

        $result = $this->subject->findVenuesByUids([2]);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findVenuesByUidsFindsVenuesOnPage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/VenueOnPage.csv');

        $result = $this->subject->findVenuesByUids([1]);

        self::assertCount(1, $result);
        self::assertInstanceOf(Venue::class, $result->getFirst());
    }

    /**
     * @test
     */
    public function findVenuesByUidsIgnoresDeletedVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/DeletedVenue.csv');

        $result = $this->subject->findVenuesByUids([1]);

        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function findVenuesByUidsOrdersInAscendingOrderByInternalTitle(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findVenuesByUids/TwoVenuesInReverseOrder.csv');

        $result = $this->subject->findVenuesByUids([1, 2])->toArray();

        self::assertCount(2, $result);
        $firstMatch = $result[0];
        self::assertInstanceOf(Venue::class, $firstMatch);
        self::assertSame('Betahaus', $firstMatch->getTitle());
        $secondMatch = $result[1];
        self::assertInstanceOf(Venue::class, $secondMatch);
        self::assertSame('Domani Venlo', $secondMatch->getTitle());
    }
}
