<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Service;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\FrontEnd\DefaultController;
use OliverKlee\Seminars\Hooks\Interfaces\RegistrationEmail;
use OliverKlee\Seminars\Mapper\RegistrationMapper;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Service\RegistrationManager;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyEvent;
use OliverKlee\Seminars\Tests\Unit\Traits\EmailTrait;
use OliverKlee\Seminars\Tests\Unit\Traits\MakeInstanceTrait;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\RegistrationManager
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class RegistrationManagerTest extends FunctionalTestCase
{
    use LanguageHelper;
    use EmailTrait;
    use MakeInstanceTrait;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    /**
     * @var positive-int
     */
    private int $now;

    private RegistrationManager $subject;

    private TestingFramework $testingFramework;

    /**
     * @var positive-int
     */
    private int $seminarUid;

    private TestingLegacyEvent $seminar;

    private int $frontEndUserUid = 0;

    private int $loginPageUid = 0;

    private int $registrationPageUid = 0;

    private DefaultController $pi1;

    private TestingLegacyEvent $fullyBookedSeminar;

    /**
     * backed-up extension configuration of the TYPO3 configuration variables
     *
     * @var array<string, mixed>
     */
    private array $extConfBackup = [];

    /**
     * @var list<class-string>
     */
    private array $mockedClassNames = [];

    private DummyConfiguration $configuration;

    private int $rootPageUid;

    protected function setUp(): void
    {
        parent::setUp();

        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));
        $this->now = (int)$context->getPropertyFromAspect('date', 'timestamp');

        $this->extConfBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'];

        $this->testingFramework = new TestingFramework('tx_seminars');
        $this->rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $this->rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($this->rootPageUid);
        $this->getLanguageService();

        $this->email = $this->createEmailMock();
        $secondEmail = $this->createEmailMock();
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $secondEmail);

        $configurationRegistry = ConfigurationRegistry::getInstance();
        $this->configuration = new DummyConfiguration(
            [
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html',
            ],
        );
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);
        $configurationRegistry->set('plugin.tx_seminars._LOCAL_LANG.default', new DummyConfiguration());
        $configurationRegistry->set('config', new DummyConfiguration());
        $configurationRegistry->set('page.config', new DummyConfiguration());

        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'test organizer',
                'email' => 'mail@example.com',
                'email_footer' => 'organizer footer',
            ],
        );
        $this->seminarUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'test event',
                'subtitle' => 'juggling with burning chainsaws',
                'begin_date' => $this->now + 1000,
                'end_date' => $this->now + 2000,
                'attendees_min' => 1,
                'attendees_max' => 10,
                'needs_registration' => 1,
                'organizers' => 1,
            ],
        );
        $this->testingFramework->createRelation('tx_seminars_seminars_organizers_mm', $this->seminarUid, $organizerUid);

        $this->seminar = new TestingLegacyEvent($this->seminarUid);
        $this->subject = $this->get(RegistrationManager::class);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        $this->purgeMockedInstances();

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] = $this->extConfBackup;

        parent::tearDown();
    }

    /**
     * Creates a dummy login page and registration page and stores their UIDs
     * in `$this->loginPageUid` and `$this->registrationPageUid`.
     *
     * In addition, it provides the fixture's configuration with the UIDs.
     */
    private function createFrontEndPages(): void
    {
        $this->loginPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $this->loginPageUid, ['slug' => '/login']);
        $this->registrationPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $this->registrationPageUid, ['slug' => '/eventRegistration']);

        $this->pi1 = new DefaultController();

        $this->pi1->init(
            [
                'isStaticTemplateLoaded' => 1,
                'loginPID' => $this->loginPageUid,
                'registerPID' => $this->registrationPageUid,
            ],
        );
    }

    /**
     * Creates a FE user, stores it UID in $this->frontEndUserUid and logs it in.
     */
    private function createAndLogInFrontEndUser(): void
    {
        $this->frontEndUserUid = $this->testingFramework->createAndLoginFrontEndUser();
    }

    /**
     * Creates a seminar which is booked out.
     */
    private function createBookedOutSeminar(): void
    {
        $this->fullyBookedSeminar = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'title' => 'test event',
                    'begin_date' => $this->now + 1000,
                    'end_date' => $this->now + 2000,
                    'attendees_max' => 10,
                    'deadline_registration' => $this->now + 1000,
                    'needs_registration' => 1,
                    'queue_size' => 0,
                ],
            ),
        );
        $this->fullyBookedSeminar->setNumberOfAttendances(10);
    }

    /**
     * Returns and creates a registration.
     *
     * A new front-end user will be created and the event in $this->seminar will be used.
     *
     * @return LegacyRegistration the created registration
     */
    private function createRegistration(): LegacyRegistration
    {
        $frontEndUserUid = $this->testingFramework->createFrontEndUser(
            '',
            [
                'name' => 'Harry Callagan',
                'email' => 'foo@bar.com',
            ],
        );

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $frontEndUserUid,
                'food' => 'something nice to eat',
                'accommodation' => 'a nice, dry place',
                'interests' => 'learning Ruby on Rails',
            ],
        );

        return new LegacyRegistration($registrationUid);
    }

    /**
     * Adds an instance to the Typo3 instance FIFO buffer used by `GeneralUtility::makeInstance()`
     * and registers it for purging in `tearDown()`.
     *
     * In case of a failing test or an exception in the test before the instance is taken
     * from the FIFO buffer, the instance would stay in the buffer and make following tests
     * fail. This function adds it to the list of instances to purge in `tearDown()` in addition
     * to `GeneralUtility::addInstance()`.
     *
     * @param class-string $className
     */
    private function addMockedInstance(string $className, object $instance): void
    {
        GeneralUtility::addInstance($className, $instance);
        $this->mockedClassNames[] = $className;
    }

    /**
     * Purges possibly leftover instances from the Typo3 instance FIFO buffer used by
     * `GeneralUtility::makeInstance()`.
     */
    private function purgeMockedInstances(): void
    {
        foreach ($this->mockedClassNames as $className) {
            GeneralUtility::makeInstance($className);
        }

        $this->mockedClassNames = [];
    }

    // Tests for the utility functions

    /**
     * @test
     */
    public function createFrontEndPagesCreatesNonZeroLoginPageUid(): void
    {
        $this->createFrontEndPages();

        self::assertGreaterThan(
            0,
            $this->loginPageUid,
        );
    }

    /**
     * @test
     */
    public function createFrontEndPagesCreatesNonZeroRegistrationPageUid(): void
    {
        $this->createFrontEndPages();

        self::assertGreaterThan(
            0,
            $this->registrationPageUid,
        );
    }

    /**
     * @test
     */
    public function createFrontEndPagesCreatesPi1(): void
    {
        $this->createFrontEndPages();

        self::assertNotNull(
            $this->pi1,
        );
        self::assertInstanceOf(
            DefaultController::class,
            $this->pi1,
        );
    }

    /**
     * @test
     */
    public function createAndLogInFrontEndUserCreatesNonZeroUserUid(): void
    {
        $this->createAndLogInFrontEndUser();

        self::assertGreaterThan(
            0,
            $this->frontEndUserUid,
        );
    }

    /**
     * @test
     */
    public function createAndLogInFrontEndUserLogsInFrontEndUser(): void
    {
        $this->createAndLogInFrontEndUser();

        self::assertTrue(GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->isLoggedIn());
    }

    /**
     * @test
     */
    public function createBookedOutSeminarSetsSeminarInstance(): void
    {
        $this->createBookedOutSeminar();

        self::assertInstanceOf(
            LegacyEvent::class,
            $this->fullyBookedSeminar,
        );
    }

    /**
     * @test
     */
    public function createdBookedOutSeminarHasUidGreaterZero(): void
    {
        $this->createBookedOutSeminar();

        self::assertTrue(
            $this->fullyBookedSeminar->getUid() > 0,
        );
    }

    /**
     * @test
     */
    public function mockedInstancesListInitiallyHasTwoInstances(): void
    {
        self::assertCount(2, $this->mockedClassNames);
    }

    /**
     * @test
     */
    public function addMockedInstanceAddsClassnameToList(): void
    {
        $mockedInstance = $this->createMock(\stdClass::class);
        /** @var class-string<\stdClass&MockObject> $mockedClassName */
        $mockedClassName = \get_class($mockedInstance);

        $this->addMockedInstance($mockedClassName, $mockedInstance);
        // manually purge the Typo3 FIFO here, as purgeMockedInstances() is not tested yet
        GeneralUtility::makeInstance($mockedClassName);

        self::assertCount(3, $this->mockedClassNames);
        self::assertSame($mockedClassName, $this->mockedClassNames[2]);
    }

    /**
     * @test
     */
    public function addMockedInstanceAddsInstanceToTypo3InstanceBuffer(): void
    {
        $mockedInstance = $this->createMock(\stdClass::class);
        /** @var class-string<\stdClass&MockObject> $mockedClassName */
        $mockedClassName = \get_class($mockedInstance);

        // manually purge the Typo3 FIFO here, as purgeMockedInstances() is not tested yet
        GeneralUtility::makeInstance($mockedClassName);

        $this->addMockedInstance($mockedClassName, $mockedInstance);

        self::assertSame($mockedInstance, GeneralUtility::makeInstance($mockedClassName));
    }

    /**
     * @test
     */
    public function purgeMockedInstancesRemovesClassnameFromList(): void
    {
        $mockedInstance = $this->createMock(\stdClass::class);
        /** @var class-string<\stdClass&MockObject> $mockedClassName */
        $mockedClassName = \get_class($mockedInstance);
        $this->addMockedInstance($mockedClassName, $mockedInstance);

        $this->purgeMockedInstances();
        // manually purge the Typo3 FIFO here, as purgeMockedInstances() is not tested for that yet
        GeneralUtility::makeInstance($mockedClassName);

        self::assertEmpty($this->mockedClassNames);
    }

    /**
     * @test
     */
    public function purgeMockedInstancesRemovesInstanceFromTypo3InstanceBuffer(): void
    {
        $mockedInstance = $this->createMock(\stdClass::class);
        /** @var class-string<\stdClass&MockObject> $mockedClassName */
        $mockedClassName = \get_class($mockedInstance);
        $this->addMockedInstance($mockedClassName, $mockedInstance);

        $this->purgeMockedInstances();

        self::assertNotSame($mockedInstance, GeneralUtility::makeInstance($mockedClassName));
    }

    // Tests concerning canRegisterIfLoggedIn

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedOutUserAndSeminarRegistrationOpenReturnsTrue(): void
    {
        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForPriceOnRequestReturnsFalse(): void
    {
        $this->seminar->setPriceOnRequest(true);

        self::assertFalse($this->subject->canRegisterIfLoggedIn($this->seminar));
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedInUserAndRegistrationOpenReturnsTrue(): void
    {
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedInButAlreadyRegisteredUserReturnsFalse(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $this->testingFramework->createAndLoginFrontEndUser(),
                'seminar' => $this->seminarUid,
            ],
        );

        self::assertFalse(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedInButAlreadyRegisteredAndWithMultipleRegistrationsAllowedIsTrue(
    ): void {
        $this->seminar->setAllowsMultipleRegistrations(true);

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $this->testingFramework->createAndLoginFrontEndUser(),
                'seminar' => $this->seminarUid,
            ],
        );

        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedInButBlockedUserReturnsFalse(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $this->testingFramework->createAndLoginFrontEndUser(),
                'seminar' => $this->seminarUid,
            ],
        );

        $event = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'begin_date' => $this->now + 1000,
                    'end_date' => $this->now + 2000,
                    'attendees_max' => 10,
                    'deadline_registration' => $this->now + 1000,
                ],
            ),
        );

        self::assertFalse(
            $this->subject->canRegisterIfLoggedIn($event),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedOutUserAndFullyBookedSeminarReturnsFalse(): void
    {
        $this->createBookedOutSeminar();

        self::assertFalse(
            $this->subject->canRegisterIfLoggedIn($this->fullyBookedSeminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedOutUserAndCanceledSeminarReturnsFalse(): void
    {
        $this->seminar->setStatus(EventInterface::STATUS_CANCELED);

        self::assertFalse(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedOutUserAndSeminarWithoutRegistrationReturnsFalse(): void
    {
        $this->seminar->setAttendancesMax(0);
        $this->seminar->setNeedsRegistration(false);

        self::assertFalse(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedOutUserAndSeminarWithUnlimitedVacanciesReturnsTrue(): void
    {
        $this->seminar->setUnlimitedVacancies();

        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedInUserAndSeminarWithUnlimitedVacanciesReturnsTrue(): void
    {
        $this->seminar->setUnlimitedVacancies();
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedOutUserAndFullyBookedSeminarWithQueueReturnsTrue(): void
    {
        $this->seminar->setAttendancesMax(5);
        $this->seminar->setNumberOfAttendances(5);
        $this->seminar->setRegistrationQueue(true);

        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInForLoggedInUserAndFullyBookedSeminarWithQueueReturnsTrue(): void
    {
        $this->seminar->setAttendancesMax(5);
        $this->seminar->setNumberOfAttendances(5);
        $this->seminar->setRegistrationQueue(true);
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertTrue(
            $this->subject->canRegisterIfLoggedIn($this->seminar),
        );
    }

    // Tests concerning canRegisterIfLoggedInMessage

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedOutUserAndSeminarRegistrationOpenReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedInUserAndRegistrationOpenReturnsEmptyString(): void
    {
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedInButAlreadyRegisteredUserReturnsAlreadyRegisteredMessage(
    ): void {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $this->testingFramework->createAndLoginFrontEndUser(),
                'seminar' => $this->seminarUid,
            ],
        );

        self::assertSame(
            $this->translate('message_alreadyRegistered'),
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedInButAlreadyRegisteredAndMultipleRegistrationsAllowedIsEmpty(
    ): void {
        $this->seminar->setAllowsMultipleRegistrations(true);

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $this->testingFramework->createAndLoginFrontEndUser(),
                'seminar' => $this->seminarUid,
            ],
        );

        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedOutUserAndFullyBookedSeminarReturnsFullyBookedMessage(): void
    {
        $this->createBookedOutSeminar();

        self::assertSame(
            $this->translate('message_noVacancies'),
            $this->subject->canRegisterIfLoggedInMessage($this->fullyBookedSeminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedOutUserAndCanceledSeminarReturnsSeminarCancelledMessage(): void
    {
        $this->seminar->setStatus(EventInterface::STATUS_CANCELED);

        self::assertSame(
            $this->translate('message_seminarCancelled'),
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedOutAndWithoutRegistrationReturnsNoRegistrationNeededMessage(
    ): void {
        $this->seminar->setAttendancesMax(0);
        $this->seminar->setNeedsRegistration(false);

        self::assertSame(
            $this->translate('message_noRegistrationNecessary'),
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedOutUserAndSeminarWithUnlimitedVacanciesReturnsEmptyString(
    ): void {
        $this->seminar->setUnlimitedVacancies();

        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedInUserAndSeminarWithUnlimitedVacanciesReturnsEmptyString(
    ): void {
        $this->seminar->setUnlimitedVacancies();
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedOutUserAndFullyBookedSeminarWithQueueReturnsEmptyString(): void
    {
        $this->seminar->setAttendancesMax(5);
        $this->seminar->setNumberOfAttendances(5);
        $this->seminar->setRegistrationQueue(true);

        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    /**
     * @test
     */
    public function canRegisterIfLoggedInMessageForLoggedInUserAndFullyBookedSeminarWithQueueReturnsEmptyString(): void
    {
        $this->seminar->setAttendancesMax(5);
        $this->seminar->setNumberOfAttendances(5);
        $this->seminar->setRegistrationQueue(true);
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertSame(
            '',
            $this->subject->canRegisterIfLoggedInMessage($this->seminar),
        );
    }

    // Tests concerning removeRegistration

    /**
     * @test
     */
    public function removeRegistrationHidesRegistrationOfUser(): void
    {
        $userUid = $this->testingFramework->createAndLoginFrontEndUser();
        $seminarUid = $this->seminarUid;
        $this->createFrontEndPages();

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
                'hidden' => 0,
            ],
        );

        $this->subject->removeRegistration($registrationUid, $this->pi1);
        $query = $this->getConnectionPool()->getQueryBuilderForTable('tx_seminars_attendances');
        $query->getRestrictions()->removeAll();
        $queryResult = $query
            ->count('*')
            ->from('tx_seminars_attendances')
            ->where(
                $query->expr()->eq('user', $query->createNamedParameter($userUid, Connection::PARAM_INT)),
                $query->expr()->eq('seminar', $query->createNamedParameter($seminarUid, Connection::PARAM_INT)),
                $query->expr()->eq('hidden', $query->createNamedParameter(1, Connection::PARAM_INT)),
            )
            ->executeQuery();
        $numberOfRows = $queryResult->fetchOne();

        self::assertSame(1, $numberOfRows);
    }

    /**
     * @test
     */
    public function removeRegistrationWithWaitingListRegistrationMovesItToRegularIfEnabled(): void
    {
        $this->configuration->setAsBoolean('automaticallyFillVacanciesOnUnregistration', true);

        $userUid = $this->testingFramework->createAndLoginFrontEndUser();
        $seminarUid = $this->seminarUid;
        $this->createFrontEndPages();

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
            ],
        );
        $waitingListRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
                'seats' => 1,
                'registration_queue' => Registration::STATUS_WAITING_LIST,
            ],
        );

        $this->subject->removeRegistration($registrationUid, $this->pi1);
        $connection = $this->getConnectionForTable('tx_seminars_attendances');

        self::assertSame(
            1,
            $connection->count(
                '*',
                'tx_seminars_attendances',
                ['registration_queue' => Registration::STATUS_REGULAR, 'uid' => $waitingListRegistrationUid],
            ),
        );
    }

    /**
     * @test
     */
    public function removeRegistrationWithWaitingListRegistrationKeepsItOnWaitingListIfSuccessionIsDisabled(): void
    {
        $this->configuration->setAsBoolean('automaticallyFillVacanciesOnUnregistration', false);

        $userUid = $this->testingFramework->createAndLoginFrontEndUser();
        $seminarUid = $this->seminarUid;
        $this->createFrontEndPages();

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
            ],
        );
        $waitingListRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
                'seats' => 1,
                'registration_queue' => Registration::STATUS_WAITING_LIST,
            ],
        );

        $this->subject->removeRegistration($registrationUid, $this->pi1);
        $connection = $this->getConnectionForTable('tx_seminars_attendances');

        self::assertSame(
            1,
            $connection->count(
                '*',
                'tx_seminars_attendances',
                ['registration_queue' => Registration::STATUS_WAITING_LIST, 'uid' => $waitingListRegistrationUid],
            ),
        );
    }

    /**
     * @test
     */
    public function removeRegistrationDoesNotMoveNonbindingReservationToRegular(): void
    {
        $userUid = $this->testingFramework->createAndLoginFrontEndUser();
        $seminarUid = $this->seminarUid;
        $this->createFrontEndPages();

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
            ],
        );
        $queueRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'user' => $userUid,
                'seminar' => $seminarUid,
                'seats' => 1,
                'registration_queue' => Registration::STATUS_NONBINDING_RESERVATION,
            ],
        );

        $this->subject->removeRegistration($registrationUid, $this->pi1);
        $connection = $this->getConnectionForTable('tx_seminars_attendances');

        self::assertSame(
            0,
            $connection->count(
                '*',
                'tx_seminars_attendances',
                ['registration_queue' => Registration::STATUS_REGULAR, 'uid' => $queueRegistrationUid],
            ),
        );
    }

    // Tests concerning the unregistration notice

    /**
     * @test
     */
    public function notifyAttendeeForUnregistrationMailDoesNotAppendUnregistrationNotice(): void
    {
        $subject = $this
            ->getMockBuilder(RegistrationManager::class)
            ->onlyMethods(['getUnregistrationNotice'])->getMock();
        $subject->expects(self::never())->method('getUnregistrationNotice');

        $this->configuration->setAsBoolean('sendConfirmationOnUnregistration', true);
        $registration = $this->createRegistration();
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'deadline_unregistration' => $this->now + Time::SECONDS_PER_DAY,
            ],
        );
        $pi1 = new DefaultController();
        $pi1->init();

        $subject->notifyAttendee(
            $registration,
            $pi1,
            'confirmationOnUnregistration',
        );
    }

    /**
     * @test
     */
    public function notifyAttendeeForRegistrationMailAndNoUnregistrationPossibleNotAddsUnregistrationNotice(): void
    {
        $subject = $this
            ->getMockBuilder(RegistrationManager::class)
            ->onlyMethods(['getUnregistrationNotice'])->getMock();
        $subject->expects(self::never())->method('getUnregistrationNotice');
        $this->configuration->setAsBoolean('sendConfirmation', true);

        $registration = $this->createRegistration();
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['deadline_unregistration' => $this->now - Time::SECONDS_PER_DAY],
        );

        $pi1 = new DefaultController();
        $pi1->init();

        $subject->notifyAttendee($registration, $pi1);
    }

    /**
     * @test
     */
    public function notifyAttendeeForRegistrationMailAndUnregistrationPossibleAddsUnregistrationNotice(): void
    {
        $subject = $this
            ->getMockBuilder(RegistrationManager::class)
            ->onlyMethods(['getUnregistrationNotice'])->getMock();
        $subject->expects(self::atLeast(1))->method('getUnregistrationNotice');
        $this->configuration->setAsBoolean('sendConfirmation', true);

        $registration = $this->createRegistration();
        $user = $registration->getFrontEndUser();
        self::assertInstanceOf(FrontEndUser::class, $user);
        $userUid = $user->getUid();
        \assert($userUid > 0);
        $this->testingFramework->changeRecord('fe_users', $userUid, ['email' => 'foo@bar.com']);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['deadline_unregistration' => $this->now + Time::SECONDS_PER_DAY],
        );

        $pi1 = new DefaultController();
        $pi1->init();

        $subject->notifyAttendee($registration, $pi1);
    }

    /**
     * @test
     */
    public function notifyAttendeeForRegistrationOnQueuemailAndUnregistrationPossibleAddsUnregistrationNotice(): void
    {
        $this->configuration->setAsBoolean('sendConfirmationOnRegistrationForQueue', true);

        $subject = $this
            ->getMockBuilder(RegistrationManager::class)
            ->onlyMethods(['getUnregistrationNotice'])->getMock();
        $subject->expects(self::atLeast(1))->method('getUnregistrationNotice');

        $registration = $this->createRegistration();
        $registrationUid = $registration->getUid();
        \assert($registrationUid > 0);

        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'deadline_unregistration' => $this->now + Time::SECONDS_PER_DAY,
                'queue_size' => 1,
            ],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_attendances',
            $registrationUid,
            ['registration_queue' => 1],
        );

        $pi1 = new DefaultController();
        $pi1->init();

        $subject->notifyAttendee(
            $registration,
            $pi1,
            'confirmationOnRegistrationForQueue',
        );
    }

    /**
     * @test
     */
    public function notifyAttendeeForQueueUpdatemailAndUnregistrationPossibleAddsUnregistrationNotice(): void
    {
        $this->configuration->setAsBoolean('sendConfirmationOnQueueUpdate', true);

        $subject = $this
            ->getMockBuilder(RegistrationManager::class)
            ->onlyMethods(['getUnregistrationNotice'])->getMock();
        $subject->expects(self::atLeast(1))->method('getUnregistrationNotice');

        $registration = $this->createRegistration();
        $registrationUid = $registration->getUid();
        \assert($registrationUid > 0);

        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'deadline_unregistration' => $this->now + Time::SECONDS_PER_DAY,
                'queue_size' => 1,
            ],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_attendances',
            $registrationUid,
            ['registration_queue' => 1],
        );

        $pi1 = new DefaultController();
        $pi1->init();

        $subject->notifyAttendee(
            $registration,
            $pi1,
            'confirmationOnQueueUpdate',
        );
    }

    /**
     * @test
     */
    public function notifyAttendeeForSendConfirmationFalseNeverCallsRegistrationEmailHookMethods(): void
    {
        $this->configuration->setAsBoolean('sendConfirmation', false);
        $registrationOld = $this->createRegistration();
        $registrationUid = $registrationOld->getUid();
        \assert($registrationUid > 0);
        MapperRegistry::get(RegistrationMapper::class)->find($registrationUid);

        $hook = $this->createMock(RegistrationEmail::class);
        $hook->expects(self::never())->method('modifyAttendeeEmail');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyPlainText');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyHtml');
        $hook->expects(self::never())->method('modifyOrganizerEmail');
        $hook->expects(self::never())->method('modifyAdditionalEmail');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][RegistrationEmail::class][] = $hookClass;
        $this->addMockedInstance($hookClass, $hook);

        $controller = new DefaultController();
        $controller->init();

        $this->subject->notifyAttendee($registrationOld, $controller);
    }

    // Tests regarding the notification of organizers

    /**
     * @test
     */
    public function notifyOrganizersForEventWithEmailsMutedNotSendsEmail(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['mute_notification_emails' => 1],
        );

        $this->email->expects(self::never())->method('send');

        $registration = $this->createRegistration();
        $this->subject->notifyOrganizers($registration);
    }

    /**
     * @test
     */
    public function notifyOrganizersUsesTypo3DefaultFromAddressAsFrom(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);

        $registration = $this->createRegistration();

        $defaultMailFromAddress = 'system-foo@example.com';
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->subject->notifyOrganizers($registration);

        self::assertSame([$defaultMailFromAddress => $defaultMailFromName], $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function notifyOrganizersUsesOrganizerAsReplyTo(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);

        $registration = $this->createRegistration();

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system-foo@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Mr. Default';

        $this->subject->notifyOrganizers($registration);

        self::assertSame(['mail@example.com' => 'test organizer'], $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function notifyOrganizersWithoutTypo3DefaultFromAddressUsesOrganizerAsFrom(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);

        $registration = $this->createRegistration();

        $defaultMailFromAddress = 'system-foo@example.com';
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->subject->notifyOrganizers($registration);

        self::assertSame([$defaultMailFromAddress => $defaultMailFromName], $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function notifyOrganizersUsesOrganizerAsTo(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);

        $registration = $this->createRegistration();
        $this->subject->notifyOrganizers($registration);

        self::assertArrayHasKey('mail@example.com', $this->getToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function notifyOrganizersIncludesHelloIfNotHidden(): void
    {
        $registration = $this->createRegistration();
        $this->configuration->setAsBoolean('sendNotification', true);
        $this->configuration->setAsString('hideFieldsInNotificationMail', '');

        $this->subject->notifyOrganizers($registration);

        self::assertStringContainsString('Hello', $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function notifyOrganizersForEventWithOneVacancyShowsVacanciesLabelWithVacancyNumber(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);
        $this->configuration->setAsString('showSeminarFieldsInNotificationMail', 'vacancies');
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['needs_registration' => 1, 'attendees_max' => 2],
        );

        $registration = $this->createRegistration();
        $this->subject->notifyOrganizers($registration);

        self::assertMatchesRegularExpression(
            '/' . $this->translate('label_vacancies') . ': 1\\n*$/',
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function notifyOrganizersForEventWithUnlimitedVacanciesShowsVacanciesLabelWithUnlimitedLabel(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);
        $this->configuration->setAsString('showSeminarFieldsInNotificationMail', 'vacancies');
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['needs_registration' => 1, 'attendees_max' => 0],
        );

        $registration = $this->createRegistration();
        $this->subject->notifyOrganizers($registration);

        self::assertStringContainsString(
            $this->translate('label_vacancies') . ': ' .
            $this->translate('label_unlimited'),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function notifyOrganizersForRegistrationWithCompanyShowsLabelOfCompany(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);
        $this->configuration->setAsString('showAttendanceFieldsInNotificationMail', 'company');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createFrontEndUser(),
                'company' => 'foo inc.',
            ],
        );

        $registration = new LegacyRegistration($registrationUid);
        $this->subject->notifyOrganizers($registration);

        self::assertStringContainsString(
            $this->translate('label_company'),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function notifyOrganizersForRegistrationWithCompanyShowsCompanyOfRegistration(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);
        $this->configuration->setAsString('showAttendanceFieldsInNotificationMail', 'company');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createFrontEndUser(),
                'company' => 'foo inc.',
            ],
        );

        $registration = new LegacyRegistration($registrationUid);
        $this->subject->notifyOrganizers($registration);

        self::assertStringContainsString('foo inc.', $this->email->getTextBody());
    }

    /**
     * @test
     */
    public function notifyOrganizersForSendNotificationTrueCallsRegistrationEmailHookMethods(): void
    {
        $this->configuration->setAsBoolean('sendNotification', true);

        $registrationOld = $this->createRegistration();
        $registrationUid = $registrationOld->getUid();
        \assert($registrationUid > 0);
        $registration = MapperRegistry::get(RegistrationMapper::class)->find($registrationUid);

        $hook = $this->createMock(RegistrationEmail::class);
        $hook->expects(self::never())->method('modifyAttendeeEmail');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyPlainText');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyHtml');
        $hook->expects(self::once())->method('modifyOrganizerEmail')->with(
            self::isInstanceOf(MailMessage::class),
            $registration,
            'notification',
        );
        $hook->expects(self::never())->method('modifyAdditionalEmail');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][RegistrationEmail::class][] = $hookClass;
        $this->addMockedInstance($hookClass, $hook);

        $this->subject->notifyOrganizers($registrationOld);
    }

    /**
     * @test
     */
    public function notifyOrganizersForSendNotificationFalseNeverCallsRegistrationEmailHookMethods(): void
    {
        $this->configuration->setAsBoolean('sendNotification', false);

        $registrationOld = $this->createRegistration();
        $registrationUid = $registrationOld->getUid();
        \assert($registrationUid > 0);
        MapperRegistry::get(RegistrationMapper::class)->find($registrationUid);

        $hook = $this->createMock(RegistrationEmail::class);
        $hook->expects(self::never())->method('modifyAttendeeEmail');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyPlainText');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyHtml');
        $hook->expects(self::never())->method('modifyOrganizerEmail');
        $hook->expects(self::never())->method('modifyAdditionalEmail');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][RegistrationEmail::class][] = $hookClass;
        $this->addMockedInstance($hookClass, $hook);

        $this->subject->notifyOrganizers($registrationOld);
    }

    // Tests concerning sendAdditionalNotification

    /**
     * @test
     */
    public function sendAdditionalNotificationCanSendEmailToOneOrganizer(): void
    {
        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertArrayHasKey('mail@example.com', $this->getToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEmailMutedNotSendsEmail(): void
    {
        $registration = $this->createRegistration();
        $event = $registration->getSeminarObject();
        $event->muteNotificationEmails();

        $this->email->expects(self::never())->method('send');

        $this->subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationCanSendEmailsToTwoOrganizers(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'test organizer 2',
                'email' => 'mail2@example.com',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $this->seminarUid,
            $organizerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['organizers' => 2],
        );

        $this->email->expects(self::once())->method('send');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertArrayHasKey('mail@example.com', $this->getToOfEmail($this->email));
        self::assertArrayHasKey('mail2@example.com', $this->getToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationUsesTypo3DefaultFromAddressAsSenderIfEmailIsSentToTwoOrganizers(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'test organizer 2',
                'email' => 'mail2@example.com',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $this->seminarUid,
            $organizerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['organizers' => 2],
        );

        $registration = $this->createRegistration();

        $defaultMailFromAddress = 'system-foo@example.com';
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->subject->sendAdditionalNotification($registration);

        self::assertArrayHasKey($defaultMailFromAddress, $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationUsesTheFirstOrganizerAsReplyToIfEmailIsSentToTwoOrganizers(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'test organizer 2',
                'email' => 'mail2@example.com',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $this->seminarUid,
            $organizerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['organizers' => 2],
        );

        $registration = $this->createRegistration();

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system-foo@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Mr. Default';

        $this->subject->sendAdditionalNotification($registration);

        self::assertArrayHasKey('mail@example.com', $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationUsesTheFirstOrganizerAsSenderIfEmailIsSentToTwoOrganizers(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'test organizer 2',
                'email' => 'mail2@example.com',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $this->seminarUid,
            $organizerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['organizers' => 2],
        );

        $registration = $this->createRegistration();

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';

        $this->subject->sendAdditionalNotification($registration);

        self::assertArrayHasKey('mail@example.com', $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithEnoughAttendancesSendsEnoughAttendancesMail(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 1, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertStringContainsString(
            sprintf(
                $this->translate('email_additionalNotificationEnoughRegistrationsSubject'),
                $this->seminarUid,
                '',
            ),
            $this->email->getSubject(),
        );
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithNotEnoughAttendancesNotSendsEmail(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 2, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->email->expects(self::never())->method('send');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithNotEnoughAttendancesNotSetsNotificationFlag(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 2, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        // This makes sure the event is loaded from DB again.
        $event = new TestingLegacyEvent($this->seminarUid);

        self::assertFalse($event->haveOrganizersBeenNotifiedAboutEnoughAttendees());
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithMoreThanEnoughAttendancesSendsEnoughAttendancesMail(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 1, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->createRegistration();
        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        $firstEmail = $this->email;
        self::assertNotNull($firstEmail);
        self::assertStringContainsString(
            sprintf(
                $this->translate('email_additionalNotificationEnoughRegistrationsSubject'),
                $this->seminarUid,
                '',
            ),
            $firstEmail->getSubject(),
        );
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithEnoughAttendancesSetsNotifiedFlag(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 1, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        // This makes sure the event is loaded from DB again.
        $event = new TestingLegacyEvent($this->seminarUid);

        self::assertTrue($event->haveOrganizersBeenNotifiedAboutEnoughAttendees());
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithMoreThanEnoughAttendancesSetsNotifiedFlag(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 1, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->createRegistration();
        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        // This makes sure the event is loaded from DB again.
        $event = new TestingLegacyEvent($this->seminarUid);

        self::assertTrue($event->haveOrganizersBeenNotifiedAboutEnoughAttendees());
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithEnoughAttendancesAndOrganizersAlreadyNotifiedNotSendsEmail(
    ): void {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 1, 'attendees_max' => 42, 'organizers_notified_about_minimum_reached' => 1],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->email->expects(self::never())->method('send');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForMoreThanEnoughAttendancesAndOrganizersAlreadyNotifiedNotSends(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 1, 'attendees_max' => 42, 'organizers_notified_about_minimum_reached' => 1],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->email->expects(self::never())->method('send');

        $this->createRegistration();
        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithZeroAttendeesMinDoesNotSendAnyMail(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 0, 'attendees_max' => 42],
        );

        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->email->expects(self::never())->method('send');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForBookedOutEventSendsEmailWithBookedOutSubject(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_max' => 1],
        );

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertStringContainsString(
            sprintf(
                $this->translate('email_additionalNotificationIsFullSubject'),
                $this->seminarUid,
                '',
            ),
            $this->email->getSubject(),
        );
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForBookedOutEventSendsEmailWithBookedOutMessage(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_max' => 1],
        );
        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertStringContainsString(
            $this->translate('email_additionalNotificationIsFull'),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithNotEnoughAttendancesAndNotBookedOutSendsNoEmail(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_min' => 5, 'attendees_max' => 5],
        );

        $subject = new RegistrationManager();
        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');

        $this->email->expects(self::never())->method('send');

        $registration = $this->createRegistration();
        $subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithEnoughAttendancesAndUnlimitedVacanciesSendsEmail(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'attendees_min' => 1,
                'attendees_max' => 0,
                'needs_registration' => 1,
            ],
        );

        $this->email->expects(self::once())->method('send');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEventWithEnoughAttendancesAndOneVacancyHasVacancyNumber(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'attendees_min' => 1,
                'attendees_max' => 2,
                'needs_registration' => 1,
            ],
        );
        $this->configuration->setAsString('showSeminarFieldsInNotificationMail', 'vacancies');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertMatchesRegularExpression(
            '/' . $this->translate('label_vacancies') . ': 1\\n*$/',
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationForEnoughAttendancesAndUnlimitedVacanciesHasUnlimitedLabel(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'attendees_min' => 1,
                'attendees_max' => 0,
                'needs_registration' => 1,
            ],
        );
        $this->configuration->setAsString('showSeminarFieldsInNotificationMail', 'vacancies');

        $registration = $this->createRegistration();
        $this->subject->sendAdditionalNotification($registration);

        self::assertStringContainsString(
            $this->translate('label_vacancies') . ': '
            . $this->translate('label_unlimited'),
            $this->email->getTextBody(),
        );
    }

    /**
     * @test
     */
    public function sendAdditionalNotificationCallsRegistrationEmailHookMethods(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['attendees_max' => 1],
        );

        $registrationOld = $this->createRegistration();
        $registrationUid = $registrationOld->getUid();
        \assert($registrationUid > 0);
        $registration = MapperRegistry::get(RegistrationMapper::class)->find($registrationUid);

        $hook = $this->createMock(RegistrationEmail::class);
        $hook->expects(self::never())->method('modifyAttendeeEmail');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyPlainText');
        $hook->expects(self::never())->method('modifyAttendeeEmailBodyHtml');
        $hook->expects(self::never())->method('modifyOrganizerEmail');
        $hook->expects(self::once())->method('modifyAdditionalEmail')->with(
            self::isInstanceOf(MailMessage::class),
            $registration,
            'IsFull',
        );

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][RegistrationEmail::class][] = $hookClass;
        $this->addMockedInstance($hookClass, $hook);

        $this->subject->sendAdditionalNotification($registrationOld);
    }

    // Tests concerning allowsRegistrationByDate

    /**
     * @test
     */
    public function allowsRegistrationByDateForEventWithoutDateWithRegistrationRequiredReturnsTrue(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate(0);

        self::assertTrue($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForEventWithoutDateWithoutRegistrationRequiredReturnsFalse(): void
    {
        $this->seminar->setNeedsRegistration(false);
        $this->seminar->setBeginDate(0);

        self::assertFalse($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForBeginDateAndRegistrationDeadlineOverReturnsFalse(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate($this->now + 42);
        $this->seminar->setRegistrationDeadline($this->now - 42);

        self::assertFalse($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForBeginDateAndRegistrationDeadlineInFutureReturnsTrue(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate($this->now + 42);
        $this->seminar->setRegistrationDeadline($this->now + 42);

        self::assertTrue($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForRegistrationDeadlineInFutureAndNoRegistrationNeededReturnsFalse(): void
    {
        $this->seminar->setNeedsRegistration(false);
        $this->seminar->setBeginDate($this->now + 42);
        $this->seminar->setRegistrationDeadline($this->now + 42);

        self::assertFalse($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForRegistrationBeginInFutureReturnsFalse(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate($this->now + 42);
        $this->seminar->setRegistrationBeginDate($this->now + 10);

        self::assertFalse($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForRegistrationBeginInPastReturnsTrue(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate($this->now + 42);
        $this->seminar->setRegistrationBeginDate($this->now - 42);

        self::assertTrue($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForNoRegistrationBeginReturnsTrue(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate($this->now + 42);
        $this->seminar->setRegistrationBeginDate(0);

        self::assertTrue($this->subject->allowsRegistrationByDate($this->seminar));
    }

    /**
     * @test
     */
    public function allowsRegistrationByDateForBeginDateInPastAndRegistrationBeginInPastReturnsFalse(): void
    {
        $this->seminar->setNeedsRegistration(true);
        $this->seminar->setBeginDate($this->now - 42);
        $this->seminar->setRegistrationBeginDate($this->now - 50);

        self::assertFalse($this->subject->allowsRegistrationByDate($this->seminar));
    }

    // Tests concerning allowsRegistrationBySeats

    /**
     * @test
     */
    public function allowsRegistrationBySeatsForEventWithNoVacanciesAndNoQueueReturnsFalse(): void
    {
        $this->seminar->setNumberOfAttendances(1);
        $this->seminar->setAttendancesMax(1);
        $this->seminar->setRegistrationQueue(false);

        self::assertFalse(
            $this->subject->allowsRegistrationBySeats($this->seminar),
        );
    }

    /**
     * @test
     */
    public function allowsRegistrationBySeatsForEventWithUnlimitedVacanciesReturnsTrue(): void
    {
        $this->seminar->setUnlimitedVacancies();

        self::assertTrue(
            $this->subject->allowsRegistrationBySeats($this->seminar),
        );
    }

    /**
     * @test
     */
    public function allowsRegistrationBySeatsForEventWithRegistrationQueueReturnsTrue(): void
    {
        $this->seminar->setNumberOfAttendances(1);
        $this->seminar->setAttendancesMax(1);
        $this->seminar->setRegistrationQueue(true);

        self::assertTrue(
            $this->subject->allowsRegistrationBySeats($this->seminar),
        );
    }

    /**
     * @test
     */
    public function allowsRegistrationBySeatsForEventWithVacanciesReturnsTrue(): void
    {
        $this->seminar->setNumberOfAttendances(0);
        $this->seminar->setAttendancesMax(1);
        $this->seminar->setRegistrationQueue(false);

        self::assertTrue(
            $this->subject->allowsRegistrationBySeats($this->seminar),
        );
    }

    // Tests concerning registrationHasStarted

    /**
     * @test
     */
    public function registrationHasStartedForEventWithoutRegistrationBeginReturnsTrue(): void
    {
        $this->seminar->setRegistrationBeginDate(0);

        self::assertTrue(
            $this->subject->registrationHasStarted($this->seminar),
        );
    }

    /**
     * @test
     */
    public function registrationHasStartedForEventWithRegistrationBeginInPastReturnsTrue(): void
    {
        $this->seminar->setRegistrationBeginDate(
            $this->now - 42,
        );

        self::assertTrue(
            $this->subject->registrationHasStarted($this->seminar),
        );
    }

    /**
     * @test
     */
    public function registrationHasStartedForEventWithRegistrationBeginInFutureReturnsFalse(): void
    {
        $this->seminar->setRegistrationBeginDate(
            $this->now + 42,
        );

        self::assertFalse(
            $this->subject->registrationHasStarted($this->seminar),
        );
    }

    private function getConnectionForTable(string $table): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable($table);
    }
}
