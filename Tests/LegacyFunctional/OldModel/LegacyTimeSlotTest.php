<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyTimeSlot;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\OldModel\LegacyTimeSlot
 */
final class LegacyTimeSlotTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingLegacyTimeSlot $subject;

    private TestingFramework $testingFramework;

    private DummyConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework('tx_seminars');
        $this->configuration = new DummyConfiguration();
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $this->configuration);

        $seminarUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $subjectUid = $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $seminarUid,
                'place' => 0,
            ],
        );

        $this->subject = new TestingLegacyTimeSlot($subjectUid);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    //////////////////////////////////////////
    // Tests for creating time slot objects.
    //////////////////////////////////////////

    /**
     * @test
     */
    public function createFromUid(): void
    {
        self::assertTrue(
            $this->subject->isOk(),
        );
    }

    /////////////////////////////////////
    // Tests for the time slot's sites.
    /////////////////////////////////////

    /**
     * @test
     */
    public function placeIsInitiallyZero(): void
    {
        self::assertEquals(
            0,
            $this->subject->getPlace(),
        );
    }

    /**
     * @test
     */
    public function hasPlaceInitiallyReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasPlace(),
        );
    }

    /**
     * @test
     */
    public function getPlaceReturnsUidOfPlaceSetViaSetPlace(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
        );
        $this->subject->setPlace($placeUid);

        self::assertEquals(
            $placeUid,
            $this->subject->getPlace(),
        );
    }

    /**
     * @test
     */
    public function hasPlaceReturnsTrueIfPlaceIsSet(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
        );
        $this->subject->setPlace($placeUid);

        self::assertTrue(
            $this->subject->hasPlace(),
        );
    }

    ////////////////////////////
    // Tests for getPlaceShort
    ////////////////////////////

    /**
     * @test
     */
    public function getPlaceShortReturnsEmptyStringForNoPlaces(): void
    {
        self::assertSame('', $this->subject->getPlaceShort());
    }

    /**
     * @test
     */
    public function getPlaceShortReturnsPlaceNameForOnePlace(): void
    {
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'a place'],
        );
        $this->subject->setPlace($placeUid);

        self::assertEquals(
            'a place',
            $this->subject->getPlaceShort(),
        );
    }

    /**
     * @test
     */
    public function getPlaceShortForInexistentPlaceUidReturnsEmptyString(): void
    {
        $placeUid = $this->testingFramework->createRecord('tx_seminars_sites');
        $this->subject->setPlace($placeUid);
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_seminars_attendances');
        $connection->delete('tx_seminars_sites', ['uid' => $placeUid]);

        self::assertSame('', $this->subject->getPlaceShort());
    }

    /**
     * @test
     */
    public function getPlaceShortForDeletedPlaceReturnsEmptyString(): void
    {
        $placeUid = $this->testingFramework->createRecord('tx_seminars_sites', ['deleted' => 1]);

        $this->subject->setPlace($placeUid);

        self::assertSame('', $this->subject->getPlaceShort());
    }
}
