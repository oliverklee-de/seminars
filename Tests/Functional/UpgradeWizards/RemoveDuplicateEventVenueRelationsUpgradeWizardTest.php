<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\RemoveDuplicateEventVenueRelationsUpgradeWizard;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\RemoveDuplicateEventVenueRelationsUpgradeWizard
 */
class RemoveDuplicateEventVenueRelationsUpgradeWizardTest extends FunctionalTestCase
{
    private const FIXTURES_PREFIX = __DIR__ . '/Fixtures/RemoveDuplicateEventVenueRelationsUpgradeWizard/';

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RemoveDuplicateEventVenueRelationsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(RemoveDuplicateEventVenueRelationsUpgradeWizard::class);
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
    public function updateNecessaryForNoRelationsReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'NoRelations.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForOneUniqueRelationReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'OneUniqueRelation.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForTwoUniqueRelationsFromTwoEventsToTheSameVenueReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromTwoEventsToTheSameVenue.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForTwoUniqueRelationsFromOneEventToTwoVenusReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForTwoDuplicatedRelationsReturnsTrue(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TwoDuplicateRelations.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForOneDuplicatedRelationAndOneUniqueRelationReturnsTrue(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'OneDuplicateAndOneUniqueRelationFromOneEventToTwoVenues.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function executeUpdateForNoRelationsDoesNotAddAnyRelation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'NoRelations.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'NoRelations.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForOneUniqueRelationKeepsUniqueRelation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'OneUniqueRelation.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'OneUniqueRelation.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForTwoUniqueRelationsFromTwoEventsToTheSameVenueKeepsBothUniqueRelations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromTwoEventsToTheSameVenue.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromTwoEventsToTheSameVenue.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForTwoUniqueRelationsFromOneEventToTwoVenuesKeepsBothUniqueRelations(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForTwoDuplicatedRelationsDeletesOneOfThem(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'TwoDuplicateRelations.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'OneUniqueRelation.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForThreeDuplicatedRelationDeletesTwoOfThem(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'ThreeDuplicateRelations.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'OneUniqueRelation.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForOneDuplicatedRelationAndOneUniqueRelationRemovesDuplicate(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'OneDuplicateAndOneUniqueRelationFromOneEventToTwoVenues.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');
    }
}
