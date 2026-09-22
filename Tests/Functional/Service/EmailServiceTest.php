<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Service;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Organizer;
use OliverKlee\Seminars\Model\Registration;
use OliverKlee\Seminars\Service\EmailService;
use OliverKlee\Seminars\Tests\Unit\Traits\EmailTrait;
use OliverKlee\Seminars\Tests\Unit\Traits\MakeInstanceTrait;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\EmailService
 */
final class EmailServiceTest extends FunctionalTestCase
{
    use EmailTrait;
    use MakeInstanceTrait;

    public const FIXTURES_PATH = __DIR__ . '/Fixtures/EmailService';

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

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'defaultMailFromAddress' => 'system-foo@example.com',
            'defaultMailFromName' => 'Mr. Default',
        ],
    ];

    private EmailService $subject;

    private EventRepository $eventRepository;

    private Event $event;

    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['LANG'] = $languageService;

        $this->get(ConfigurationRegistry::class)->set('plugin.tx_seminars', new DummyConfiguration());

        $this->email = $this->createEmailMock();
        $this->eventRepository = $this->get(EventRepository::class);

        $this->organizer = new Organizer();
        $this->organizer->setData(
            [
                'title' => 'Brain Gourmets',
                'email' => 'organizer@example.com',
            ],
        );
        /** @var Collection<Organizer> $organizers */
        $organizers = new Collection();
        $organizers->add($this->organizer);

        $this->event = new Event();
        $this->event->setData(
            [
                'title' => 'A nice event',
                'registrations' => new Collection(),
                'organizers' => $organizers,
            ],
        );

        $user = new FrontEndUser();
        $user->setData(['name' => 'John Doe', 'email' => 'john.doe@example.com']);
        $registration = new Registration();
        $registration->setData([]);
        $registration->setFrontEndUser($user);
        $registration->setEvent($this->event);
        $this->event->getRegistrations()->add($registration);

        $this->subject = $this->get(EmailService::class);
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(EmailService::class, $this->get(EmailService::class));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithoutFooterNotAppendsFooterSeparatorInTextBody(): void
    {
        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->organizer->setEmailFooter('');

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringNotContainsString('-- ', $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterUsesFooterSeparatorInTextBody(): void
    {
        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->organizer->setEmailFooter('We are here for you.');

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringContainsString("\n-- \n", $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterAppendsFooterInTextBody(): void
    {
        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $footer = 'We are here for you.';
        $this->organizer->setEmailFooter($footer);

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringContainsString($footer, $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterKeepsLinebreaksInTextBody(): void
    {
        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $footer = "We are here for you.\nAlways.";
        $this->organizer->setEmailFooter($footer);

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringContainsString($footer, $result);
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesForTwoRegistrationsSendsTwoEmails(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(2);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesUsesTypo3DefaultFromAddressAsSender(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(2);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');

        self::assertArrayHasKey('system-foo@example.com', $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesForNoTypo3EmailConfiguredUsesFirstOrganizerAsSender(): void
    {
        self::assertIsArray($GLOBALS['TYPO3_CONF_VARS']);
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [];

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(2);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');

        self::assertArrayHasKey('oliver@example.com', $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesEmailUsesFirstOrganizerAsReplyTo(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(2);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');

        self::assertArrayHasKey('oliver@example.com', $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesEmailAppendsFirstOrganizerFooterToMessageBody(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(2);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::exactly(2))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');
        $result = $this->email->getTextBody();
        self::assertIsString($result);

        self::assertStringContainsString("\n-- \nThe one and only", $result);
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesUsesProvidedEmailSubject(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);
        $emailSubject = 'Thank you for your registration.';

        $this->subject->sendPlainTextEmailToRegularAttendees($event, $emailSubject, 'some message body');

        self::assertSame($emailSubject, $this->email->getSubject());
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesNotSendsEmailToUserWithoutEmailAddress(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/Records.csv');
        $event = $this->eventRepository->findByUid(4);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');
    }
}
