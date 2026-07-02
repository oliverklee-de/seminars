<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Hooks;

use OliverKlee\Seminars\Hooks\DataHandlerHook;
use OliverKlee\Seminars\Tests\Functional\Support\BackEndTestsTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Hooks\DataHandlerHook
 */
final class DataHandlerHookTest extends FunctionalTestCase
{
    use BackEndTestsTrait;

    private const EVENTS_TABLE = 'tx_seminars_seminars';

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

    private DataHandlerHook $subject;

    private DataHandler $dataHandler;

    private Connection $eventsTableConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        $this->eventsTableConnection = $this->get(ConnectionPool::class)->getConnectionForTable(self::EVENTS_TABLE);

        $this->subject = $this->get(DataHandlerHook::class);
    }

    private function initializeBackEndUser(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/BackEndUser.csv');
        $this->setUpBackendUser(1);
        $this->unifyBackEndLanguage();
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(DataHandlerHook::class, $this->get(DataHandlerHook::class));
    }

    private function getProcessDataMapConfigurationForSeminars(): string
    {
        $dataMapperConfiguration = (array)$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php'];

        return (string)$dataMapperConfiguration['processDatamapClass']['seminars'];
    }

    /**
     * @test
     */
    public function tceMainProcessDataMapHookReferencesExistingClass(): void
    {
        $reference = $this->getProcessDataMapConfigurationForSeminars();

        self::assertSame(DataHandlerHook::class, $reference);
    }

    private function getProcessCommandMapConfigurationForSeminars(): string
    {
        $dataMapperConfiguration = (array)$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php'];

        return (string)$dataMapperConfiguration['processCmdmapClass']['seminars'];
    }

    /**
     * @test
     */
    public function tceMainProcessCommandMapHookReferencesExistingClass(): void
    {
        $reference = $this->getProcessCommandMapConfigurationForSeminars();

        self::assertSame(DataHandlerHook::class, $reference);
    }

    private function processUpdateActionForSeminarsTable(int $uid): void
    {
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $this->dataHandler->datamap[self::EVENTS_TABLE][$uid] = $data;

        $this->subject->processDatamap_afterAllOperations($this->dataHandler);
    }

    private function processNewActionForSeminarsTable(int $uid): void
    {
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $temporaryUid = 'NEW5e0f43477dcd4869591288';
        $this->dataHandler->datamap[self::EVENTS_TABLE][$temporaryUid] = $data;
        $this->dataHandler->substNEWwithIDs[$temporaryUid] = $uid;

        $this->subject->processDatamap_afterAllOperations($this->dataHandler);
    }

    /**
     * @return int[][]
     */
    public function validRegistrationDeadlineDataProvider(): array
    {
        return [
            'no begin date and no deadline' => [1],
            'begin date and no deadline' => [2],
            'begin date and same deadline' => [3],
            'begin date and earlier deadline' => [4],
        ];
    }

    /**
     * @test
     *
     * @dataProvider validRegistrationDeadlineDataProvider
     */
    public function afterDatabaseOperationsOnUpdateKeepsValidRegistrationDeadline(int $uid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook.csv');
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $expectedDeadline = $data['deadline_registration'];

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDeadline, $row['deadline_registration']);
    }

    /**
     * @return int[][]
     */
    public function invalidRegistrationDeadlineDataProvider(): array
    {
        return [
            'begin date before deadline' => [5],
            'no begin date, but deadline' => [6],
        ];
    }

    /**
     * @test
     *
     * @dataProvider invalidRegistrationDeadlineDataProvider
     */
    public function afterDatabaseOperationsOnUpdateResetsInvalidRegistrationDeadline(int $uid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook.csv');

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame(0, $row['deadline_registration']);
    }

    /**
     * @test
     *
     * @dataProvider invalidRegistrationDeadlineDataProvider
     */
    public function afterDatabaseOperationsOnNewResetsInvalidRegistrationDeadline(int $uid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook.csv');

        $this->processNewActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame(0, $row['deadline_registration']);
    }

    /**
     * @return int[][]
     */
    public function validEarlyBirdDeadlineDataProvider(): array
    {
        return [
            'no begin date and no deadline' => [1],
            'begin date and no deadline' => [2],
            'begin date and same deadline' => [3],
            'begin date and earlier deadline' => [4],
        ];
    }

    /**
     * @test
     *
     * @dataProvider validEarlyBirdDeadlineDataProvider
     */
    public function afterDatabaseOperationsOnUpdateKeepsValidEarlyBirdDeadline(int $uid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook.csv');
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $expectedDeadline = $data['deadline_early_bird'];

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDeadline, $row['deadline_early_bird']);
    }

    /**
     * @return int[][]
     */
    public function invalidEarlyBirdDeadlineDataProvider(): array
    {
        return [
            'begin date before deadline' => [7],
            'no begin date, but deadline' => [8],
            'early-bird deadline after registration deadline' => [9],
        ];
    }

    /**
     * @test
     *
     * @dataProvider invalidEarlyBirdDeadlineDataProvider
     */
    public function afterDatabaseOperationsOnUpdateResetsInvalidEarlyBirdDeadline(int $uid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook.csv');

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame(0, $row['deadline_early_bird']);
    }

    /**
     * @test
     *
     * @dataProvider invalidEarlyBirdDeadlineDataProvider
     */
    public function afterDatabaseOperationsOnNewResetsInvalidEarlyBirdDeadline(int $uid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook.csv');

        $this->processNewActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame(0, $row['deadline_early_bird']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsOnUpdateWithoutTimeSlotsKeepsBeginDate(): void
    {
        $uid = 1;
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $expectedDate = $data['begin_date'];

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['begin_date']);
    }

    /**
     * @return int[][]
     */
    public function beginDateWithTimeSlotsDataProvider(): array
    {
        return [
            '1 time slot' => [2, 3000],
            '2 time slots' => [3, 500],
        ];
    }

    /**
     * @test
     *
     * @dataProvider beginDateWithTimeSlotsDataProvider
     */
    public function afterDatabaseOperationsOnUpdateWithTimeSlotsOverwritesBeginDate(int $uid, int $expectedDate): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['begin_date']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsOnNewWithoutTimeSlotsKeepsBeginDate(): void
    {
        $uid = 1;
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $expectedDate = $data['begin_date'];

        $this->processNewActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['begin_date']);
    }

    /**
     * @test
     *
     * @dataProvider beginDateWithTimeSlotsDataProvider
     */
    public function afterDatabaseOperationsOnNewWithTimeSlotsOverwritesBeginDate(int $uid, int $expectedDate): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');

        $this->processNewActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['begin_date']);
    }

    /**
     * @return int[][]
     */
    public function endDateWithTimeSlotsDataProvider(): array
    {
        return [
            '1 time slot' => [2, 3500],
            '2 time slots' => [3, 3500],
        ];
    }

    /**
     * @test
     *
     * @dataProvider endDateWithTimeSlotsDataProvider
     */
    public function afterDatabaseOperationsOnUpdateWithTimeSlotsOverwritesEndDate(int $uid, int $expectedDate): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['end_date']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsOnNewWithoutTimeSlotsKeepsEndDate(): void
    {
        $uid = 1;
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');
        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $data = $result->fetchAssociative();
        $expectedDate = $data['end_date'];

        $this->processNewActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['end_date']);
    }

    /**
     * @test
     *
     * @dataProvider endDateWithTimeSlotsDataProvider
     */
    public function afterDatabaseOperationsOnNewWithTimeSlotsOverwritesEndDate(int $uid, int $expectedDate): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TimeSlots.csv');

        $this->processNewActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame($expectedDate, $row['end_date']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsForSingleEventWithSlugKeepsSlugUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/SingleEventWithSlug.csv');
        $uid = 1;

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame('unchanged-slug/1', $row['slug']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsForTopicWithSlugKeepsSlugUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TopicWithSlug.csv');
        $uid = 1;

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame('unchanged-slug/1', $row['slug']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsForEventDateWithSlugKeepsSlugUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/EventDateWithSlug.csv');
        $uid = 1;

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame('unchanged-slug/1', $row['slug']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsForSingleEventWithoutSlugSetsSlug(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/SingleEventWithoutSlug.csv');
        $uid = 1;

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame('single-event-without-slug/1', $row['slug']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsForTopicWithoutSlugSetsSlug(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/TopicWithoutSlug.csv');
        $uid = 1;

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame('topic-without-slug/1', $row['slug']);
    }

    /**
     * @test
     */
    public function afterDatabaseOperationsForEventDateWithoutSlugSetsSlug(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/EventDateWithoutSlug.csv');
        $uid = 1;

        $this->processUpdateActionForSeminarsTable($uid);

        $result = $this->eventsTableConnection->select(['*'], self::EVENTS_TABLE, ['uid' => $uid]);
        $row = $result->fetchAssociative();
        self::assertSame('topic-with-slug/1', $row['slug']);
    }

    /**
     * @test
     */
    public function eventCanBeCopied(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/copy/SingleEventOnPage.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [self::EVENTS_TABLE => [1 => ['copy' => -1]]]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Assertions/DataHandlerHook/copy/SingleEventOnPageAndDuplicate.csv');
    }

    /**
     * @test
     */
    public function doesNotDuplicateRegistrationsWhenCopyingEvent(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 13) {
            self::markTestSkipped(
                'This functionality relies on altering the TCA at runtime, which is not supported in TYPO3 v13 and '
                . 'later. Also, the tested functionality is deprecated anyway and will be removed very soon.',
            );
        }

        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/copy/SingleEventWithOneRegistration.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [self::EVENTS_TABLE => [1 => ['copy' => -1]]]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(
            __DIR__
            . '/Assertions/DataHandlerHook/copy/SingleEventWithOneRegistrationAndDuplicateWithRegistrations.csv',
        );
    }

    /**
     * @test
     */
    public function canMoveRegistration(): void
    {
        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/move/SingleEventOnPage.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [self::EVENTS_TABLE => [1 => ['move' => 2]]]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(
            __DIR__ . '/Assertions/DataHandlerHook/move/SingleEventOnPageAfterMoving.csv',
        );
    }

    /**
     * @test
     */
    public function doesNotMoveRegistrationsWhenMovingEvent(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 13) {
            self::markTestSkipped(
                'This functionality relies on altering the TCA at runtime, which is not supported in TYPO3 v13 and '
                . 'later. Also, the tested functionality is deprecated anyway and will be removed very soon.',
            );
        }

        $this->initializeBackEndUser();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerHook/move/SingleEventWithOneRegistrationOnPage.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [self::EVENTS_TABLE => [1 => ['move' => 2]]]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(
            __DIR__ . '/Assertions/DataHandlerHook/move/SingleEventWithOneRegistrationOnPageAfterMoving.csv',
        );
    }
}
