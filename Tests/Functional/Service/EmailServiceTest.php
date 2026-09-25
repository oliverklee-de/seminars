<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Service;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Service\EmailService;
use OliverKlee\Seminars\Tests\Unit\Traits\EmailTrait;
use OliverKlee\Seminars\Tests\Unit\Traits\MakeInstanceTrait;
use OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper;
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

    private EventRepository $eventRepository;

    private EventMapper $eventMapper;

    private EmailService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        $GLOBALS['LANG'] = $languageService;

        $this->get(ConfigurationRegistry::class)->set('plugin.tx_seminars', new DummyConfiguration());

        $this->email = $this->createEmailMock();
        $this->eventRepository = $this->get(EventRepository::class);
        $this->eventMapper = $this->get(MapperRegistry::class)->getByClassName(EventMapper::class);

        $this->subject = $this->get(EmailService::class);
    }

    protected function tearDown(): void
    {
        // This is temporarily necessary to remove the mapper registry instance as long as we haven't switched the
        // tested class to also use the Extbase event model.
        MapperRegistry::purgeInstance();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(EmailService::class, $this->get(EmailService::class));
    }

    // Tests for sendEmailToAttendees

    /**
     * @test
     */
    public function sendEmailToAttendeesForEventWithoutRegistrationsNotSendsMail(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithoutRegistrations.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::exactly(0))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesUsesTypo3DefaultFromAddressAsSender(): void
    {
        self::assertTrue(is_array($GLOBALS['TYPO3_CONF_VARS']) && is_array($GLOBALS['TYPO3_CONF_VARS']['MAIL']));
        $defaultMailFromAddress = 'system-foo@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        self::assertTrue(is_array($GLOBALS['TYPO3_CONF_VARS']) && is_array($GLOBALS['TYPO3_CONF_VARS']['MAIL']));
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        self::assertArrayHasKey($defaultMailFromAddress, $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesUsesFirstOrganizerAsReplyTo(): void
    {
        self::assertTrue(is_array($GLOBALS['TYPO3_CONF_VARS']) && is_array($GLOBALS['TYPO3_CONF_VARS']['MAIL']));
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system-foo@example.com';
        self::assertTrue(is_array($GLOBALS['TYPO3_CONF_VARS']) && is_array($GLOBALS['TYPO3_CONF_VARS']['MAIL']));
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Mr. Default';

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        self::assertArrayHasKey('organizer@example.com', $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesWithoutTypo3DefaultFromAddressUsesFirstOrganizerAsSender(): void
    {
        self::assertTrue(is_array($GLOBALS['TYPO3_CONF_VARS']) && is_array($GLOBALS['TYPO3_CONF_VARS']['MAIL']));
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        self::assertTrue(is_array($GLOBALS['TYPO3_CONF_VARS']) && is_array($GLOBALS['TYPO3_CONF_VARS']['MAIL']));
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        self::assertArrayHasKey('organizer@example.com', $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsEmailWithProvidedSubject(): void
    {
        $subject = 'Bonjour!';

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, $subject, 'Hello!');

        self::assertSame($subject, $this->email->getSubject());
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesReplacesEventTitleInSubject(): void
    {
        $subjectPrefix = 'Event title goes here: ';

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, $subjectPrefix . '%eventTitle', 'Hello!');

        self::assertSame($subjectPrefix . 'A nice event', $this->email->getSubject());
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesReplacesEventDateInSubject(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $formattedDate = (new DateRangeViewHelper())->render($event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $subjectPrefix = 'Event date goes here: ';
        $this->subject->sendEmailToAttendees($event, $subjectPrefix . '%eventDate', 'Hello!');

        self::assertSame($subjectPrefix . $formattedDate, $this->email->getSubject());
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsEmailWithProvidedBody(): void
    {
        $body = 'Life is good.';

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', $body);
        $result = $this->email->getTextBody();
        self::assertIsString($result);

        self::assertStringContainsString($body, $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsToFirstAttendee(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        self::assertSame(['john.doe@example.com' => 'John Doe'], $this->getToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForTwoRegistrationsSendsTwoEmails(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithTwoRegistrations.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturn(true);
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForRegistrationWithoutUserNotSendsMail(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistrationWithoutUser.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::exactly(0))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForAttendeeWithoutEmailAddressNotSendsMail(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistrationWithoutEmailAddress.csv',
        );
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::exactly(0))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsSalutationIntoMailTextWithSalutationMarker(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($event, 'Bonjour!', '%salutation (This was the salutation)');
        $result = $this->email->getTextBody();
        self::assertIsString($result);

        self::assertStringContainsString('John Doe', $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsUserNameIntoMailTextWithUserNameMarker(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello %userName!');
        $result = $this->email->getTextBody();
        self::assertIsString($result);

        self::assertStringContainsString('Hello John Doe!', $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsEventTitleIntoMailTextWithEventTitleMarker(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Event: %eventTitle');
        $result = $this->email->getTextBody();
        self::assertIsString($result);

        self::assertStringContainsString('Event: A nice event', $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsEventDateIntoMailTextWithEventDateMarker(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOneRegistration.csv');
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        $formattedDate = (new DateRangeViewHelper())->render($event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Date: %eventDate');
        $result = $this->email->getTextBody();
        self::assertIsString($result);

        self::assertStringContainsString('Date: ' . $formattedDate, $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithoutFooterNotAppendsFooterSeparatorInTextBody(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOrganizerWithoutFooterAndOneRegistration.csv',
        );
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringNotContainsString('-- ', $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterUsesFooterSeparatorInTextBody(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOrganizerWithFooterAndOneRegistration.csv',
        );
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringContainsString("\n-- \n", $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterAppendsFooterInTextBody(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendEmailToAttendees/EventWithOrganizerWithFooterAndOneRegistration.csv',
        );
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringContainsString('We are here for you.', $result);
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterKeepsLinebreaksInTextBody(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH
            . '/sendEmailToAttendees/EventWithOrganizerWithFooterWithLinebreakAndOneRegistration.csv',
        );
        $event = $this->eventMapper->find(1);
        self::assertInstanceOf(Event::class, $event);

        self::assertInstanceOf(MailMessage::class, $this->email);
        self::assertInstanceOf(MockObject::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');

        $this->subject->sendEmailToAttendees($event, 'Bonjour!', 'Hello!');

        $result = $this->email->getTextBody();
        self::assertIsString($result);
        self::assertStringContainsString("We are here for you.\nAlways.", $result);
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesForTwoRegistrationsSendsTwoEmails(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/EventWithTwoRegistrations.csv',
        );
        $event = $this->eventRepository->findByUid(1);
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
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/EventWithRegistration.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::once())->method('send');
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

        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/EventWithRegistration.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');

        self::assertArrayHasKey('oliver@example.com', $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesEmailUsesFirstOrganizerAsReplyTo(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/EventWithRegistration.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');

        self::assertArrayHasKey('oliver@example.com', $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendPlainTextEmailToRegularAttendeesEmailAppendsFirstOrganizerFooterToMessageBody(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH
            . '/sendPlainTextEmailToRegularAttendees/EventWithTwoOrganizersWithFooterAndRegistration.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::once())->method('send');
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
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/EventWithRegistration.csv',
        );
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
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/sendPlainTextEmailToRegularAttendees/EventWithRegistrationWithoutEmail.csv',
        );
        $event = $this->eventRepository->findByUid(1);
        self::assertInstanceOf(SingleEvent::class, $event);

        $this->email->expects(self::never())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendPlainTextEmailToRegularAttendees($event, 'foo', 'some message body');
    }
}
