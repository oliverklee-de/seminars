<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\SchedulerTasks;

use OliverKlee\Oelib\Configuration\PageFinder;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\SchedulerTasks\MailNotifier;
use OliverKlee\Seminars\Tests\Functional\Support\BackEndTestsTrait;
use OliverKlee\Seminars\Tests\Unit\Traits\EmailTrait;
use OliverKlee\Seminars\Tests\Unit\Traits\MakeInstanceTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\SchedulerTasks\MailNotifier
 */
final class MailNotifierTest extends FunctionalTestCase
{
    use BackEndTestsTrait;
    use EmailTrait;
    use MakeInstanceTrait;

    private const SECONDS_PER_DAY = 86400;

    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
        'typo3/cms-scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private MailNotifier $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackEndUser.csv');

        $this->unifyTestingEnvironment();

        $this->configuration->setAsInteger('sendEventTakesPlaceReminderDaysBeforeBeginDate', 2);
        $this->configuration->setAsBoolean('sendCancelationDeadlineReminder', true);

        $this->email = $this->createEmailMock();

        $this->subject = new MailNotifier();

        $this->testingFramework = $this->get(TestingFramework::class);
        $configurationPageUid = $this->testingFramework->createFrontEndPage();
        $this->subject->setConfigurationPageUid($configurationPageUid);
        $this->subject->constituteDependencies();
    }

    protected function tearDown(): void
    {
        PageFinder::purgeInstance();
        GeneralUtility::resetSingletonInstances([]);

        parent::tearDown();
    }

    // Utility functions

    private function getConnectionForTable(string $table): Connection
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable($table);
    }

    /**
     * Creates a seminar record and an organizer record and the relation
     * between them.
     *
     * @param array $additionalSeminarData additional data for the seminar record, may be empty
     *
     * @return positive-int UID of the added event, will be > 0
     */
    private function createSeminarWithOrganizer(array $additionalSeminarData = []): int
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'Mr. Test', 'email' => 'MrTest@example.com'],
        );

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            array_merge($additionalSeminarData, ['organizers' => 1]),
        );

        $this->testingFramework->createRelation('tx_seminars_seminars_organizers_mm', $eventUid, $organizerUid);

        return $eventUid;
    }

    /**
     * Adds a speaker relation to an existing seminar record.
     *
     * Note: This function must only be called once per test.
     *
     * @param positive-int $eventUid event UID, must be > 0
     */
    private function addSpeaker(int $eventUid): void
    {
        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            ['cancelation_period' => 2],
        );

        $this->testingFramework->changeRecord('tx_seminars_seminars', $eventUid, ['speakers' => 1]);
        $this->testingFramework->createRelation('tx_seminars_seminars_speakers_mm', $eventUid, $speakerUid);
    }

    // Tests for the utility functions

    /**
     * @test
     */
    public function createSeminarWithOrganizerCreatesSeminarRecord(): void
    {
        $this->createSeminarWithOrganizer();
        $connection = $this->getConnectionForTable('tx_seminars_seminars');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars', []),
        );
    }

    /**
     * @test
     */
    public function createSeminarWithOrganizerCreatesSeminarRecordWithAdditionalData(): void
    {
        $this->createSeminarWithOrganizer(['title' => 'foo']);
        $connection = $this->getConnectionForTable('tx_seminars_seminars');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars', ['title' => 'foo']),
        );
    }

    /**
     * @test
     */
    public function createSeminarWithOrganizerCreatesOrganizerRecord(): void
    {
        $this->createSeminarWithOrganizer();
        $connection = $this->getConnectionForTable('tx_seminars_organizers');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_organizers', []),
        );
    }

    /**
     * @test
     */
    public function createSeminarWithOrganizerCreatesRelationBetweenSeminarAndOrganizer(): void
    {
        $this->createSeminarWithOrganizer();
        $connection = $this->getConnectionForTable('tx_seminars_seminars_organizers_mm');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars_organizers_mm', []),
        );
    }

    /**
     * @test
     */
    public function addSpeakerCreatesSpeakerRecord(): void
    {
        $this->addSpeaker($this->createSeminarWithOrganizer());
        $connection = $this->getConnectionForTable('tx_seminars_speakers');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_speakers', []),
        );
    }

    /**
     * @test
     */
    public function addSpeakerCreatesSpeakerRelation(): void
    {
        $this->addSpeaker($this->createSeminarWithOrganizer());
        $connection = $this->getConnectionForTable('tx_seminars_seminars_speakers_mm');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars_speakers_mm', []),
        );
    }

    /**
     * @test
     */
    public function addSpeakerSetsNumberOfSpeakersToOneForTheSeminarWithTheProvidedUid(): void
    {
        $this->addSpeaker($this->createSeminarWithOrganizer());
        $connection = $this->getConnectionForTable('tx_seminars_seminars');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars', ['speakers' => 1]),
        );
    }

    // Tests concerning sendEventTakesPlaceReminders

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForConfirmedEventWithinConfiguredTimeFrameSendsReminder(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersSendsReminderWithEventTakesPlaceSubject(): void
    {
        $this->getLanguageService()->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
        $emailSubject = $this->translate('email_eventTakesPlaceReminderSubject');
        $emailSubject = str_replace(['%event', '%days'], ['', 2], $emailSubject);

        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString($emailSubject, $this->email->getSubject());
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersSendsReminderWithEventTakesPlaceMessage(): void
    {
        $this->getLanguageService()->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
        $message = $this->translate('email_eventTakesPlaceReminder');
        $message = str_replace(['%event', '%organizer'], ['', 'Mr. Test'], $message);

        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString(
            \substr($message, 0, \strpos($message, '%') - 1),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForTwoConfirmedEventsWithinConfiguredTimeFrameSendsTwoReminders(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForConfirmedWithTwoOrganizersAndWithinTimeFrameSendsTwoReminders(): void
    {
        $eventUid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'foo', 'email' => 'foo@example.com'],
        );
        $this->testingFramework->createRelation('tx_seminars_seminars_organizers_mm', $eventUid, $organizerUid);
        $this->testingFramework->changeRecord('tx_seminars_seminars', $eventUid, ['organizers' => 2]);

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersSetsSentFlagInTheDatabaseWhenReminderWasSent(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
        $connection = $this->getConnectionForTable('tx_seminars_seminars');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars', ['event_takes_place_reminder_sent' => 1]),
        );
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForConfirmedWithinTimeFrameAndReminderSentFlagTrueSendsNoReminder(
    ): void {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
                'event_takes_place_reminder_sent' => 1,
            ],
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForConfirmedEventWithPassedBeginDateSendsNoReminder(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now - self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForConfirmedEventBeginningLaterThanConfiguredTimeFrameSendsNoReminder(
    ): void {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + (3 * self::SECONDS_PER_DAY),
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForConfirmedEventAndNoTimeFrameConfiguredSendsNoReminder(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $this->configuration->setAsInteger('sendEventTakesPlaceReminderDaysBeforeBeginDate', 0);

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForCanceledEventWithinConfiguredTimeFrameSendsNoReminder(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CANCELED,
            ],
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    /**
     * @test
     */
    public function sendEventTakesPlaceRemindersForPlannedEventWithinConfiguredTimeFrameSendsNoReminder(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_PLANNED,
            ],
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();
    }

    // Tests concerning sendCancellationDeadlineReminders

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForPlannedEventAndOptionEnabledSendsReminder(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersSendsReminderWithCancelationDeadlineSubject(): void
    {
        $this->getLanguageService()->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
        $emailSubject = $this->translate('email_cancelationDeadlineReminderSubject');
        $emailSubject = str_replace('%event', '', $emailSubject);

        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();

        self::assertStringContainsString($emailSubject, $this->email->getSubject());
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersSendsReminderWithCancelationDeadlineMessage(): void
    {
        $this->getLanguageService()->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
        $message = $this->translate('email_cancelationDeadlineReminder');
        $message = str_replace(['%event', '%organizer'], ['', 'Mr. Test'], $message);

        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );
        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();

        self::assertStringContainsString(
            \substr($message, 0, \strpos($message, '%') - 1),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForTwoPlannedEventsAndOptionEnabledSendsTwoReminders(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForPlannedEventWithTwoOrganizersAndOptionEnabledSendsTwoReminders(
    ): void {
        $eventUid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_PLANNED,
            ],
        );
        $this->addSpeaker($eventUid);
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'foo', 'email' => 'foo@example.com'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $eventUid,
            ['organizers' => 2],
        );

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersSetsFlagInTheDatabaseWhenReminderWasSent(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
        $connection = $this->getConnectionForTable('tx_seminars_seminars');

        self::assertGreaterThan(
            0,
            $connection->count('*', 'tx_seminars_seminars', ['cancelation_deadline_reminder_sent' => 1]),
        );
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForPlannedAndOptionEnabledAndReminderSentFlagTrueSendsNoReminder(
    ): void {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                    'cancelation_deadline_reminder_sent' => 1,
                ],
            ),
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForPlannedEventWithPassedBeginDateSendsNoReminder(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now - self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForPlannedEventWithSpeakersDeadlineNotYetReachedSendsNoReminder(
    ): void {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + (3 * self::SECONDS_PER_DAY),
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForPlannedEventAndOptionDisabledSendsNoReminder(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );
        $this->configuration->setAsBoolean('sendCancelationDeadlineReminder', false);

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForCanceledEventAndOptionEnabledSendsNoReminder(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_CANCELED,
                ],
            ),
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /**
     * @test
     */
    public function sendCancellationDeadlineRemindersForConfirmedEventAndOptionEnabledSendsNoReminder(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_CONFIRMED,
                ],
            ),
        );

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();
    }

    /*
     * Tests concerning the reminders content
     *
     * * sender and recipients
     */

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsEmailWithOrganizerAsRecipient(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertArrayHasKey('MrTest@example.com', $this->getToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsEmailWithTypo3DefaultFromAddressAsSender(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $defaultMailFromAddress = 'system-foo@example.com';
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertArrayHasKey($defaultMailFromAddress, $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersForEventWithTwoOrganizersSendsEmailWithTypo3DefaultFromAddressAsSender(
    ): void {
        $eventUid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'foo', 'email' => 'foo@example.com'],
        );
        $this->testingFramework->createRelation('tx_seminars_seminars_organizers_mm', $eventUid, $organizerUid);
        $this->testingFramework->changeRecord('tx_seminars_seminars', $eventUid, ['organizers' => 2]);

        $defaultMailFromAddress = 'system-foo@example.com';
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertArrayHasKey($defaultMailFromAddress, $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersForEventWithTwoOrganizersSendsEmailWithFirstOrganizerAsReplyTo(): void
    {
        $eventUid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'foo', 'email' => 'foo@example.com'],
        );
        $this->testingFramework->createRelation('tx_seminars_seminars_organizers_mm', $eventUid, $organizerUid);
        $this->testingFramework->changeRecord('tx_seminars_seminars', $eventUid, ['organizers' => 2]);

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system-foo@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Mr. Default';

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertSame(['MrTest@example.com' => 'Mr. Test'], $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersWithoutTypo3DefaultFromAddressSendsEmailWithOrganizerAsSender(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertArrayHasKey('MrTest@example.com', $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersForEventWithTwoOrganizersWithoutTypo3DefaultFromAddressSendsEmailWithFirstOrganizerAsSender(
    ): void {
        $eventUid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'foo', 'email' => 'foo@example.com'],
        );
        $this->testingFramework->createRelation('tx_seminars_seminars_organizers_mm', $eventUid, $organizerUid);
        $this->testingFramework->changeRecord('tx_seminars_seminars', $eventUid, ['organizers' => 2]);

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertArrayHasKey('MrTest@example.com', $this->getFromOfEmail($this->email));
    }

    // customized subject

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithSubjectWithEventTitle(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
                'title' => 'test event',
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString(
            'test event',
            $this->email->getSubject(),
        );
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithSubjectWithDaysUntilBeginDate(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString(
            '2',
            $this->email->getSubject(),
        );
    }

    // customized message

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithMessageWithOrganizerName(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString('Mr. Test', $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithMessageWithEventTitle(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
                'title' => 'test event',
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString('test event', $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithMessageWithEventUid(): void
    {
        $uid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString((string)$uid, $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithMessageWithDaysUntilBeginDate(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString('2', $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersSendsReminderWithMessageWithEventBeginDate(): void
    {
        $this->addSpeaker(
            $this->createSeminarWithOrganizer(
                [
                    'begin_date' => $this->now + self::SECONDS_PER_DAY,
                    'cancelled' => EventInterface::STATUS_PLANNED,
                ],
            ),
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendCancellationDeadlineReminders();

        self::assertStringContainsString(
            \date('Y-m-d', $this->now + self::SECONDS_PER_DAY),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersForEventWithNoRegistrationSendsReminderWithNumberOfRegistrations(): void
    {
        $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString('0', $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function sendRemindersToOrganizersForEventWithOneRegistrationsSendsReminderWithNumberOfRegistrations(): void
    {
        $eventUid = $this->createSeminarWithOrganizer(
            [
                'begin_date' => $this->now + self::SECONDS_PER_DAY,
                'cancelled' => EventInterface::STATUS_CONFIRMED,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'title' => 'test registration',
                'seminar' => $eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEventTakesPlaceReminders();

        self::assertStringContainsString('1', $this->email->getTextBody());
    }
}
