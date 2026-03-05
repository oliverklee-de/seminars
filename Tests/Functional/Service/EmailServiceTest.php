<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Service;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Testing\CacheNullifyer;
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\EmailService
 */
final class EmailServiceTest extends FunctionalTestCase
{
    use EmailTrait;
    use MakeInstanceTrait;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private EmailService $subject;

    private Event $event;

    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(CacheNullifyer::class)->setAllCoreCaches();

        $languageService = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $GLOBALS['LANG'] = $languageService;

        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', new DummyConfiguration());

        $this->email = $this->createEmailMock();

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

        $this->subject = new EmailService();
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
}
