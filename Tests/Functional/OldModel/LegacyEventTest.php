<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Registration\Registration as ExtbaseRegistration;
use OliverKlee\Seminars\FrontEnd\DefaultController;
use OliverKlee\Seminars\Model\Place;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\Tests\Functional\Traits\FalHelper;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\OldModel\LegacyEvent
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class LegacyEventTest extends FunctionalTestCase
{
    use FalHelper;
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $this->testingFramework = new TestingFramework('tx_seminars');

        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', new DummyConfiguration());
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();
        ConfigurationRegistry::purgeInstance();

        parent::tearDown();
    }

    private function buildFrontEndAndPlugin(): DefaultController
    {
        $this->importDataSet(__DIR__ . '/Fixtures/SingleRootPage.xml');
        $this->testingFramework->createFakeFrontEnd(1);

        $plugin = new DefaultController();
        $plugin->main('', ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html']);

        return $plugin;
    }

    /**
     * @test
     */
    public function fromUidMapsDataFromDatabase(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(1);
        self::assertSame('event with all scalar data set', $subject->getTitle());
        self::assertSame('Cooking for beginners', $subject->getSubtitle());
        self::assertSame('Never be hungry again.', $subject->getTeaser());
        self::assertSame('Never ever.', $subject->getDescription());
        self::assertSame('ABC-12345', $subject->getAccreditationNumber());
        self::assertSame('12', $subject->getCreditPoints());
        self::assertSame(1575026911, $subject->getBeginDateAsTimestamp());
        self::assertSame(1575926911, $subject->getEndDateAsTimestamp());
        self::assertSame(1570026911, $subject->getRegistrationBeginAsUnixTimestamp());
        self::assertSame('2019-11-17 21:41', $subject->getRegistrationDeadline());
        self::assertSame('2019-10-14 04:21', $subject->getEarlyBirdDeadline());
        self::assertSame(1573026911, $subject->getUnregistrationDeadlineAsTimestamp());
        self::assertSame('2019-12-11', $subject->getExpiry());
        self::assertSame('12', $subject->getDetailsPage());
        self::assertSame('the first one to the left', $subject->getRoom());
        self::assertSame('€ 1.234,56', $subject->getPriceRegular());
        self::assertSame('€ 234,56', $subject->getEarlyBirdPriceRegular());
        self::assertSame('€ 1.134,54', $subject->getPriceSpecial());
        self::assertSame('€ 1.034,54', $subject->getEarlyBirdPriceSpecial());
        self::assertSame('Nothing to see here.', $subject->getAdditionalInformation());
        self::assertTrue($subject->needsRegistration());
        self::assertTrue($subject->allowsMultipleRegistrations());
        self::assertSame(4, $subject->getAttendancesMin());
        self::assertSame(20, $subject->getAttendancesMax());
        self::assertTrue($subject->hasRegistrationQueue());
        self::assertSame(3, $subject->getOfflineRegistrations());
        self::assertTrue($subject->isCanceled());
        self::assertTrue($subject->hasTerms2());
        self::assertTrue($subject->haveOrganizersBeenNotifiedAboutEnoughAttendees());
        self::assertTrue($subject->shouldMuteNotificationEmails());
        self::assertTrue($subject->shouldAutomaticallyConfirmOrCancel());
        self::assertTrue($subject->getPriceOnRequest());
    }

    /**
     * @test
     */
    public function getAttendancesForNoRegistrationsReturnsZero(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(2);

        self::assertSame(0, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function getAttendancesCountsOfflineRegistrations(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(3);

        self::assertSame(2, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function getAttendancesSumsSeatsOfRegistrationsWithSeats(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(4);

        self::assertSame(3, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function getAttendancesCalculatesSeatsOfRegistrationsWithoutSeatsAsOneEach(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(5);

        self::assertSame(2, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function getAttendancesIgnoresRegistrationsOnQueue(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(6);

        self::assertSame(2, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function calculateStatisticsTakesNewOfflineRegistrationsIntoAccount(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(2);
        self::assertSame(0, $subject->getAttendances());

        $offlineRegistrations = 4;
        $subject->setOfflineRegistrationNumber($offlineRegistrations);
        $subject->calculateStatistics();

        self::assertSame($offlineRegistrations, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function calculateStatisticsTakesRegularRegistrationRecordsIntoAccount(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $eventUid = 4;
        $subject = TestingLegacyEvent::fromUid($eventUid);
        self::assertSame(3, $subject->getAttendances());

        GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_seminars_attendances')
            ->insert(
                'tx_seminars_attendances',
                ['seminar' => $eventUid, 'seats' => 2, 'registration_queue' => ExtbaseRegistration::STATUS_REGULAR],
            );
        $subject->calculateStatistics();

        self::assertSame(5, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function calculateStatisticsIgnoresNonbindingReservations(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $eventUid = 4;
        $subject = TestingLegacyEvent::fromUid($eventUid);
        self::assertSame(3, $subject->getAttendances());

        GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_seminars_attendances')
            ->insert(
                'tx_seminars_attendances',
                [
                    'seminar' => $eventUid,
                    'seats' => 1,
                    'registration_queue' => ExtbaseRegistration::STATUS_NONBINDING_RESERVATION,
                ],
            );
        $subject->calculateStatistics();

        self::assertSame(3, $subject->getAttendances());
    }

    /**
     * @test
     */
    public function getVacanciesForNoMaxAttendancesAndNoRegistrationsReturnsZero(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(2);

        self::assertSame(0, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesForMaxAttendancesAndNoRegistrationsReturnsMaxAttendances(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(9);

        self::assertSame(12, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesReturnsMaxVacanciesMinusOfflineRegistrations(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(3);

        self::assertSame(3, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesReturnsMaxVacanciesMinusRegistrations(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(4);

        self::assertSame(2, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesForMoreRegisteredSeatsThanAllowedReturnsZero(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(5);

        self::assertSame(0, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getVacanciesIgnoresQueueRegistrations(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $subject = TestingLegacyEvent::fromUid(6);

        self::assertSame(2, $subject->getVacancies());
    }

    /**
     * @test
     */
    public function getCheckboxesForNoCheckboxesReturnsEmptyArray(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/Checkboxes.xml');

        $subject = TestingLegacyEvent::fromUid(1);
        $result = $subject->getCheckboxes();

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function getCheckboxesReturnsCaptionAndUidOfAssociatedCheckboxesForSingleEvent(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/Checkboxes.xml');

        $subject = TestingLegacyEvent::fromUid(2);
        $result = $subject->getCheckboxes();

        $expected = [['caption' => 'Checkbox one', 'value' => 1]];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getCheckboxesReturnsCaptionAndUidOfAssociatedCheckboxesForEventDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/Checkboxes.xml');

        $subject = TestingLegacyEvent::fromUid(4);
        $result = $subject->getCheckboxes();

        $expected = [['caption' => 'Checkbox one', 'value' => 1]];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getCheckboxesReturnsAssociatedCheckboxesOrderedBySorting(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/Checkboxes.xml');

        $subject = TestingLegacyEvent::fromUid(3);
        $result = $subject->getCheckboxes();

        $expected = [['caption' => 'Checkbox two', 'value' => 2], ['caption' => 'Checkbox one', 'value' => 1]];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getTargetGroupsAsArrayForNoTargetGroupsReturnsEmptyArray(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/TargetGroups.xml');

        $subject = TestingLegacyEvent::fromUid(1);
        $result = $subject->getTargetGroupsAsArray();

        self::assertSame([], $result);
    }

    /**
     * @test
     */
    public function getTargetGroupsAsArrayReturnsTitlesOfAssociatedTargetGroups(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/TargetGroups.xml');

        $subject = TestingLegacyEvent::fromUid(2);
        $result = $subject->getTargetGroupsAsArray();

        $expected = ['Target group one'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getTargetGroupsAsArrayReturnsAssociatedTargetGroupsOrderedBySorting(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/TargetGroups.xml');

        $subject = TestingLegacyEvent::fromUid(3);
        $result = $subject->getTargetGroupsAsArray();

        $expected = ['Target group two', 'Target group one'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getTargetGroupsAsArrayForDateReturnsTitlesOfTopicTargetGroups(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/TargetGroups.xml');

        $subject = TestingLegacyEvent::fromUid(5);
        $result = $subject->getTargetGroupsAsArray();

        $expected = ['Target group one'];
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getAttachedFilesForNoAttachedFilesReturnsEmptyArray(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithAttachments.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(1);

        self::assertSame([], $subject->getAttachedFiles());
    }

    /**
     * @test
     */
    public function getAttachedFilesForNotMigratedFilesReturnsEmptyArray(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithAttachments.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(2);

        self::assertSame([], $subject->getAttachedFiles());
    }

    /**
     * @test
     */
    public function getAttachedFilesWithPositiveFileCountWithoutFileReferenceReturnsEmptyArray(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithAttachments.xml');

        $subject = new LegacyEvent(3);

        self::assertSame([], $subject->getAttachedFiles());
    }

    /**
     * @test
     */
    public function getAttachedFilesWithOneDirectlyAttachedFileFileReferenceInArray(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithAttachments.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(4);
        $files = $subject->getAttachedFiles();

        self::assertCount(1, $files);
        self::assertInstanceOf(FileReference::class, $files[0]);
    }

    /**
     * @test
     */
    public function getAttachedFilesForDateReturnsFilesFromTopic(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithAttachments.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(5);
        $files = $subject->getAttachedFiles();

        self::assertCount(1, $files);
        self::assertInstanceOf(FileReference::class, $files[0]);
    }

    /**
     * @test
     */
    public function getAttachedFilesForDateReturnsFilesFromTopicAndDateCombined(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithAttachments.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(6);
        $files = $subject->getAttachedFiles();

        self::assertCount(2, $files);
    }

    // Tests concerning getPlaceWithDetails and getPlaceWithDetailsRaw

    /**
     * @test
     */
    public function getPlaceWithDetailsReturnsWillBeAnnouncedForNoPlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(1);

        $result = $subject->getPlaceWithDetails($plugin);

        $expected = $this->translate('message_willBeAnnounced');
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsContainsTitleOfPlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetails($plugin);

        self::assertStringContainsString('The Castle (without country)', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsContainsTitlesOfAllRelatedPlaces(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(8);

        $result = $subject->getPlaceWithDetails($plugin);

        self::assertStringContainsString('The Castle (without country)', $result);
        self::assertStringContainsString('The garden (without country)', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsCanContainAddressOfOneVenue(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetails($plugin);

        self::assertStringContainsString('On top of the mountain', $result);
        self::assertStringContainsString('12345 Hamm', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsHasCityOfVenueOnlyOnce(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetails($plugin);

        self::assertSame(1, substr_count($result, 'Hamm'));
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsContainsHomepageLinkOfOnePlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetails($plugin);

        self::assertStringContainsString('href="', $result);
        self::assertStringContainsString('://www.example.com"', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsContainsDirectionsOfOnePlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $plugin = $this->buildFrontEndAndPlugin();
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetails($plugin);

        self::assertStringContainsString('3 turns left, then always right', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawReturnsWillBeAnnouncedForNoPlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(1);
        $this->initializeBackEndLanguage();

        $result = $subject->getPlaceWithDetailsRaw();

        $expected = $this->translate('message_willBeAnnounced');
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawContainsTitleOfPlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetailsRaw();

        self::assertStringContainsString('The Castle (without country)', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawSeparatesPlacesByNewline(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(8);

        $result = $subject->getPlaceWithDetailsRaw();

        self::assertStringContainsString("3 turns left, then always right\nThe garden (without country)", $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawContainsAddressOfOnePlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetailsRaw();

        self::assertStringContainsString('On top of the mountain', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawForNonEmptyZipAndCityContainsZipAndCity(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetailsRaw();

        self::assertStringContainsString('12345', $result);
        self::assertStringContainsString('Hamm', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawContainsHomepageLinkOfOnePlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetailsRaw();

        self::assertStringContainsString('www.example.com', $result);
    }

    /**
     * @test
     */
    public function getPlaceWithDetailsRawContainsDirectionsOfOnePlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceWithDetailsRaw();

        self::assertStringContainsString('3 turns left, then always right', $result);
    }

    // Tests for getPlaceShort

    /**
     * @test
     */
    public function getPlaceShortReturnsWillBeAnnouncedForNoPlaces(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(1);
        $this->initializeBackEndLanguage();

        $result = $subject->getPlaceShort();

        $expected = $this->translate('message_willBeAnnounced');
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function getPlaceShortReturnsPlaceNameForOnePlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaceShort();

        self::assertStringContainsString('The Castle (without country)', $result);
    }

    /**
     * @test
     */
    public function getPlaceShortReturnsPlaceNamesWithCommaForTwoPlaces(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(8);

        $result = $subject->getPlaceShort();

        self::assertStringContainsString('The Castle (without country), The garden (without country)', $result);
    }

    // Tests concerning getPlaces

    /**
     * @test
     */
    public function getPlacesForEventWithNoPlacesReturnsEmptyList(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(1);

        $result = $subject->getPlaces();

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * @test
     */
    public function getPlacesForSeminarWithOnePlacesReturnsListWithPlace(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events/EventsWithPlaces.xml');
        $subject = TestingLegacyEvent::fromUid(2);

        $result = $subject->getPlaces();

        self::assertInstanceOf(Collection::class, $result);
        self::assertInstanceOf(Place::class, $result->first());
        self::assertSame('1', $result->getUids());
    }

    // Tests for getImage

    /**
     * @test
     */
    public function getImageWithoutImageReturnsNull(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithImages.xml');

        $subject = new LegacyEvent(1);

        self::assertNull($subject->getImage());
    }

    /**
     * @test
     */
    public function getImageWithNotYetMigratedImageReturnsNull(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithImages.xml');

        $subject = new LegacyEvent(4);

        self::assertNull($subject->getImage());
    }

    /**
     * @test
     */
    public function getImageWithPositiveImageCountWithoutFileReferenceReturnsNull(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithImages.xml');

        $subject = new LegacyEvent(2);

        self::assertNull($subject->getImage());
    }

    /**
     * @test
     */
    public function getImageWithFileReferenceReturnsFileReference(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithImages.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(3);

        self::assertInstanceOf(FileReference::class, $subject->getImage());
    }

    /**
     * @test
     */
    public function getImageForDateForSingleEventWithFileReferenceReturnsFileReference(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/EventsWithImages.xml');
        $this->provideAdminBackEndUserForFal();

        $subject = new LegacyEvent(5);

        self::assertInstanceOf(FileReference::class, $subject->getImage());
    }

    /**
     * @test
     */
    public function hasTimeForEventWithoutDateReturnsFalse(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events/hasTime/SingleEventWithoutDate.csv');
        $subject = new LegacyEvent(1);

        self::assertFalse($subject->hasTime());
    }

    /**
     * @test
     */
    public function hasTimeForEventWithBeginAndEndDateReturnsTrue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events/hasTime/SingleEventWithBeginAndWithEndDate.csv');
        $subject = new LegacyEvent(1);

        self::assertTrue($subject->hasTime());
    }

    /**
     * @test
     */
    public function hasTimeForEventWithBeginDateAndWithoutEndDateReturnsTrue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events/hasTime/SingleEventWithBeginAndWithoutEndDate.csv');
        $subject = new LegacyEvent(1);

        self::assertTrue($subject->hasTime());
    }

    /**
     * @test
     */
    public function hasTimeForEventWithoutBeginDateAndWithEndDateReturnsFalse(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events/hasTime/SingleEventWithoutBeginAndWithEndDate.csv');
        $subject = new LegacyEvent(1);

        self::assertFalse($subject->hasTime());
    }

    /**
     * @test
     */
    public function hasTimeForEventWithOneTimeSlotReturnsFalse(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events/hasTime/SingleEventWithOneTimeSlot.csv');
        $subject = new LegacyEvent(1);

        self::assertFalse($subject->hasTime());
    }

    /**
     * @test
     */
    public function hasTimeForEventWithTwoTimeSlotsReturnsFalse(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events/hasTime/SingleEventWithTwoTimeSlots.csv');
        $subject = new LegacyEvent(1);

        self::assertFalse($subject->hasTime());
    }
}
