<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\SchedulerTasks;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Configuration\PageFinder;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\SchedulerTasks\MailNotifier;
use OliverKlee\Seminars\SchedulerTasks\RegistrationDigest;
use OliverKlee\Seminars\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\SchedulerTasks\MailNotifier
 */
final class MailNotifierTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/MailNotifier';

    private const ASSERTIONS_PATH = __DIR__ . '/Assertions/MailNotifier';

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

    /**
     * @var EmailService&MockObject
     */
    private EmailService $emailService;

    private EventRepository $eventRepository;

    /**
     * @var RegistrationDigest&MockObject
     */
    private RegistrationDigest $registrationDigestMock;

    private MailNotifier $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = $this
            ->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $now = new \DateTimeImmutable('2018-04-26 12:42:23');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect($now));

        $configurationRegistry = $this->get(ConfigurationRegistry::class);
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $configurationRegistry->set('plugin.tx_seminars', new DummyConfiguration());

        $this->eventRepository = $this->get(EventRepository::class);

        $this->emailService = $this->createMock(EmailService::class);
        GeneralUtility::setSingletonInstance(EmailService::class, $this->emailService);

        $this->registrationDigestMock = $this->createMock(RegistrationDigest::class);
        GeneralUtility::setSingletonInstance(RegistrationDigest::class, $this->registrationDigestMock);

        $this->subject = new MailNotifier();
    }

    protected function tearDown(): void
    {
        PageFinder::purgeInstance();
        GeneralUtility::resetSingletonInstances([]);

        parent::tearDown();
    }

    // Basic tests

    /**
     * @test
     */
    public function classIsSchedulerTask(): void
    {
        self::assertInstanceOf(AbstractTask::class, $this->subject);
    }

    /**
     * @test
     */
    public function setConfigurationPageUidSetsConfigurationPageUid(): void
    {
        $uid = 42;
        $this->subject->setConfigurationPageUid($uid);

        $result = $this->subject->getConfigurationPageUid();

        self::assertSame($uid, $result);
    }

    /**
     * @test
     */
    public function executeWithoutPageConfigurationReturnsFalse(): void
    {
        $result = (new MailNotifier())->execute();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function executeWithZeroPageConfigurationReturnsFalse(): void
    {
        $subject = new MailNotifier();
        $subject->setConfigurationPageUid(0);

        $result = $subject->execute();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function executeWithPageConfigurationReturnsTrue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');

        $subject = new MailNotifier();
        $subject->setConfigurationPageUid(1);

        $result = $subject->execute();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function executeWithPageConfigurationCallsAllSeparateSteps(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $subject = $this->getMockBuilder(MailNotifier::class)->onlyMethods(
            [
                'sendEventTakesPlaceReminders',
                'sendCancellationDeadlineReminders',
                'automaticallyChangeEventStatuses',
            ],
        )->getMock();
        $subject->setConfigurationPageUid(1);

        $subject->expects(self::once())->method('sendEventTakesPlaceReminders');
        $subject->expects(self::once())->method('sendCancellationDeadlineReminders');
        $subject->expects(self::once())->method('automaticallyChangeEventStatuses');

        $subject->execute();
    }

    /**
     * @test
     */
    public function executeWithoutPageConfigurationNotCallsAnySeparateStep(): void
    {
        $subject = $this->getMockBuilder(MailNotifier::class)->onlyMethods(
            [
                'sendEventTakesPlaceReminders',
                'sendCancellationDeadlineReminders',
                'automaticallyChangeEventStatuses',
            ],
        )->getMock();
        $subject->setConfigurationPageUid(0);

        $subject->expects(self::never())->method('sendEventTakesPlaceReminders');
        $subject->expects(self::never())->method('sendCancellationDeadlineReminders');
        $subject->expects(self::never())->method('automaticallyChangeEventStatuses');

        $subject->execute();
    }

    /**
     * @test
     */
    public function executeWithPageConfigurationExecutesRegistrationDigest(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $subject = $this->getMockBuilder(MailNotifier::class)->onlyMethods(
            [
                'sendEventTakesPlaceReminders',
                'sendCancellationDeadlineReminders',
                'automaticallyChangeEventStatuses',
            ],
        )->getMock();
        $subject->setConfigurationPageUid(1);
        $this->registrationDigestMock->expects(self::once())->method('execute');

        $subject->execute();
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function eventsWithoutStatusChangeDataProvider(): array
    {
        return [
            'planned, but without automation' => ['PlannedEventWithoutAutomation.csv'],
            'hidden, planned, with automation' => ['HiddenPlannedEventWithAutomation.csv'],
            'canceled, with automation' => ['CanceledEventWithAutomation.csv'],
            'confirmed, with automation' => ['ConfirmedEventWithAutomation.csv'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider eventsWithoutStatusChangeDataProvider
     */
    public function automaticallyChangeEventStatusesForNoStatusChangesKeepsEventsUnchanged(string $file): void
    {
        $fileWithPath = '/automaticallyChangeEventStatuses/' . $file;
        $this->importCSVDataSet(self::FIXTURES_PATH . $fileWithPath);

        $this->subject->automaticallyChangeEventStatuses();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . $fileWithPath);
    }

    /**
     * @test
     *
     * @dataProvider eventsWithoutStatusChangeDataProvider
     */
    public function automaticallyChangeEventStatusesForNoStatusChangesSendsNoEmail(string $file): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/' . $file);

        $this->emailService->expects(self::never())->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneStatusChangeMarksEventAsConfirmed(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithAutomation.csv',
        );

        $this->subject->automaticallyChangeEventStatuses();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/automaticallyChangeEventStatuses/ConfirmedEvent.csv');
    }

    /**
     * @test
     *
     * @dataProvider eventsWithoutStatusChangeDataProvider
     */
    public function automaticallyChangeEventStatusesForOneEventWithNoStatusChangeNeededNotSendsEmail(string $file): void
    {
        $fileWithPath = '/automaticallyChangeEventStatuses/' . $file;
        $this->importCSVDataSet(self::FIXTURES_PATH . $fileWithPath);

        $this->emailService->expects(self::never())->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithConfirmedStatusChangeSendsEmail(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithAutomation.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(EventDateInterface::class, $event);

        $this->emailService
            ->expects(self::once())->method('sendEmailToAttendees')
            ->with($event, self::anything(), self::anything());

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithConfirmedStatusChangeSendsEmailWithConfirmSubject(
    ): void {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithAutomation.csv',
        );

        $emailSubject = LocalizationUtility::translate('email-event-confirmed-subject', 'seminars');
        self::assertIsString($emailSubject);
        $this->emailService
            ->expects(self::once())->method('sendEmailToAttendees')
            ->with(self::anything(), $emailSubject, self::anything());

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithConfirmedStatusChangeSendsEmailWithConfirmBody(
    ): void {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithAutomation.csv',
        );

        $emailBody = LocalizationUtility::translate('email-event-confirmed-body', 'seminars');
        self::assertIsString($emailBody);
        $this->emailService
            ->expects(self::once())->method('sendEmailToAttendees')
            ->with(self::anything(), self::anything(), $emailBody);

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForTwoEventsWithConfirmedStatusChangeSendsTwoEmails(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/TwoPlannedEventsWithAutomation.csv',
        );

        $this->emailService->expects(self::exactly(2))->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithCanceledStatusChangeSendsEmail(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithUnsufficientRegistrations.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(EventDateInterface::class, $event);

        $this->emailService
            ->expects(self::once())->method('sendEmailToAttendees')
            ->with($event, self::anything(), self::anything());

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithCanceledStatusChangeSendsEmailWithCancelSubject(
    ): void {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithUnsufficientRegistrations.csv',
        );

        $emailSubject = LocalizationUtility::translate('email-event-canceled-subject', 'seminars');
        self::assertIsString($emailSubject);
        $this->emailService
            ->expects(self::once())->method('sendEmailToAttendees')
            ->with(self::anything(), $emailSubject, self::anything());

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithCanceledStatusChangeSendsEmailWithCancelBody(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/PlannedEventWithUnsufficientRegistrations.csv',
        );

        $emailBody = LocalizationUtility::translate('email-event-canceled-body', 'seminars');
        self::assertIsString($emailBody);
        $this->emailService
            ->expects(self::once())->method('sendEmailToAttendees')
            ->with(self::anything(), self::anything(), $emailBody);

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForTwoEventsWithCanceledStatusChangeSendsTwoEmails(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/automaticallyChangeEventStatuses/TwoPlannedEventsWithAutomation.csv',
        );

        $this->emailService->expects(self::exactly(2))->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }
}
