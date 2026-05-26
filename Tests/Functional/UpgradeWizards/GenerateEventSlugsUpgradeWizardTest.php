<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\GenerateEventSlugsUpgradeWizard;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\GenerateEventSlugsUpgradeWizard
 */
class GenerateEventSlugsUpgradeWizardTest extends FunctionalTestCase
{
    private const FIXTURES_PREFIX = __DIR__ . '/Fixtures/GenerateEventSlugsUpgradeWizard/';

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private Connection $eventsTableConnection;

    private GenerateEventSlugsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventsTableConnection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_seminars_seminars');

        $this->subject = $this->get(GenerateEventSlugsUpgradeWizard::class);
        $this->subject->setLogger(new NullLogger());
    }

    /**
     * @test
     */
    public function updateNecessaryForEmptyDatabaseReturnsFalse(): void
    {
        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForOnlyEventsWithSlugsReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'EventWithSlug.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForEventWithEmptySlugReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'EventsWithAndWithEmptySlug.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForEventWithNullSlugReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'EventWithNullSlug.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForHiddenEventWithNullSlugReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'HiddenEventWithNullSlug.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForDeletedEventWithNullSlugReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'DeletedEventWithNullSlug.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForTimedEventWithNullSlugReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TimedEventWithNullSlug.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function executeUpdateKeepsEventWithSlugUnmodified(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'EventsWithAndWithEmptySlug.csv');

        $wizardResult = $this->subject->executeUpdate();

        $result = $this->eventsTableConnection->executeQuery(
            'SELECT * FROM tx_seminars_seminars WHERE uid = :uid',
            ['uid' => 1],
        );
        $databaseRow = $result->fetchAssociative();

        self::assertIsArray($databaseRow);
        self::assertSame('existing-slug', $databaseRow['slug']);
        self::assertTrue($wizardResult);
    }

    /**
     * @test
     */
    public function executeUpdateUpdatesSlugOfEventWithEmptySlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'EventsWithAndWithEmptySlug.csv');

        $wizardResult = $this->subject->executeUpdate();

        $result = $this->eventsTableConnection->executeQuery(
            'SELECT * FROM tx_seminars_seminars WHERE uid = :uid',
            ['uid' => 2],
        );
        $databaseRow = $result->fetchAssociative();

        self::assertIsArray($databaseRow);
        self::assertSame('event-without-slug/2', $databaseRow['slug']);
        self::assertTrue($wizardResult);
    }

    /**
     * @test
     */
    public function executeUpdateUpdatesSlugOfEventWithNullSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'EventWithNullSlug.csv');

        $wizardResult = $this->subject->executeUpdate();

        $result = $this->eventsTableConnection->executeQuery(
            'SELECT * FROM tx_seminars_seminars WHERE uid = :uid',
            ['uid' => 1],
        );
        $databaseRow = $result->fetchAssociative();

        self::assertIsArray($databaseRow);
        self::assertSame('event-without-slug/1', $databaseRow['slug']);
        self::assertTrue($wizardResult);
    }

    /**
     * @test
     */
    public function executeUpdateUpdatesSlugOfHiddenEventWithNullSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'HiddenEventWithNullSlug.csv');

        $wizardResult = $this->subject->executeUpdate();

        $result = $this->eventsTableConnection->executeQuery(
            'SELECT * FROM tx_seminars_seminars WHERE uid = :uid',
            ['uid' => 1],
        );
        $databaseRow = $result->fetchAssociative();

        self::assertIsArray($databaseRow);
        self::assertSame('event-without-slug/1', $databaseRow['slug']);
        self::assertTrue($wizardResult);
    }

    /**
     * @test
     */
    public function executeUpdateUpdatesSlugOfDeletedEventWithNullSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'DeletedEventWithNullSlug.csv');

        $wizardResult = $this->subject->executeUpdate();

        $result = $this->eventsTableConnection->executeQuery(
            'SELECT * FROM tx_seminars_seminars WHERE uid = :uid',
            ['uid' => 1],
        );
        $databaseRow = $result->fetchAssociative();

        self::assertIsArray($databaseRow);
        self::assertSame('event-without-slug/1', $databaseRow['slug']);
        self::assertTrue($wizardResult);
    }

    /**
     * @test
     */
    public function executeUpdateUpdatesSlugOfTimedEventWithNullSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TimedEventWithNullSlug.csv');

        $wizardResult = $this->subject->executeUpdate();

        $result = $this->eventsTableConnection->executeQuery(
            'SELECT * FROM tx_seminars_seminars WHERE uid = :uid',
            ['uid' => 1],
        );
        $databaseRow = $result->fetchAssociative();

        self::assertIsArray($databaseRow);
        self::assertSame('event-without-slug/1', $databaseRow['slug']);
        self::assertTrue($wizardResult);
    }
}
