<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\SchedulerTasks;

use DateTimeZone;
use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\SchedulerTasks\RegistrationDigest;
use OliverKlee\Seminars\Tests\Unit\Traits\EmailTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\SchedulerTasks\RegistrationDigest
 */
final class RegistrationDigestTest extends FunctionalTestCase
{
    use EmailTrait;

    protected array $coreExtensionsToLoad = ['typo3/cms-scheduler'];

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private DummyConfiguration $configuration;

    private RegistrationDigest $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $configurationData = [
            'plaintextTemplate' => 'EXT:seminars/Resources/Private/Templates/Mail/RegistrationDigest.txt',
            'htmlTemplate' => 'EXT:seminars/Resources/Private/Templates/Mail/RegistrationDigest.html',
            'fromEmail' => 'from@example.com',
            'fromName' => 'the sender',
            'toEmail' => 'to@example.com',
            'toName' => 'the recipient',
            'enable' => '1',
        ];
        $this->configuration = new DummyConfiguration($configurationData);
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars.registrationDigestEmail', $this->configuration);

        $this->email = $this->createEmailMock();
        GeneralUtility::addInstance(MailMessage::class, $this->email);

        $now = new \DateTimeImmutable('2018-04-26 12:42:23', new DateTimeZone('UTC'));
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('date', new DateTimeAspect($now));

        $this->subject = $this->get(RegistrationDigest::class);
    }

    protected function tearDown(): void
    {
        // Manually purge the TYPO3 FIFO queue
        GeneralUtility::makeInstance(MailMessage::class);

        ConfigurationRegistry::purgeInstance();
        MapperRegistry::purgeInstance();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function isSingleton(): void
    {
        self::assertInstanceOf(SingletonInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function executeForNoEventsInDatabaseDoesNotSendEmail(): void
    {
        $this->email->expects(self::never())->method('send');

        $this->subject->execute();
    }

    /**
     * @test
     */
    public function executeForEventWithoutRegistrationsNotSendEmail(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithoutRegistrations.csv');

        $this->email->expects(self::never())->method('send');

        $this->subject->execute();
    }

    /**
     * @test
     */
    public function executeForEventWithOneAlreadyNotifiedRegistrationDoesNotSendEmail(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotifiedRegistration.csv');

        $this->email->expects(self::never())->method('send');

        $this->subject->execute();
    }

    /**
     * @test
     */
    public function executeForEventWithOneNotNotifiedRegistrationButFeatureDisabledDoesNotSendsEmail(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');
        $this->configuration->setAsString('enable', '0');

        $this->email->expects(self::never())->method('send');

        $this->subject->execute();
    }

    /**
     * @test
     */
    public function executeForEventWithOneNotNotifiedRegistrationSendsEmail(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->email->expects(self::once())->method('send');

        $this->subject->execute();
    }

    /**
     * @test
     */
    public function emailUsesSenderFromConfiguration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $fromEmail = 'jane@example.com';
        $fromName = 'Jane Doe';
        $this->configuration->setAsString('fromEmail', $fromEmail);
        $this->configuration->setAsString('fromName', $fromName);

        $this->subject->execute();

        self::assertSame([$fromEmail => $fromName], $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function emailUsesToFromConfiguration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $toEmail = 'joe@example.com';
        $toName = 'Joe Doe';
        $this->configuration->setAsString('toEmail', $toEmail);
        $this->configuration->setAsString('toName', $toName);

        $this->subject->execute();

        self::assertSame([$toEmail => $toName], $this->getToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function emailHasLocalizedSubject(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $expectedSubject = LocalizationUtility::translate('registrationDigestEmail_Subject', 'seminars');
        self::assertSame($expectedSubject, $this->email->getSubject());
    }

    /**
     * @test
     */
    public function emailTextBodyContainsEventTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $textBody = $this->email->getTextBody();
        self::assertIsString($textBody);
        self::assertStringContainsString('some event', $textBody);
    }

    /**
     * @test
     */
    public function emailHtmlBodyContainsEventTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $htmlBody = $this->email->getHtmlBody();
        self::assertIsString($htmlBody);
        self::assertStringContainsString('some event', $htmlBody);
    }

    /**
     * @test
     */
    public function emailTextBodyContainsEventDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $textBody = $this->email->getTextBody();
        self::assertIsString($textBody);
        self::assertStringContainsString('2049-12-31', $textBody);
    }

    /**
     * @test
     */
    public function emailHtmlBodyContainsEventDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $htmlBody = $this->email->getHtmlBody();
        self::assertIsString($htmlBody);
        self::assertStringContainsString('2049-12-31', $htmlBody);
    }

    /**
     * @test
     */
    public function emailTextBodyContainsFullNameOfUser(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $textBody = $this->email->getTextBody();
        self::assertIsString($textBody);
        self::assertStringContainsString('the dragonborn', $textBody);
    }

    /**
     * @test
     */
    public function emailHtmlBodyContainsFullNameOfUser(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $htmlBody = $this->email->getHtmlBody();
        self::assertIsString($htmlBody);
        self::assertStringContainsString('the dragonborn', $htmlBody);
    }

    /**
     * @test
     */
    public function executeForNoMailSentKeepsEventUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotifiedRegistration.csv');

        $this->subject->execute();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotifiedRegistration.csv');
    }

    /**
     * @test
     */
    public function executeForMailSentUpdateEventUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithOneNotNotifiedRegistration.csv');

        $this->subject->execute();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/RegistrationDigest/EventWithLastDigestDateSetToNow.csv');
    }
}
