<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\SchedulerTasks;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Configuration\PageFinder;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\SchedulerTasks\MailNotifier;
use OliverKlee\Seminars\SchedulerTasks\RegistrationDigest;
use OliverKlee\Seminars\Service\EmailService;
use OliverKlee\Seminars\Service\EventStatusService;
use PHPUnit\Framework\MockObject\MockObject;
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
     * @var EventStatusService&MockObject
     */
    private EventStatusService $eventStatusService;

    /**
     * @var EmailService&MockObject
     */
    private EmailService $emailService;

    /**
     * @var EventMapper&MockObject
     */
    private EventMapper $eventMapper;

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

        $configurationRegistry = $this->get(ConfigurationRegistry::class);
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $configurationRegistry->set('plugin.tx_seminars', new DummyConfiguration());

        $this->eventMapper = $this->createMock(EventMapper::class);
        $this->get(MapperRegistry::class)->setByClassName(EventMapper::class, $this->eventMapper);

        $this->eventStatusService = $this->createMock(EventStatusService::class);
        GeneralUtility::setSingletonInstance(EventStatusService::class, $this->eventStatusService);

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
     * @test
     */
    public function automaticallyChangeEventStatusesForNoEventsForStatusChangeNotRequestsStatusChange(): void
    {
        $events = new Collection();
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->expects(self::never())->method('updateStatusAndSave');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForNoEventsForStatusChangeSendsNoEmail(): void
    {
        $events = new Collection();
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->emailService->expects(self::never())->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventForStatusChangeRequestsStatusChangeWithThatEvent(): void
    {
        $events = new Collection();
        $event = new Event();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService
            ->expects(self::once())
            ->method('updateStatusAndSave')->with($event)
            ->willReturn(false);

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithNoStatusChangeNeededNotSendsEmail(): void
    {
        $events = new Collection();
        $event = new Event();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(false);

        $this->emailService->expects(self::never())->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithConfirmedStatusChangeSendsEmail(): void
    {
        $events = new Collection();
        $event = new Event();
        $event->confirm();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

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
        $events = new Collection();
        $event = new Event();
        $event->confirm();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

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
        $events = new Collection();
        $event = new Event();
        $event->confirm();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

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
        $events = new Collection();
        $event1 = new Event();
        $event1->confirm();
        $events->add($event1);
        $event2 = new Event();
        $event2->confirm();
        $events->add($event2);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

        $this->emailService->expects(self::exactly(2))->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithCanceledStatusChangeSendsEmail(): void
    {
        $events = new Collection();
        $event = new Event();
        $event->cancel();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

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
        $events = new Collection();
        $event = new Event();
        $event->cancel();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

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
        $events = new Collection();
        $event = new Event();
        $event->cancel();
        $events->add($event);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

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
        $events = new Collection();
        $event1 = new Event();
        $event1->cancel();
        $events->add($event1);
        $event2 = new Event();
        $event2->cancel();
        $events->add($event2);
        $this->eventMapper->expects(self::once())->method(
            'findForAutomaticStatusChange',
        )->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

        $this->emailService->expects(self::exactly(2))->method('sendEmailToAttendees');

        $this->subject->automaticallyChangeEventStatuses();
    }

    /**
     * @test
     */
    public function automaticallyChangeEventStatusesForOneEventWithPlannedStatusChangeThrowsException(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        $events = new Collection();
        $event = new Event();
        $event->setStatus(EventInterface::STATUS_PLANNED);
        $events->add($event);
        $this->eventMapper->expects(self::once())->method('findForAutomaticStatusChange')->willReturn($events);

        $this->eventStatusService->method('updateStatusAndSave')->willReturn(true);

        $this->subject->automaticallyChangeEventStatuses();
    }
}
