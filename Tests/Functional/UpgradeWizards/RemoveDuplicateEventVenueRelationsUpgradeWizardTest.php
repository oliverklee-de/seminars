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
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/RemoveDuplicateEventVenueRelationsUpgradeWizard/';
    private const ASSERTIONS_PATH = __DIR__ . '/Assertions/RemoveDuplicateEventVenueRelationsUpgradeWizard/';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
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
        $this->importCSVDataSet(self::FIXTURES_PATH . 'NoRelations.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForOneUniqueRelationReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'OneUniqueRelation.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForTwoUniqueRelationsFromTwoEventsToTheSameVenueReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'TwoUniqueRelationsFromTwoEventsToTheSameVenue.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForTwoUniqueRelationsFromOneEventToTwoVenuesReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');

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

        $this->importCSVDataSet(self::FIXTURES_PATH . 'TwoDuplicateRelations.csv');

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

        $this->importCSVDataSet(self::FIXTURES_PATH . 'OneDuplicateAndOneUniqueRelationFromOneEventToTwoVenues.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function executeUpdateForNoRelationsDoesNotAddAnyRelation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'NoRelations.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'NoRelations.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForOneUniqueRelationKeepsUniqueRelation(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'OneUniqueRelation.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'OneUniqueRelation.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForTwoUniqueRelationsFromTwoEventsToTheSameVenueKeepsBothUniqueRelations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'TwoUniqueRelationsFromTwoEventsToTheSameVenue.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'TwoUniqueRelationsFromTwoEventsToTheSameVenue.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForTwoUniqueRelationsFromOneEventToTwoVenuesKeepsBothUniqueRelations(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PATH . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForTwoDuplicatedRelationsDeletesOneOfThem(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PATH . 'TwoDuplicateRelations.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'OneUniqueRelation.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForThreeDuplicatedRelationDeletesTwoOfThem(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PATH . 'ThreeDuplicateRelations.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'OneUniqueRelation.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForOneDuplicatedRelationAndOneUniqueRelationRemovesDuplicate(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('This test is not relevant for TYPO3 v12 and later.');
        }

        $this->importCSVDataSet(self::FIXTURES_PATH . 'OneDuplicateAndOneUniqueRelationFromOneEventToTwoVenues.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'TwoUniqueRelationsFromOneEventToTwoVenues.csv');
    }
}
