<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\FrontEnd;

use OliverKlee\Oelib\Configuration\ConfigurationProxy;
use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Hooks\Interfaces\SeminarListView;
use OliverKlee\Seminars\Hooks\Interfaces\SeminarSingleView;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\Service\RegistrationManager;
use OliverKlee\Seminars\Tests\Functional\FrontEnd\Fixtures\TestingDefaultController;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyEvent;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\DefaultController
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class DefaultControllerTest extends FunctionalTestCase
{
    use LanguageHelper;

    private const CONFIGURATION = [
        'currency' => 'EUR',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected array $coreExtensionsToLoad = ['typo3/cms-extensionmanager'];

    private TestingDefaultController $subject;

    private TestingFramework $testingFramework;

    /**
     * @var positive-int the UID of a seminar to which the fixture relates
     */
    private int $seminarUid;

    /**
     * @var positive-int PID of a dummy system folder
     */
    private int $systemFolderPid;

    /**
     * @var int<0, max> the number of target groups for the current event record
     */
    private int $numberOfTargetGroups = 0;

    /**
     * @var int<0, max> the number of categories for the current event record
     */
    private int $numberOfCategories = 0;

    /**
     * @var int<0, max> the number of organizers for the current event record
     */
    private int $numberOfOrganizers = 0;

    /**
     * backed-up extension configuration of the TYPO3 configuration variables
     *
     * @var array<string, mixed>
     */
    private array $extConfBackup = [];

    private ConnectionPool $connectionPool;

    private DummyConfiguration $sharedConfiguration;

    private DummyConfiguration $extensionConfiguration;

    private DummyConfiguration $pluginConfiguration;

    /**
     * @var positive-int
     */
    private int $rootPageUid;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->extConfBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'] = [];

        $this->testingFramework = new TestingFramework('tx_seminars');
        $this->rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $this->rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($this->rootPageUid);

        $this->getLanguageService();

        $this->extensionConfiguration = new DummyConfiguration();
        $this->extensionConfiguration->setAsBoolean('enableConfigCheck', false);
        ConfigurationProxy::setInstance('seminars', $this->extensionConfiguration);
        $this->sharedConfiguration = new DummyConfiguration(self::CONFIGURATION);
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $this->sharedConfiguration);
        $this->pluginConfiguration = new DummyConfiguration(self::CONFIGURATION);
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars_pi1', $this->pluginConfiguration);

        $this->systemFolderPid = $this->testingFramework->createSystemFolder();
        $this->seminarUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Test & event',
                'subtitle' => 'Something for you & me',
                'accreditation_number' => '1 & 1',
                'room' => 'Rooms 2 & 3',
                'expiry' => mktime(0, 0, 0, 1, 1, 2008),
            ],
        );

        $this->subject = new TestingDefaultController();
        $this->subject->init(
            [
                'isStaticTemplateLoaded' => 1,
                'enableRegistration' => 1,
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
                'what_to_display' => 'seminar_list',
                'pidList' => $this->systemFolderPid,
                'pages' => $this->systemFolderPid,
                'recursive' => 1,
                'listView.' => [
                    'orderBy' => 'data',
                    'descFlag' => 0,
                    'results_at_a_time' => 999,
                    'maxPages' => 5,
                ],
                'linkToSingleView' => 'always',
            ],
        );
        $this->subject->getTemplateCode();
        $this->subject->setLabels();
        $this->subject->createHelperObjects();

        $contentObject = $this->createPartialMock(ContentObjectRenderer::class, ['cObjGetSingle']);
        $contentObject->setLogger(new NullLogger());
        $contentObject->method('cObjGetSingle')->willReturn('<img src="foo.jpg" alt="bar"/>');
        $this->subject->setContentObjectRenderer($contentObject);

        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        ConfigurationRegistry::purgeInstance();
        ConfigurationProxy::purgeInstances();

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] = $this->extConfBackup;

        parent::tearDown();
    }

    ///////////////////////
    // Utility functions.
    ///////////////////////

    /**
     * Inserts a target group record into the database and creates a relation to
     * it from the event with the UID store in $this->seminarUid.
     *
     * @param array $targetGroupData data of the target group to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addTargetGroupRelation(array $targetGroupData = []): int
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            $targetGroupData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_target_groups_mm',
            $this->seminarUid,
            $uid,
        );

        $this->numberOfTargetGroups++;
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['target_groups' => $this->numberOfTargetGroups],
        );

        return $uid;
    }

    /**
     * Creates a FE user, registers him/her to the seminar with the UID in
     * $this->seminarUid and logs him/her in.
     *
     * @return int<1, max> the UID of the created registration record
     */
    private function createLogInAndRegisterFeUser(): int
    {
        $feUserUid = $this->testingFramework->createAndLoginFrontEndUser();
        return $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $feUserUid,
                'registration_queue' => Registration::STATUS_REGULAR,
            ],
        );
    }

    /**
     * Creates a FE user, adds him/her as a VIP to the seminar with the UID in
     * $this->seminarUid and logs him/her in.
     */
    private function createLogInAndAddFeUserAsVip(): void
    {
        $feUserUid = $this->testingFramework->createAndLoginFrontEndUser();
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_feusers_mm',
            $this->seminarUid,
            $feUserUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['vips' => 1],
        );
    }

    /**
     * Inserts a category record into the database and creates a relation to
     * it from the event with the UID stored in $this->seminarUid.
     *
     * @param array $categoryData data of the category to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addCategoryRelation(array $categoryData = []): int
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            $categoryData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $this->seminarUid,
            $uid,
        );

        $this->numberOfCategories++;
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['categories' => $this->numberOfCategories],
        );

        return $uid;
    }

    /**
     * Inserts an organizer record into the database and creates a relation to
     * to the seminar with the UID stored in $this->seminarUid.
     *
     * @param array $organizerData data of the organizer to add, may be empty
     */
    private function addOrganizerRelation(array $organizerData = []): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            $organizerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $this->seminarUid,
            $organizerUid,
        );

        $this->numberOfOrganizers++;
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['organizers' => $this->numberOfOrganizers],
        );
    }

    /////////////////////////////////////
    // Tests for the utility functions.
    /////////////////////////////////////

    /**
     * @test
     */
    public function addTargetGroupRelationReturnsUid(): void
    {
        self::assertTrue(
            $this->addTargetGroupRelation() > 0,
        );
    }

    /**
     * @test
     */
    public function addTargetGroupRelationCreatesNewUids(): void
    {
        $this->addTargetGroupRelation();
        self::assertNotEquals(
            $this->addTargetGroupRelation(),
            $this->addTargetGroupRelation(),
        );
    }

    /**
     * @test
     */
    public function addTargetGroupRelationIncreasesTheNumberOfTargetGroups(): void
    {
        self::assertEquals(
            0,
            $this->numberOfTargetGroups,
        );

        $this->addTargetGroupRelation();
        self::assertEquals(
            1,
            $this->numberOfTargetGroups,
        );

        $this->addTargetGroupRelation();
        self::assertEquals(
            2,
            $this->numberOfTargetGroups,
        );
    }

    /**
     * @test
     */
    public function addTargetGroupRelationCreatesRelations(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_target_groups_mm');

        self::assertEquals(
            0,
            $connection->count('*', 'tx_seminars_seminars_target_groups_mm', ['uid_local' => $this->seminarUid]),
        );

        $this->addTargetGroupRelation();
        self::assertEquals(
            1,
            $connection->count('*', 'tx_seminars_seminars_target_groups_mm', ['uid_local' => $this->seminarUid]),
        );

        $this->addTargetGroupRelation();
        self::assertEquals(
            2,
            $connection->count('*', 'tx_seminars_seminars_target_groups_mm', ['uid_local' => $this->seminarUid]),
        );
    }

    /**
     * @test
     */
    public function createLogInAndAddFeUserAsVipCreatesFeUser(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('fe_users');

        $this->createLogInAndAddFeUserAsVip();

        self::assertEquals(
            1,
            $connection->count('*', 'fe_users', []),
        );
    }

    /**
     * @test
     */
    public function createLogInAndAddFeUserAsVipLogsInFeUser(): void
    {
        $this->createLogInAndAddFeUserAsVip();

        self::assertTrue(GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->isLoggedIn());
    }

    /**
     * @test
     */
    public function createLogInAndAddFeUserAsVipAddsUserAsVip(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars');

        $this->createLogInAndAddFeUserAsVip();

        self::assertEquals(
            1,
            $connection->count('*', 'tx_seminars_seminars', ['uid' => $this->seminarUid, 'vips' => 1]),
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationReturnsPositiveUid(): void
    {
        self::assertTrue(
            $this->addCategoryRelation() > 0,
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationCreatesNewUids(): void
    {
        self::assertNotEquals(
            $this->addCategoryRelation(),
            $this->addCategoryRelation(),
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationIncreasesTheNumberOfCategories(): void
    {
        self::assertEquals(
            0,
            $this->numberOfCategories,
        );

        $this->addCategoryRelation();
        self::assertEquals(
            1,
            $this->numberOfCategories,
        );

        $this->addCategoryRelation();
        self::assertEquals(
            2,
            $this->numberOfCategories,
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationCreatesRelations(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_categories_mm');

        self::assertEquals(
            0,
            $connection->count('*', 'tx_seminars_seminars_categories_mm', ['uid_local' => $this->seminarUid]),
        );

        $this->addCategoryRelation();
        self::assertEquals(
            1,
            $connection->count('*', 'tx_seminars_seminars_categories_mm', ['uid_local' => $this->seminarUid]),
        );

        $this->addCategoryRelation();
        self::assertEquals(
            2,
            $connection->count('*', 'tx_seminars_seminars_categories_mm', ['uid_local' => $this->seminarUid]),
        );
    }

    ////////////////////////////////////////////
    // Test concerning the base functionality.
    ////////////////////////////////////////////

    /**
     * @test
     */
    public function pi1MustBeInitialized(): void
    {
        self::assertNotNull(
            $this->subject,
        );
        self::assertTrue(
            $this->subject->isInitialized(),
        );
    }

    /**
     * @test
     */
    public function getSeminarReturnsSeminarIfSet(): void
    {
        $this->subject->createSeminar($this->seminarUid);

        self::assertInstanceOf(
            LegacyEvent::class,
            $this->subject->getSeminar(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationManagerReturnsRegistrationManager(): void
    {
        self::assertInstanceOf(
            RegistrationManager::class,
            $this->subject->getRegistrationManager(),
        );
    }

    // Tests concerning the single view

    /**
     * @test
     */
    public function otherDatesListInSingleViewContainsOtherDateWithDateLinkedToSingleView(): void
    {
        self::markTestIncomplete('Fix this test to work without a mocked SingleView.');

        // @phpstan-ignore-next-line Yes, this code is unreachable, and we know it.
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'Test topic',
            ],
        );
        $dateUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + Time::SECONDS_PER_DAY,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date 2',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 2 * Time::SECONDS_PER_DAY,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 3 * Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $dateUid1;

        self::assertStringContainsString(
            'tx_seminars_pi1%5BshowUid%5D=1337',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function otherDatesListInSingleViewDoesNotContainSingleEventRecordWithTopicSet(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->subject->setConfigurationValue(
            'hideFields',
            'eventsnextday',
        );
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'Test topic',
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + Time::SECONDS_PER_DAY,
            ],
        );
        $singleEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'topic' => $topicUid,
                'title' => 'Test single 2',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 2 * Time::SECONDS_PER_DAY,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 3 * Time::SECONDS_PER_DAY,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $dateUid;

        $result = $this->subject->main('', []);

        self::assertStringNotContainsString(
            'tx_seminars_pi1%5BshowUid%5D=' . $singleEventUid,
            $result,
        );
    }

    /**
     * @test
     */
    public function otherDatesListInSingleViewByDefaultShowsBookedOutEvents(): void
    {
        self::markTestIncomplete('Fix this test to work without a mocked SingleView.');

        // @phpstan-ignore-next-line Yes, this code is unreachable, and we know it.
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'Test topic',
            ],
        );
        $dateUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + Time::SECONDS_PER_DAY,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date 2',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 2 * Time::SECONDS_PER_DAY,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 3 * Time::SECONDS_PER_DAY,
                'needs_registration' => 1,
                'attendees_max' => 5,
                'offline_attendees' => 5,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $dateUid1;

        self::assertStringContainsString(
            'tx_seminars_pi1%5BshowUid%5D=1337',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function otherDatesListInSingleViewForShowOnlyEventsWithVacanciesSetHidesBookedOutEvents(): void
    {
        $this->subject->setConfigurationValue(
            'showOnlyEventsWithVacancies',
            true,
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'Test topic',
            ],
        );
        $dateUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + Time::SECONDS_PER_DAY,
            ],
        );
        $dateUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
                'title' => 'Test date 2',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 2 * Time::SECONDS_PER_DAY,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + Time::SECONDS_PER_WEEK + 3 * Time::SECONDS_PER_DAY,
                'needs_registration' => 1,
                'attendees_max' => 5,
                'offline_attendees' => 5,
            ],
        );

        $this->subject->piVars['showUid'] = $dateUid1;

        self::assertStringNotContainsString(
            'tx_seminars_pi1%5BshowUid%5D=' . $dateUid2,
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForSpeakerWithoutHomepageContainsHtmlspecialcharedSpeakerName(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->subject->setConfigurationValue('showSpeakerDetails', true);
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'title' => 'foo & bar',
                'organization' => 'baz',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $this->seminarUid,
            $speakerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['speakers' => '1'],
        );

        self::assertStringContainsString(
            'foo &amp; bar',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForContainsHtmlspecialcharedSpeakerOrganization(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->subject->setConfigurationValue('showSpeakerDetails', true);
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'title' => 'John Doe',
                'organization' => 'foo & bar',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $this->seminarUid,
            $speakerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['speakers' => '1'],
        );

        self::assertStringContainsString(
            'foo &amp; bar',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewWithSpeakerDetailsLinksHtmlspecialcharedSpeakersName(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->subject->setConfigurationValue('showSpeakerDetails', true);
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'title' => 'foo & bar',
                'organization' => 'baz',
                'homepage' => 'www.foo.com',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $this->seminarUid,
            $speakerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['speakers' => '1'],
        );

        self::assertMatchesRegularExpression(
            '#<a href="[a-z]+://www.foo.com".*>foo &amp; bar</a>#',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewWithoutSpeakerDetailsLinksHtmlspecialcharedSpeakersName(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->subject->setConfigurationValue('showSpeakerDetails', false);
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $speakerUid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'title' => 'foo & bar',
                'organization' => 'baz',
                'homepage' => 'www.foo.com',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $this->seminarUid,
            $speakerUid,
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['speakers' => '1'],
        );

        self::assertMatchesRegularExpression(
            '#<a href="[a-z]+://www.foo.com".*>foo &amp; bar</a>#',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithoutImageNotDisplaysImage(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->setConfigurationValue('seminarImageSingleViewWidth', 260);
        $this->subject->setConfigurationValue('seminarImageSingleViewHeight', 160);

        $this->subject->piVars['showUid'] = (string)$this->seminarUid;
        $result = $this->subject->main('', []);

        self::assertStringNotContainsString('<p class="tx-seminars-pi1-image">', $result);
        self::assertStringNotContainsString('<img', $result);
    }

    /**
     * @test
     */
    public function singleViewCallsHookSeminarSingleViewModifySingleView(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');

        $hookedInObject = $this->createMock(SeminarSingleView::class);
        $hookedInObject->expects(self::once())->method('modifySingleView')->with($this->subject);

        $hookedInClass = \get_class($hookedInObject);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarSingleView::class][] = $hookedInClass;
        GeneralUtility::addInstance($hookedInClass, $hookedInObject);

        $this->subject->piVars['showUid'] = (string)$this->seminarUid;
        $this->subject->main('', []);
    }

    /**
     * @test
     */
    public function attachedFilesSubpartIsHiddenInSingleViewWithoutAttachedFilesAndWithLoggedInAndRegisteredFeUser(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->setConfigurationValue('limitFileDownloadToAttendees', 1);
        $this->createLogInAndRegisterFeUser();

        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_ATTACHED_FILES'),
        );
    }

    /**
     * @test
     */
    public function attachedFilesSubpartIsHiddenInSingleViewWithoutAttachedFilesAndWithDisabledLimitFileDownloadToAttendees(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->setConfigurationValue('limitFileDownloadToAttendees', 0);

        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_ATTACHED_FILES'),
        );
    }

    ///////////////////////////////////////////////
    // Tests concerning places in the single view
    ///////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForNoSiteDetailsContainsHtmlSpecialcharedTitleOfEventPlace(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->setConfigurationValue('showSiteDetails', false);

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'a & place'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->piVars['showUid'] = $eventUid;

        self::assertStringContainsString(
            'a &amp; place',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForSiteDetailsContainsHtmlSpecialcharedTitleOfEventPlace(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->setConfigurationValue('showSiteDetails', true);

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'a & place'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->piVars['showUid'] = $eventUid;

        self::assertStringContainsString(
            'a &amp; place',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForSiteDetailsContainsHtmlSpecialcharedAddressOfEventPlace(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->setConfigurationValue('showSiteDetails', true);

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['place' => 1],
        );
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['address' => 'over & the rainbow'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid,
            $placeUid,
        );
        $this->subject->piVars['showUid'] = $eventUid;

        self::assertStringContainsString(
            'over &amp; the rainbow',
            $this->subject->main('', []),
        );
    }

    ////////////////////////////////////////////////////
    // Tests concerning time slots in the single view.
    ////////////////////////////////////////////////////

    /**
     * @test
     */
    public function timeSlotsSubpartIsHiddenInSingleViewWithoutTimeSlots(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_TIMESLOTS'),
        );
    }

    /**
     * @test
     */
    public function timeSlotsSubpartIsVisibleInSingleViewWithOneTimeSlot(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            ['seminar' => $this->seminarUid],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['timeslots' => 1],
        );

        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertTrue(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_TIMESLOTS'),
        );
    }

    /**
     * @test
     */
    public function singleViewDisplaysTimeSlotTimesWithDash(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $this->seminarUid,
                'begin_date' => mktime(9, 45, 0, 4, 2, 2020),
                'end_date' => mktime(18, 30, 0, 4, 2, 2020),
            ],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['timeslots' => 1],
        );

        $this->subject->piVars['showUid'] = $this->seminarUid;
        self::assertStringContainsString(
            '9:45&#8211;18:30',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewCanContainOneHtmlspecialcharedTimeSlotRoom(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $this->seminarUid,
                'room' => 'room & 1',
            ],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['timeslots' => 1],
        );

        $this->subject->piVars['showUid'] = $this->seminarUid;
        self::assertStringContainsString(
            'room &amp; 1',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function timeSlotsSubpartIsVisibleInSingleViewWithTwoTimeSlots(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            ['seminar' => $this->seminarUid],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            ['seminar' => $this->seminarUid],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['timeslots' => 2],
        );

        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertTrue(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_TIMESLOTS'),
        );
    }

    /**
     * @test
     */
    public function singleViewCanContainTwoTimeSlotRooms(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $this->seminarUid,
                'room' => 'room 1',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $this->seminarUid,
                'room' => 'room 2',
            ],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['timeslots' => 2],
        );

        $this->subject->piVars['showUid'] = $this->seminarUid;
        $result = $this->subject->main('', []);
        self::assertStringContainsString(
            'room 1',
            $result,
        );
        self::assertStringContainsString(
            'room 2',
            $result,
        );
    }

    // Tests concerning target groups in the single view.

    /**
     * @test
     */
    public function targetGroupsSubpartIsHiddenInSingleViewWithoutTargetGroups(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_TARGET_GROUPS'),
        );
    }

    /**
     * @test
     */
    public function targetGroupsSubpartIsVisibleInSingleViewWithOneTargetGroup(): void
    {
        $this->addTargetGroupRelation();

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertTrue(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_TARGET_GROUPS'),
        );
    }

    /**
     * @test
     */
    public function singleViewCanContainOneHtmlSpecialcharedTargetGroupTitle(): void
    {
        $this->addTargetGroupRelation(
            ['title' => 'group 1 & 2'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            'group 1 &amp; 2',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function targetGroupsSubpartIsVisibleInSingleViewWithTwoTargetGroups(): void
    {
        $this->addTargetGroupRelation(
            ['title' => 'group 1'],
        );
        $this->addTargetGroupRelation(
            ['title' => 'group 2'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertTrue(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_TARGET_GROUPS'),
        );
    }

    /**
     * @test
     */
    public function singleViewCanContainTwoTargetGroupTitles(): void
    {
        $this->addTargetGroupRelation(
            ['title' => 'group 1'],
        );
        $this->addTargetGroupRelation(
            ['title' => 'group 2'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $result = $this->subject->main('', []);

        self::assertStringContainsString(
            'group 1',
            $result,
        );
        self::assertStringContainsString(
            'group 2',
            $result,
        );
    }

    ///////////////////////////////////////////////////////
    // Tests concerning requirements in the single view.
    ///////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForSeminarWithoutRequirementsHidesRequirementsSubpart(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_REQUIREMENTS'),
        );
    }

    /**
     * @test
     */
    public function singleViewForSeminarWithOneRequirementDisplaysRequirementsSubpart(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredEvent = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $requiredEvent,
            'requirements',
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);

        self::assertTrue(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_REQUIREMENTS'),
        );
    }

    /**
     * @test
     */
    public function singleViewForSeminarWithOneRequirementLinksRequirementToItsSingleView(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredEvent = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'required_foo',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $requiredEvent,
            'requirements',
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertMatchesRegularExpression(
            '/<a href=.*' . $requiredEvent . '.*>required_foo<\\/a>/',
            $this->subject->main('', []),
        );
    }

    ///////////////////////////////////////////////////////
    // Tests concerning dependencies in the single view.
    ///////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForSeminarWithoutDependenciesHidesDependenciesSubpart(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_DEPENDENCIES'),
        );
    }

    /**
     * @test
     */
    public function singleViewForSeminarWithOneDependencyDisplaysDependenciesSubpart(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependingEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid,
            $this->seminarUid,
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $this->subject->main('', []);

        self::assertTrue(
            $this->subject->isSubpartVisible('FIELD_WRAPPER_DEPENDENCIES'),
        );
    }

    /**
     * @test
     */
    public function singleViewForSeminarWithOneDependenciesShowsTitleOfDependency(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependingEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'depending_foo',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid,
            $this->seminarUid,
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            'depending_foo',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForSeminarWithOneDependencyContainsLinkToDependency(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependingEventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'depending_foo',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid,
            $this->seminarUid,
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            '>depending_foo</a>',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForSeminarWithTwoDependenciesShowsTitleOfBothDependencies(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 2,
            ],
        );
        $dependingEventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'depending_foo',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid1,
            $this->seminarUid,
        );
        $dependingEventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'depending_bar',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependingEventUid2,
            $this->seminarUid,
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $renderedOutput = $this->subject->main('', []);
        self::assertStringContainsString(
            'depending_bar',
            $renderedOutput,
        );
        self::assertStringContainsString(
            'depending_foo',
            $renderedOutput,
        );
    }

    //////////////////////////////////////////////////////
    // Test concerning the event type in the single view
    //////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewContainsHtmlspecialcharedEventTypeTitleAndColonIfEventHasEventType(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'event_type' => $this->testingFramework->createRecord(
                    'tx_seminars_event_types',
                    ['title' => 'foo & type'],
                ),
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            'foo &amp; type:',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewNotContainsColonBeforeEventTitleIfEventHasNoEventType(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertDoesNotMatchRegularExpression(
            '/: *Test &amp; event/',
            $this->subject->main('', []),
        );
    }

    //////////////////////////////////////////////////////
    // Test concerning the categories in the single view
    //////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewCanContainOneHtmlSpecialcharedCategoryTitle(): void
    {
        $this->addCategoryRelation(
            ['title' => 'category & 1'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        self::assertStringContainsString(
            'category &amp; 1',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewCanContainTwoCategories(): void
    {
        $this->addCategoryRelation(
            ['title' => 'category 1'],
        );
        $this->addCategoryRelation(
            ['title' => 'category 2'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;
        $result = $this->subject->main('', []);

        self::assertStringContainsString(
            'category 1',
            $result,
        );
        self::assertStringContainsString(
            'category 2',
            $result,
        );
    }

    /**
     * @test
     */
    public function singleViewForCategoryWithoutIconDoesNotShowCategoryIcon(): void
    {
        $this->addCategoryRelation(
            ['title' => 'category 1'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringNotContainsString(
            'category 1 <img src="',
            $this->subject->main('', []),
        );
    }

    ///////////////////////////////////////////////////
    // Tests concerning the expiry in the single view
    ///////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForDateRecordWithExpiryContainsExpiryDate(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $this->seminarUid,
                'expiry' => mktime(0, 0, 0, 1, 1, 2008),
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $uid;

        self::assertStringContainsString(
            '2008-01-01',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForDateRecordWithoutExpiryNotContainsExpiryLabel(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $this->seminarUid,
                'expiry' => 0,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $uid;

        self::assertStringNotContainsString(
            $this->translate('label_expiry'),
            $this->subject->main('', []),
        );
    }

    /////////////////////////////////////////////////////////////
    // Tests concerning the payment methods in the single view.
    /////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForEventWithoutPaymentMethodsNotContainsLabelForPaymentMethods(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringNotContainsString(
            $this->translate('label_paymentmethods'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithOnePaymentMethodContainsLabelForPaymentMethods(): void
    {
        $paymentMethodUid = $this->testingFramework->createRecord(
            'tx_seminars_payment_methods',
            ['title' => 'Payment Method'],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['payment_methods' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_payment_methods_mm',
            $this->seminarUid,
            $paymentMethodUid,
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('label_paymentmethods'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithOnePaymentMethodContainsOnePaymentMethod(): void
    {
        $paymentMethodUid = $this->testingFramework->createRecord(
            'tx_seminars_payment_methods',
            ['title' => 'Payment Method'],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['payment_methods' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_payment_methods_mm',
            $this->seminarUid,
            $paymentMethodUid,
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            'Payment Method',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithTwoPaymentMethodsContainsTwoPaymentMethods(): void
    {
        $paymentMethodUid1 = $this->testingFramework->createRecord(
            'tx_seminars_payment_methods',
            ['title' => 'Payment Method 1'],
        );
        $paymentMethodUid2 = $this->testingFramework->createRecord(
            'tx_seminars_payment_methods',
            ['title' => 'Payment Method 2'],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'payment_methods' => 2,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_payment_methods_mm',
            $this->seminarUid,
            $paymentMethodUid1,
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_payment_methods_mm',
            $this->seminarUid,
            $paymentMethodUid2,
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $result = $this->subject->main('', []);
        self::assertStringContainsString(
            'Payment Method 1',
            $result,
        );
        self::assertStringContainsString(
            'Payment Method 2',
            $result,
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithOnePaymentMethodContainsPaymentMethodTitleProcessedByHtmlspecialchars(): void
    {
        $paymentMethodTitle = '<b>Payment & Method</b>';
        $paymentMethodUid = $this->testingFramework->createRecord(
            'tx_seminars_payment_methods',
            ['title' => $paymentMethodTitle],
        );
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['payment_methods' => 1],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_payment_methods_mm',
            $this->seminarUid,
            $paymentMethodUid,
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            \htmlspecialchars($paymentMethodTitle, ENT_QUOTES | ENT_HTML5),
            $this->subject->main('', []),
        );
    }

    ///////////////////////////////////////////////////////
    // Tests concerning the organizers in the single view
    ///////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewDoesNotHaveUnreplacedMarkers(): void
    {
        $this->addOrganizerRelation(['title' => 'foo organizer']);

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringNotContainsString(
            '###',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithTwoOrganizersShowsBothOrganizers(): void
    {
        $this->addOrganizerRelation(['title' => 'organizer 1']);
        $this->addOrganizerRelation(['title' => 'organizer 2']);

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertMatchesRegularExpression(
            '/organizer 1.*organizer 2/s',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithOrganizerWithHomepageHtmlSpecialcharsTitleOfOrganizer(): void
    {
        $this->addOrganizerRelation(
            ['title' => 'foo<bar'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            'foo&lt;bar',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithOrganizerWithoutHomepageHtmlSpecialCharsTitleOfOrganizer(): void
    {
        $this->addOrganizerRelation(
            ['title' => 'foo<bar'],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            \htmlspecialchars('foo<bar', ENT_QUOTES | ENT_HTML5),
            $this->subject->main('', []),
        );
    }

    //////////////////////////////////////////////////
    // Tests concerning hidden events in single view
    //////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForZeroEventUidNoLoggedInUserReturnsWrongSeminarNumberMessage(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = 0;

        self::assertStringContainsString(
            $this->translate('message_missingSeminarNumber'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForHiddenRecordAndNoLoggedInUserReturnsWrongSeminarNumberMessage(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['hidden' => 1],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('message_wrongSeminarNumber'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForHiddenRecordAndLoggedInUserNotOwnerOfHiddenRecordReturnsWrongSeminarNumberMessage(): void
    {
        $this->testingFramework->createAndLoginFrontEndUser();
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['hidden' => 1],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('message_wrongSeminarNumber'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForHiddenRecordAndLoggedInUserOwnerOfHiddenRecordShowsHiddenEvent(): void
    {
        $ownerUid = $this->testingFramework->createAndLoginFrontEndUser();
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'hidden' => 1,
                'title' => 'hidden event',
                'owner_feuser' => $ownerUid,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            'hidden event',
            $this->subject->main('', []),
        );
    }

    // Tests concerning the basic functions of the list view

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedEventSubtitle(): void
    {
        self::assertStringContainsString(
            'Something for you &amp; me',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedEventTypeTitle(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'event_type' => $this->testingFramework->createRecord(
                    'tx_seminars_event_types',
                    ['title' => 'foo & type'],
                ),
            ],
        );

        self::assertStringContainsString(
            'foo &amp; type',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedAccreditationNumber(): void
    {
        self::assertStringContainsString(
            '1 &amp; 1',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedPlaceTitle(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['place' => 1],
        );
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'a & place'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $this->seminarUid,
            $placeUid,
        );

        self::assertStringContainsString(
            'a &amp; place',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedCityTitle(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['place' => 1],
        );
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => 'Bonn & Köln'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $this->seminarUid,
            $placeUid,
        );

        self::assertStringContainsString(
            'Bonn &amp; Köln',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedOrganizerTitle(): void
    {
        $this->addOrganizerRelation(['title' => 'foo & organizer']);

        self::assertStringContainsString(
            'foo &amp; organizer',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewShowsHtmlspecialcharedTargetGroupTitle(): void
    {
        $this->addTargetGroupRelation(
            ['title' => 'group 1 & 2'],
        );

        self::assertStringContainsString(
            'group 1 &amp; 2',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForSeminarWithoutImageDoesNotDisplayImage(): void
    {
        self::assertStringNotContainsString(
            '<img src="',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForSeminarWithoutImageRemovesImageMarker(): void
    {
        self::assertStringNotContainsString(
            '###IMAGE###',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewNotContainsExpiryLabel(): void
    {
        self::assertStringNotContainsString(
            $this->translate('label_expiry'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewHidesStatusColumnByDefault(): void
    {
        $this->subject->main('', []);

        self::assertFalse(
            $this->subject->isSubpartVisible('LISTHEADER_WRAPPER_STATUS'),
        );
    }

    /**
     * @test
     */
    public function listViewShowsBookedOutEventByDefault(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'needs_registration' => 1,
                'attendees_max' => 5,
                'offline_attendees' => 5,
            ],
        );

        self::assertStringContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForShowOnlyEventsWithVacanciesSetHidesBookedOutEvent(): void
    {
        $this->subject->setConfigurationValue(
            'showOnlyEventsWithVacancies',
            true,
        );

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'needs_registration' => 1,
                'attendees_max' => 5,
                'offline_attendees' => 5,
            ],
        );

        self::assertStringNotContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /////////////////////////////////////////////////////////
    // Tests concerning the result counter in the list view
    /////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function resultCounterIsZeroForNoResults(): void
    {
        $this->subject->setConfigurationValue(
            'pidList',
            $this->testingFramework->createSystemFolder(),
        );
        $this->subject->main('', []);

        self::assertEquals(
            0,
            $this->subject->internal['res_count'],
        );
    }

    /**
     * @test
     */
    public function resultCounterIsOneForOneResult(): void
    {
        $this->subject->main('', []);

        self::assertEquals(
            1,
            $this->subject->internal['res_count'],
        );
    }

    /**
     * @test
     */
    public function resultCounterIsTwoForTwoResultsOnOnePage(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Another event',
            ],
        );
        $this->subject->main('', []);

        self::assertEquals(
            2,
            $this->subject->internal['res_count'],
        );
    }

    /**
     * @test
     */
    public function resultCounterIsSixForSixResultsOnTwoPages(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'pid' => $this->systemFolderPid,
                    'title' => 'Another event',
                ],
            );
        }
        $this->subject->main('', []);

        self::assertEquals(
            6,
            $this->subject->internal['res_count'],
        );
    }

    //////////////////////////////////////////////////////////
    // Tests concerning the list view, filtered by category.
    //////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewWithCategoryContainsEventsWithSelectedAndOtherCategory(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                // the number of categories
                'categories' => 2,
            ],
        );
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid1,
        );

        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'another category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid2,
        );
        $this->subject->piVars['category'] = $categoryUid2;

        self::assertStringContainsString(
            'Event with category',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewWithCategoryContainsEventsWithOneOfTwoSelectedCategories(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation('tx_seminars_seminars_categories_mm', $eventUid, $categoryUid);
        $this->subject->piVars['categories'][] = (string)$categoryUid;

        self::assertStringContainsString(
            'Event with category',
            $this->subject->main('', []),
        );
    }

    /////////////////////////////////////////////////////////
    // Tests concerning the the list view, filtered by date
    /////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewForGivenFromDateShowsEventWithBeginDateAfterFromDate(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $fromTime = $simTime - 86400;
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event From',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $fromTime);
        $this->subject->piVars['from_month'] = date('n', $fromTime);
        $this->subject->piVars['from_year'] = date('Y', $fromTime);

        self::assertStringContainsString(
            'Foo Event From',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromDateDoesNotShowEventWithBeginDateBeforeFromDate(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $fromTime = $simTime + 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event From',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $fromTime);
        $this->subject->piVars['from_month'] = date('n', $fromTime);
        $this->subject->piVars['from_year'] = date('Y', $fromTime);

        self::assertStringNotContainsString(
            'Foo Event From',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromDateWithMissingDayShowsEventWithBeginDateOnFirstDayOfMonth(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event From',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_month'] = date('n', $simTime);
        $this->subject->piVars['from_year'] = date('Y', $simTime);

        self::assertStringContainsString(
            'Foo Event From',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromDateWithMissingYearShowsEventWithBeginDateInCurrentYearAfterFromDate(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $fromTime = $simTime - 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event From',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $fromTime);
        $this->subject->piVars['from_month'] = date('n', $fromTime);

        self::assertStringContainsString(
            'Foo Event From',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromDateWithMissingMonthShowsEventWithBeginDateOnFirstMonthOfYear(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event From',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $simTime);
        $this->subject->piVars['from_year'] = date('Y', $simTime);

        self::assertStringContainsString(
            'Foo Event From',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromDateWithMissingMonthAndDayShowsEventWithBeginDateOnFirstDayOfGivenYear(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event From',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_year'] = date('Y', $simTime);

        self::assertStringContainsString(
            'Foo Event From',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenToDateShowsEventWithBeginDateBeforeToDate(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $toTime = $simTime + 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['to_day'] = date('j', $toTime);
        $this->subject->piVars['to_month'] = date('n', $toTime);
        $this->subject->piVars['to_year'] = date('Y', $toTime);

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenToDateHidesEventWithBeginDateAfterToDate(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $toTime = $simTime - 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['to_day'] = date('j', $toTime);
        $this->subject->piVars['to_month'] = date('n', $toTime);
        $this->subject->piVars['to_year'] = date('Y', $toTime);

        self::assertStringNotContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenToDateWithMissingDayShowsEventWithBeginDateOnEndOfGivenMonth(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['to_month'] = date('n', $simTime);
        $this->subject->piVars['to_year'] = date('Y', $simTime);

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenToDateWithMissingYearShowsEventWithBeginDateOnThisYearBeforeToDate(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $toTime = $simTime + 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['to_day'] = date('j', $toTime);
        $this->subject->piVars['to_month'] = date('n', $toTime);

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenToDateWithMissingMonthShowsEventWithBeginDateOnDayOfLastMonthOfGivenYear(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['to_day'] = date('j', $simTime);
        $this->subject->piVars['to_year'] = date('Y', $simTime);

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenToDateWithMissingMonthAndDayShowsEventWithBeginDateOnEndOfGivenYear(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['to_year'] = date('Y', $simTime);

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromAndToDatesShowsEventWithBeginDateWithinTimespan(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $fromTime = $simTime - 86400;
        $toTime = $simTime + 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $fromTime);
        $this->subject->piVars['from_month'] = date('n', $fromTime);
        $this->subject->piVars['from_year'] = date('Y', $fromTime);
        $this->subject->piVars['to_day'] = date('j', $toTime);
        $this->subject->piVars['to_month'] = date('n', $toTime);
        $this->subject->piVars['to_year'] = date('Y', $toTime);

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromAndToDatesCanShowTwoEventsWithBeginDateWithinTimespan(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $fromTime = $simTime - 86400;
        $toTime = $simTime + 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => $simTime,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Bar Event To',
                'begin_date' => $simTime,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $fromTime);
        $this->subject->piVars['from_month'] = date('n', $fromTime);
        $this->subject->piVars['from_year'] = date('Y', $fromTime);
        $this->subject->piVars['to_day'] = date('j', $toTime);
        $this->subject->piVars['to_month'] = date('n', $toTime);
        $this->subject->piVars['to_year'] = date('Y', $toTime);

        $output = $this->subject->main('', []);

        self::assertStringContainsString(
            'Foo Event To',
            $output,
        );
        self::assertStringContainsString(
            'Bar Event To',
            $output,
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromAndToDatesDoesNotShowEventWithBeginDateBeforeTimespan(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $toTime = $simTime + 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'begin_date' => $simTime - 86000,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $simTime);
        $this->subject->piVars['from_month'] = date('n', $simTime);
        $this->subject->piVars['from_year'] = date('Y', $simTime);
        $this->subject->piVars['to_day'] = date('j', $toTime);
        $this->subject->piVars['to_month'] = date('n', $toTime);
        $this->subject->piVars['to_year'] = date('Y', $toTime);

        self::assertStringNotContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenFromAndToDatesDoesNotShowEventWithBeginDateAfterTimespan(): void
    {
        $simTime = (int)GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $fromTime = $simTime - 86400;

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'begin_date' => $simTime + 86400,
            ],
        );

        $this->subject->piVars['from_day'] = date('j', $fromTime);
        $this->subject->piVars['from_month'] = date('n', $fromTime);
        $this->subject->piVars['from_year'] = date('Y', $fromTime);
        $this->subject->piVars['to_day'] = date('j', $simTime);
        $this->subject->piVars['to_month'] = date('n', $simTime);
        $this->subject->piVars['to_year'] = date('Y', $simTime);

        self::assertStringNotContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForSentDateButAllDatesZeroShowsEventWithoutBeginDate(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
            ],
        );

        $this->subject->piVars['from_day'] = 0;
        $this->subject->piVars['from_month'] = 0;
        $this->subject->piVars['from_year'] = 0;
        $this->subject->piVars['to_day'] = 0;
        $this->subject->piVars['to_month'] = 0;
        $this->subject->piVars['to_year'] = 0;

        self::assertStringContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    ///////////////////////////////////////////////////////////
    // Tests concerning the filtering by age in the list view
    ///////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewForGivenAgeShowsEventWithTargetgroupWithinAge(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 20],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 50,
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->piVars['age'] = 15;

        self::assertStringContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenAgeAndEventAgespanHigherThanAgeDoesNotShowThisEvent(): void
    {
        $targetGroupUid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            ['minimum_age' => 5, 'maximum_age' => 20],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event To',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 50,
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $targetGroupUid,
            'target_groups',
        );

        $this->subject->piVars['age'] = 4;

        self::assertStringNotContainsString(
            'Foo Event To',
            $this->subject->main('', []),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests concerning the filtering by organizer in the list view
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewForGivenOrganizerShowsEventWithOrganizer(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'Foo Event', 'pid' => $this->systemFolderPid],
        );

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        $this->subject->piVars['organizer'][] = $organizerUid;

        self::assertStringContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenOrganizerDoesNotShowEventWithOtherOrganizer(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['title' => 'Foo Event', 'pid' => $this->systemFolderPid],
        );

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        $this->subject->piVars['organizer'][]
            = $this->testingFramework->createRecord('tx_seminars_organizers');

        self::assertStringNotContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /////////////////////////////////////////////////////////////
    // Tests concerning the filtering by price in the list view
    /////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewForGivenPriceFromShowsEventWithRegularPriceHigherThanPriceFrom(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'price_regular' => 21,
            ],
        );

        $this->subject->piVars['price_from'] = 20;

        self::assertStringContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenPriceToShowsEventWithRegularPriceLowerThanPriceTo(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'price_regular' => 19,
            ],
        );

        $this->subject->piVars['price_to'] = 20;

        self::assertStringContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenPriceRangeShowsEventWithRegularPriceWithinRange(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'price_regular' => 21,
            ],
        );

        $this->subject->piVars['price_from'] = 20;
        $this->subject->piVars['price_to'] = 22;

        self::assertStringContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForGivenPriceRangeHidesEventWithRegularPriceOutsideRange(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Foo Event',
                'price_regular' => 23,
            ],
        );

        $this->subject->piVars['price_from'] = 20;
        $this->subject->piVars['price_to'] = 22;

        self::assertStringNotContainsString(
            'Foo Event',
            $this->subject->main('', []),
        );
    }

    ///////////////////////////////////////////////////
    // Tests concerning the sorting in the list view.
    ///////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewCanBeSortedByTitleAscending(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event B',
            ],
        );

        $this->subject->piVars['sort'] = 'title:0';
        $output = $this->subject->main('', []);

        self::assertTrue(
            strpos($output, 'Event A') < strpos($output, 'Event B'),
        );
    }

    /**
     * @test
     */
    public function listViewCanBeSortedByTitleDescending(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event B',
            ],
        );

        $this->subject->piVars['sort'] = 'title:1';
        $output = $this->subject->main('', []);

        self::assertTrue(
            strpos($output, 'Event B') < strpos($output, 'Event A'),
        );
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function listViewSortedByCategoryWithoutStaticTemplateAndEnabledConfigurationCheckDoesNotCrash(): void
    {
        $this->extensionConfiguration->setAsBoolean('enableConfigCheck', true);

        $subject = new TestingDefaultController();
        $subject->init(['sortListViewByCategory' => 1]);

        $subject->main('', []);
    }

    /**
     * @test
     */
    public function listViewCanBeSortedByTitleAscendingWithinOneCategory(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );

        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event B',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid,
        );

        $this->subject->setConfigurationValue('sortListViewByCategory', 1);
        $this->subject->piVars['sort'] = 'title:0';
        $output = $this->subject->main('', []);

        self::assertTrue(
            strpos($output, 'Event A') < strpos($output, 'Event B'),
        );
    }

    /**
     * @test
     */
    public function listViewCanBeSortedByTitleDescendingWithinOneCategory(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );

        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event B',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid,
        );

        $this->subject->setConfigurationValue('sortListViewByCategory', 1);
        $this->subject->piVars['sort'] = 'title:1';
        $output = $this->subject->main('', []);

        self::assertTrue(
            strpos($output, 'Event B') < strpos($output, 'Event A'),
        );
    }

    /**
     * @test
     */
    public function listViewCategorySortingComesBeforeSortingByTitle(): void
    {
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Category Y'],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid1,
        );

        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Category X'],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event B',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid2,
        );

        $this->subject->setConfigurationValue('sortListViewByCategory', 1);
        $this->subject->piVars['sort'] = 'title:0';
        $output = $this->subject->main('', []);

        self::assertTrue(
            strpos($output, 'Event B') < strpos($output, 'Event A'),
        );
    }

    /**
     * @test
     */
    public function listViewCategorySortingHidesRepeatedCategoryNames(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Category X'],
        );

        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event B',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid,
        );

        $this->subject->setConfigurationValue('sortListViewByCategory', 1);
        $this->subject->piVars['sort'] = 'title:0';

        self::assertEquals(
            1,
            mb_substr_count(
                $this->subject->main('', []),
                'Category X',
            ),
        );
    }

    /**
     * @test
     */
    public function listViewCategorySortingListsDifferentCategoryNames(): void
    {
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Category Y'],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid1,
        );

        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Category X'],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                // the number of categories
                'categories' => 1,
                'title' => 'Event B',
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid2,
        );

        $this->subject->setConfigurationValue('sortListViewByCategory', 1);
        $this->subject->piVars['sort'] = 'title:0';
        $output = $this->subject->main('', []);

        self::assertStringContainsString(
            'Category X',
            $output,
        );
        self::assertStringContainsString(
            'Category Y',
            $output,
        );
    }

    ////////////////////////////////////////////////////////////////////
    // Tests concerning the links to the single view in the list view.
    ////////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function teaserGetsLinkedToSingleView(): void
    {
        $this->subject->setConfigurationValue('hideColumns', '');

        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with teaser',
                'teaser' => 'Test Teaser',
            ],
        );

        self::assertStringContainsString(
            '>Test Teaser</a>',
            $this->subject->main('', []),
        );
    }

    //////////////////////////////////////////////////////////
    // Tests concerning the category links in the list view.
    //////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function categoryIsLinkedToTheFilteredListView(): void
    {
        self::markTestIncomplete('Fix this test to work with slugs.');

        // @phpstan-ignore-next-line Yes, this code is unreachable, and we know it.
        $listPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $listPageUid, ['slug' => '/eventList']);
        $this->pluginConfiguration->setAsInteger('listPID', $listPageUid);

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );

        $result = $this->subject->main('', []);

        self::assertStringContainsString('tx_seminars_pi1%5Bcategory%5D=' . $categoryUid, $result);
    }

    /**
     * @test
     */
    public function categoryIsNotLinkedFromSpecializedListView(): void
    {
        $listPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $listPageUid, ['slug' => '/eventList']);
        $this->pluginConfiguration->setAsInteger('listPID', $listPageUid);
        $this->subject->setConfigurationValue('what_to_display', 'events_next_day');

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                'end_date' => Time::SECONDS_PER_WEEK,
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );
        $this->subject->createSeminar($eventUid);

        self::assertStringNotContainsString(
            'tx_seminars_pi1[category%5D=' . $categoryUid,
            $this->subject->main('', []),
        );
    }

    ///////////////////////////////////////////////////////////
    // Tests concerning limiting the list view to event types
    ///////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewLimitedToEventTypesIgnoresEventsWithoutEventType(): void
    {
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'an event type'],
        );
        $this->subject->setConfigurationValue(
            'limitListViewToEventTypes',
            $eventTypeUid,
        );

        self::assertStringNotContainsString(
            'Test &amp; event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewLimitedToEventTypesContainsEventsWithMultipleSelectedEventTypes(): void
    {
        $eventTypeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'an event type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with type',
                'event_type' => $eventTypeUid1,
            ],
        );

        $eventTypeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'an event type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with another type',
                'event_type' => $eventTypeUid2,
            ],
        );

        $this->subject->setConfigurationValue(
            'limitListViewToEventTypes',
            $eventTypeUid1 . ',' . $eventTypeUid2,
        );

        $result = $this->subject->main('', []);
        self::assertStringContainsString(
            'Event with type',
            $result,
        );
        self::assertStringContainsString(
            'Event with another type',
            $result,
        );
    }

    /**
     * @test
     */
    public function listViewLimitedToEventTypesIgnoresEventsWithNotSelectedEventType(): void
    {
        $eventTypeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'an event type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with type',
                'event_type' => $eventTypeUid1,
            ],
        );

        $eventTypeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'another eventType'],
        );
        $this->subject->setConfigurationValue(
            'limitListViewToEventTypes',
            $eventTypeUid2,
        );

        self::assertStringNotContainsString(
            'Event with type',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForSingleEventTypeOverridesLimitToEventTypes(): void
    {
        $eventTypeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'an event type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with type',
                'event_type' => $eventTypeUid1,
            ],
        );

        $eventTypeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'an event type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with another type',
                'event_type' => $eventTypeUid2,
            ],
        );

        $this->subject->setConfigurationValue(
            'limitListViewToEventTypes',
            $eventTypeUid1,
        );
        $this->subject->piVars['event_type'] = [$eventTypeUid2];

        $result = $this->subject->main('', []);
        self::assertStringNotContainsString(
            'Event with type',
            $result,
        );
        self::assertStringContainsString(
            'Event with another type',
            $result,
        );
    }

    //////////////////////////////////////////////////////////
    // Tests concerning limiting the list view to categories
    //////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewLimitedToCategoriesIgnoresEventsWithoutCategory(): void
    {
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->subject->setConfigurationValue(
            'limitListViewToCategories',
            $categoryUid,
        );

        self::assertStringNotContainsString(
            'Test &amp; event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewLimitedToCategoriesContainsEventsWithMultipleSelectedCategories(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid1,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with another category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid2,
        );

        $this->subject->setConfigurationValue(
            'limitListViewToCategories',
            $categoryUid1 . ',' . $categoryUid2,
        );

        $result = $this->subject->main('', []);
        self::assertStringContainsString(
            'Event with category',
            $result,
        );
        self::assertStringContainsString(
            'Event with another category',
            $result,
        );
    }

    /**
     * @test
     */
    public function listViewLimitedToCategoriesIgnoresEventsWithNotSelectedCategory(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid1,
        );

        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'another category'],
        );
        $this->subject->setConfigurationValue(
            'limitListViewToCategories',
            $categoryUid2,
        );

        self::assertStringNotContainsString(
            'Event with category',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForSingleCategoryOverridesLimitToCategories(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid1,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with another category',
                // the number of categories
                'categories' => 1,
            ],
        );
        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'a category'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid2,
        );

        $this->subject->setConfigurationValue(
            'limitListViewToCategories',
            $categoryUid1,
        );
        $this->subject->piVars['category'] = $categoryUid2;

        $result = $this->subject->main('', []);
        self::assertStringNotContainsString(
            'Event with category',
            $result,
        );
        self::assertStringContainsString(
            'Event with another category',
            $result,
        );
    }

    // Tests concerning limiting the list view to places

    /**
     * @test
     */
    public function listViewLimitedToPlacesFromSelectorWidgetIgnoresFlexFormsValues(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with place',
                // the number of places
                'place' => 1,
            ],
        );
        $placeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'a place'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid1,
            $placeUid1,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with another place',
                // the number of places
                'place' => 1,
            ],
        );
        $placeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => 'a place'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_place_mm',
            $eventUid2,
            $placeUid2,
        );

        $this->subject->setConfigurationValue(
            'limitListViewToPlaces',
            $placeUid1,
        );
        $this->subject->piVars['place'] = [$placeUid2];

        $result = $this->subject->main('', []);
        self::assertStringNotContainsString(
            'Event with place',
            $result,
        );
        self::assertStringContainsString(
            'Event with another place',
            $result,
        );
    }

    //////////////////////////////////////////////////////////
    // Tests concerning limiting the list view to organizers
    //////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewLimitedToOrganizersContainsEventsWithSelectedOrganizer(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with organizer 1',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        $this->subject->setConfigurationValue(
            'limitListViewToOrganizers',
            $organizerUid,
        );

        $result = $this->subject->main('', []);

        self::assertStringContainsString(
            'Event with organizer 1',
            $result,
        );
    }

    /**
     * @test
     */
    public function listViewLimitedToOrganizerExcludesEventsWithNotSelectedOrganizer(): void
    {
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with organizer 1',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid1,
            $organizerUid1,
            'organizers',
        );

        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with organizer 2',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid2,
            $organizerUid2,
            'organizers',
        );

        $this->subject->setConfigurationValue(
            'limitListViewToOrganizers',
            $organizerUid1,
        );

        self::assertStringNotContainsString(
            'Event with organizer 2',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewLimitedToOrganizersFromSelectorWidgetIgnoresFlexFormsValues(): void
    {
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with organizer 1',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid1,
            $organizerUid1,
            'organizers',
        );

        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event with organizer 2',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid2,
            $organizerUid2,
            'organizers',
        );

        $this->subject->setConfigurationValue(
            'limitListViewToOrganizers',
            $organizerUid1,
        );
        $this->subject->piVars['organizer'] = [$organizerUid2];

        $result = $this->subject->main('', []);

        self::assertStringNotContainsString(
            'Event with organizer 1',
            $result,
        );
        self::assertStringContainsString(
            'Event with organizer 2',
            $result,
        );
    }

    ////////////////////////////////////////////////////////////
    // Tests concerning the registration link in the list view
    ////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewForEventWithUnlimitedVacanciesShowsRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );

        self::assertStringContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithNoVacanciesAndQueueShowsRegisterOnQueueLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
                'queue_size' => 1,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringContainsString(
            $this->translate('label_onlineRegistrationOnQueue'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithNoVacanciesAndNoQueueDoesNotShowRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
                'queue_size' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringNotContainsString(
            $this->translate('label_onlineRegistrationOnQueue'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithVacanciesAndNoDateShowsPrebookNowString(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
            ],
        );

        self::assertStringContainsString(
            $this->translate('label_onlinePrebooking'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithRegistrationBeginInFutureHidesRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'queue_size' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 20,
            ],
        );

        self::assertStringNotContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithRegistrationBeginInFutureShowsRegistrationOpenOnMessage(): void
    {
        $registrationBegin = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
            'date',
            'timestamp',
        ) + 20;
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'queue_size' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => $registrationBegin,
            ],
        );

        self::assertStringContainsString(
            \sprintf($this->translate('message_registrationOpensOn'), \date('Y-m-d H:i', $registrationBegin)),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithRegistrationBeginInPastShowsRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'queue_size' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) - 42,
            ],
        );

        self::assertStringContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewForEventWithoutRegistrationBeginShowsRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => 0,
            ],
        );

        self::assertStringContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    //////////////////////////////////////////
    // Tests concerning the "my events" view
    //////////////////////////////////////////

    /**
     * @test
     */
    public function myEventsContainsTitleOfEventWithRegularRegistrationForLoggedInUser(): void
    {
        $this->createLogInAndRegisterFeUser();
        $this->subject->setConfigurationValue('what_to_display', 'my_events');

        self::assertStringContainsString(
            'Test &amp; event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function myEventsContainsTitleOfEventWithNonbindingReservationForLoggedInUser(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createAndLoginFrontEndUser(),
                'registration_queue' => Registration::STATUS_NONBINDING_RESERVATION,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'my_events');

        self::assertStringContainsString(
            'Test &amp; event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function myEventsNotContainsTitleOfEventWithoutRegistrationForLoggedInUser(): void
    {
        $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setConfigurationValue('what_to_display', 'my_events');

        self::assertStringNotContainsString(
            'Test &amp; event',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function myEventsContainsExpiryOfEventWithExpiryAndRegistrationForLoggedInUser(): void
    {
        $this->createLogInAndRegisterFeUser();
        $this->subject->setConfigurationValue('what_to_display', 'my_events');

        self::assertStringContainsString(
            '2008-01-01',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function editSubpartIsHiddenInMyVipEventsListView(): void
    {
        $this->createLogInAndAddFeUserAsVip();
        $this->subject->setConfigurationValue('what_to_display', 'my_vip_events');

        $this->subject->main('', []);
        self::assertFalse(
            $this->subject->isSubpartVisible('LISTITEM_WRAPPER_EDIT'),
        );
    }

    /////////////////////////////////////////////////////////////////
    // Tests concerning the category list in the "my VIP events" view
    /////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function myVipEventsViewShowsCategoryTitleOfEvent(): void
    {
        $this->createLogInAndAddFeUserAsVip();
        $this->subject->setConfigurationValue('what_to_display', 'my_vip_events');

        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'category_foo'],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $categoryUid,
            'categories',
        );

        self::assertStringContainsString(
            'category_foo',
            $this->subject->main('', []),
        );
    }

    /////////////////////////////////////////////////////////////////////
    // Tests concerning the displaying of events in the VIP events view
    /////////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function myVipEventsViewWithTimeFrameSetToCurrentShowsCurrentEvent(): void
    {
        $this->createLogInAndAddFeUserAsVip();
        $this->subject->setConfigurationValue('what_to_display', 'my_vip_events');
        $this->subject->setConfigurationValue('timeframeInList', 'current');
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'title' => 'currentEvent',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) - 20,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 20,
            ],
        );

        self::assertStringContainsString(
            'currentEvent',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function myVipEventsViewWithTimeFrameSetToCurrentNotShowsEventInFuture(): void
    {
        $this->createLogInAndAddFeUserAsVip();
        $this->subject->setConfigurationValue('what_to_display', 'my_vip_events');
        $this->subject->setConfigurationValue('timeframeInList', 'current');
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'title' => 'futureEvent',
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 21,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );

        self::assertStringNotContainsString(
            'futureEvent',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function myVipEventsHidesRegistrationColumn(): void
    {
        $this->createLogInAndAddFeUserAsVip();
        $this->subject->setConfigurationValue('what_to_display', 'my_vip_events');

        $this->subject->main('', []);

        self::assertFalse(
            $this->subject->isSubpartVisible('LISTHEADER_WRAPPER_REGISTRATION'),
        );
    }

    // Tests concerning getFieldHeader

    /**
     * @test
     */
    public function getFieldHeaderContainsLabelOfKey(): void
    {
        self::assertStringContainsString(
            $this->translate('label_date'),
            $this->subject->getFieldHeader('date'),
        );
    }

    /**
     * @test
     */
    public function getFieldHeaderForSortableFieldAndSortingEnabledContainsLink(): void
    {
        $this->subject->setConfigurationValue('enableSortingLinksInListView', true);

        self::assertStringContainsString(
            '<a',
            $this->subject->getFieldHeader('date'),
        );
    }

    /**
     * @test
     */
    public function getFieldHeaderForSortableFieldAndSortingEnabledContainsLinkWithNoFollow(): void
    {
        $this->subject->setConfigurationValue('enableSortingLinksInListView', true);

        self::assertStringContainsString(
            'rel="nofollow"',
            $this->subject->getFieldHeader('date'),
        );
    }

    /**
     * @test
     */
    public function getFieldHeaderForSortableFieldAndSortingDisabledNotContainsLink(): void
    {
        $this->subject->setConfigurationValue('enableSortingLinksInListView', false);

        self::assertStringNotContainsString(
            '<a',
            $this->subject->getFieldHeader('date'),
        );
    }

    /**
     * @test
     */
    public function getFieldHeaderForNonSortableFieldAndSortingEnabledNotContainsLink(): void
    {
        $this->subject->setConfigurationValue('enableSortingLinksInListView', true);

        self::assertStringNotContainsString(
            '<a',
            $this->subject->getFieldHeader('register'),
        );
    }

    ////////////////////////////////////////////////
    // Tests concerning the getLoginLink function.
    ////////////////////////////////////////////////

    /**
     * @test
     */
    public function getLoginLinkWithLoggedOutUserAddsUidPiVarToUrl(): void
    {
        self::markTestIncomplete('Fix this test to work with slugs.');

        // @phpstan-ignore-next-line Yes, this code is unreachable, and we know it.
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'foo',
            ],
        );
        $this->testingFramework->logoutFrontEndUser();

        $loginPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $loginPageUid, ['slug' => '/login']);
        $this->pluginConfiguration->setAsInteger('loginPID', $loginPageUid);

        $otherPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $otherPageUid, ['slug' => '/other']);

        $result = $this->subject->getLoginLink('foo', $otherPageUid, $eventUid);

        self::assertStringContainsString(\rawurlencode('tx_seminars_pi1[uid]') . '=' . $eventUid, $result);
    }

    //////////////////////////////////////////////////////
    // Tests concerning the pagination of the list view.
    //////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewCanContainOneItemOnTheFirstPage(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        self::assertStringContainsString(
            'Event A',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function listViewCanContainTwoItemsOnTheFirstPage(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event B',
            ],
        );

        $output = $this->subject->main('', []);
        self::assertStringContainsString(
            'Event A',
            $output,
        );
        self::assertStringContainsString(
            'Event B',
            $output,
        );
    }

    /**
     * @test
     */
    public function firstPageOfListViewNotContainsItemForTheSecondPage(): void
    {
        $this->subject->setConfigurationValue(
            'listView.',
            [
                'orderBy' => 'title',
                'descFlag' => 0,
                'results_at_a_time' => 1,
                'maxPages' => 5,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event B',
            ],
        );

        self::assertStringNotContainsString(
            'Event B',
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function secondPageOfListViewContainsItemForTheSecondPage(): void
    {
        $this->subject->setConfigurationValue(
            'listView.',
            [
                'orderBy' => 'title',
                'descFlag' => 0,
                'results_at_a_time' => 1,
                'maxPages' => 5,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event B',
            ],
        );

        $this->subject->piVars['pointer'] = 1;
        self::assertStringContainsString(
            'Event B',
            $this->subject->main('', []),
        );
    }

    ////////////////////////////////////////////////////////////////
    // Tests concerning the attached files column in the list view
    ////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function listViewForLoggedOutUserAndLimitFileDownloadToAttendeesTrueHidesAttachedFilesHeader(): void
    {
        $this->testingFramework->logoutFrontEndUser();
        $this->subject->setConfigurationValue('hideColumns', '');
        $this->subject->setConfigurationValue('limitFileDownloadToAttendees', 1);
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        $this->subject->main('', []);

        self::assertFalse(
            $this->subject->isSubpartVisible('LISTHEADER_WRAPPER_ATTACHED_FILES'),
        );
    }

    /**
     * @test
     */
    public function listViewForLoggedOutUserAndLimitFileDownloadToAttendeesFalseShowsAttachedFilesHeader(): void
    {
        $this->testingFramework->logoutFrontEndUser();
        $this->subject->setConfigurationValue('hideColumns', '');
        $this->subject->setConfigurationValue('limitFileDownloadToAttendees', 0);
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        $this->subject->main('', []);

        self::assertTrue(
            $this->subject->isSubpartVisible('LISTHEADER_WRAPPER_ATTACHED_FILES'),
        );
    }

    /**
     * @test
     */
    public function listViewForLoggedOutUserAndLimitFileDownloadToAttendeesTrueHidesAttachedFilesListRowItem(): void
    {
        $this->testingFramework->logoutFrontEndUser();
        $this->subject->setConfigurationValue('hideColumns', '');
        $this->subject->setConfigurationValue('limitFileDownloadToAttendees', 1);
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        $this->subject->main('', []);

        self::assertFalse(
            $this->subject->isSubpartVisible('LISTITEM_WRAPPER_ATTACHED_FILES'),
        );
    }

    /**
     * @test
     */
    public function listViewForLoggedOutUserAndLimitFileDownloadToAttendeesFalseShowsAttachedFilesListRowItem(): void
    {
        $this->testingFramework->logoutFrontEndUser();
        $this->subject->setConfigurationValue('hideColumns', '');
        $this->subject->setConfigurationValue('limitFileDownloadToAttendees', 0);
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        $this->subject->main('', []);

        self::assertTrue(
            $this->subject->isSubpartVisible('LISTITEM_WRAPPER_ATTACHED_FILES'),
        );
    }

    /**
     * @test
     */
    public function listViewForLoggedInUserShowsAttachedFilesHeader(): void
    {
        $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setConfigurationValue('hideColumns', '');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        $this->subject->main('', []);

        self::assertTrue(
            $this->subject->isSubpartVisible('LISTHEADER_WRAPPER_ATTACHED_FILES'),
        );
    }

    /**
     * @test
     */
    public function listViewForLoggedInUserShowsAttachedFilesListRowItem(): void
    {
        $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setConfigurationValue('hideColumns', '');
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'title' => 'Event A',
            ],
        );

        $this->subject->main('', []);

        self::assertTrue(
            $this->subject->isSubpartVisible('LISTITEM_WRAPPER_ATTACHED_FILES'),
        );
    }

    //////////////////////////////////////////////////////////////
    // Tests concerning the registration link in the single view
    //////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function singleViewForEventWithUnlimitedVacanciesShowsRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithNoVacanciesAndQueueShowsRegisterOnQueueLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
                'queue_size' => 1,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('label_onlineRegistrationOnQueue'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithNoVacanciesAndNoQueueDoesNotShowRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
                'queue_size' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringNotContainsString(
            $this->translate('label_onlineRegistrationOnQueue'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithVacanciesAndNoDateShowsPrebookNowString(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 1,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('label_onlinePrebooking'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithRegistrationBeginInFutureDoesNotShowRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 40,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringNotContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithRegistrationBeginInFutureShowsRegistrationOpensOnMessage(): void
    {
        $registrationBegin = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
            'date',
            'timestamp',
        ) + 40;
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => $registrationBegin,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            \sprintf($this->translate('message_registrationOpensOn'), \date('Y-m-d H:i', $registrationBegin)),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithRegistrationBeginInPastShowsRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) - 42,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    /**
     * @test
     */
    public function singleViewForEventWithoutRegistrationBeginShowsRegistrationLink(): void
    {
        $this->subject->setConfigurationValue('enableRegistration', true);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            [
                'needs_registration' => 1,
                'attendees_max' => 0,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 42,
                'begin_date_registration' => 0,
            ],
        );

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        self::assertStringContainsString(
            $this->translate('label_onlineRegistration'),
            $this->subject->main('', []),
        );
    }

    // Tests concerning getVacanciesClasses

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithEnoughVacanciesReturnsAvailableClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNumberOfAttendances(0);
        $event->setNeedsRegistration(true);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-vacancies-available',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithOneVacancyReturnsVacancyOneClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNumberOfAttendances(9);
        $event->setNeedsRegistration(true);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-vacancies-1',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithTwoVacanciesReturnsVacancyTwoClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNumberOfAttendances(8);
        $event->setNeedsRegistration(true);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-vacancies-2',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithNoVacanciesReturnsVacancyZeroClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNumberOfAttendances(10);
        $event->setNeedsRegistration(true);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-vacancies-0',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithUnlimitedVacanciesReturnsVacanciesAvailableClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setUnlimitedVacancies();
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-vacancies-available',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithUnlimitedVacanciesDoesNotReturnZeroVacancyClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setUnlimitedVacancies();
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringNotContainsString(
            'tx-seminars-pi1-vacancies-0',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithUnlimitedVacanciesReturnsVacanciesUnlimitedClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setUnlimitedVacancies();
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-vacancies-unlimited',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForRegistrationDeadlineInPastReturnsDeadlineOverClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setNeedsRegistration(true);
        $event->setRegistrationDeadline(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) - 45,
        );
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 45,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-registration-deadline-over',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForBeginDateInPastReturnsBeginDateOverClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setNeedsRegistration(true);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) - 45,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-event-begin-date-over',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithNoVacanciesAndRegistrationQueueReturnsRegistrationQueueClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNumberOfAttendances(10);
        $event->setNeedsRegistration(true);
        $event->setRegistrationQueue(true);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringContainsString(
            'tx-seminars-pi1-has-registration-queue',
            $this->subject->getVacanciesClasses($event),
        );
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithNoVacanciesAndNoRegistrationQueueDoesNotReturnRegistrationQueueClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNumberOfAttendances(10);
        $event->setNeedsRegistration(true);
        $event->setRegistrationQueue(false);
        $event->setBeginDate(
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                'date',
                'timestamp',
            ) + 42,
        );

        self::assertStringNotContainsString(
            'tx-seminars-pi1-has-registration-queue',
            $this->subject->getVacanciesClasses($event),
        );
    }

    // Tests concerning getVacanciesClasses for events without date

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithoutDateAndWithEnoughVacanciesReturnsAvailableClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNeedsRegistration(true);
        $event->setNumberOfAttendances(0);

        $output = $this->subject->getVacanciesClasses($event);

        self::assertStringContainsString('tx-seminars-pi1-vacancies-available', $output);
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithoutDateAndWithOneVacancyReturnsVacancyOneClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNeedsRegistration(true);
        $event->setNumberOfAttendances(9);

        $output = $this->subject->getVacanciesClasses($event);

        self::assertStringContainsString('tx-seminars-pi1-vacancies-1', $output);
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithoutDateAndWithTwoVacanciesReturnsVacancyTwoClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNeedsRegistration(true);
        $event->setNumberOfAttendances(8);

        $output = $this->subject->getVacanciesClasses($event);

        self::assertStringContainsString('tx-seminars-pi1-vacancies-2', $output);
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithoutDateAndWithNoVacanciesReturnsVacancyZeroClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setAttendancesMax(10);
        $event->setNeedsRegistration(true);
        $event->setNumberOfAttendances(10);

        $output = $this->subject->getVacanciesClasses($event);

        self::assertStringContainsString('tx-seminars-pi1-vacancies-0', $output);
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithoutDateAndWithUnlimitedVacanciesReturnsAvailableClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setUnlimitedVacancies();
        $event->setNumberOfAttendances(0);

        $output = $this->subject->getVacanciesClasses($event);

        self::assertStringContainsString('tx-seminars-pi1-vacancies-available', $output);
    }

    /**
     * @test
     */
    public function getVacanciesClassesForEventWithoutDateAndWithUnlimitedVacanciesDoesNotReturnDeadlineOverClass(): void
    {
        $event = new TestingLegacyEvent($this->seminarUid);
        $event->setUnlimitedVacancies();
        $event->setNumberOfAttendances(0);

        $output = $this->subject->getVacanciesClasses($event);

        self::assertStringNotContainsString('tx-seminars-pi1-registration-deadline-over', $output);
    }

    //////////////////////////////////
    // Tests concerning initListView
    //////////////////////////////////

    /**
     * @test
     */
    public function initListViewForDefaultListLimitsListByAdditionalParameters(): void
    {
        $subject = $this->createPartialMock(
            TestingDefaultController::class,
            ['limitForAdditionalParameters'],
        );
        $subject->setContentObjectRenderer($this->createMock(ContentObjectRenderer::class));
        $subject->expects(self::once())->method('limitForAdditionalParameters');

        $subject->initListView();
    }

    /**
     * @test
     */
    public function initListViewForTopicListLimitsListByAdditionalParameters(): void
    {
        $subject = $this->createPartialMock(
            TestingDefaultController::class,
            ['limitForAdditionalParameters'],
        );
        $subject->setContentObjectRenderer($this->createMock(ContentObjectRenderer::class));
        $subject->expects(self::once())->method('limitForAdditionalParameters');

        $subject->initListView('topic_list');
    }

    /**
     * @test
     */
    public function initListViewForMyEventsListNotLimitsListByAdditionalParameters(): void
    {
        $subject = $this->createPartialMock(
            TestingDefaultController::class,
            ['limitForAdditionalParameters'],
        );
        $subject->setContentObjectRenderer($this->createMock(ContentObjectRenderer::class));
        $subject->expects(self::never())->method('limitForAdditionalParameters');

        $subject->initListView('my_events');
    }

    ////////////////////////////////////////////////////////////
    // Tests concerning hideListRegistrationsColumnIfNecessary
    ////////////////////////////////////////////////////////////

    /**
     * Data provider for the tests concerning
     * hideListRegistrationsColumnIfNecessary.
     *
     * @return array[] nested array with the following inner keys:
     *               [getsHidden] boolean: whether the registration column is
     *                            expected to be hidden
     *               [whatToDisplay] string: the value for what_to_display
     *               [listPid] integer: the PID of the registration list page
     *               [vipListPid] integer: the PID of the VIP registration list
     *                            page
     */
    public function hideListRegistrationsColumnIfNecessaryDataProvider(): array
    {
        return [
            'notHiddenForListForBothPidsSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'seminar_list',
                'listPid' => 1,
                'vipListPid' => 1,
            ],
            'hiddenForListForNoPidsSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'seminar_list',
                'listPid' => 0,
                'vipListPid' => 0,
            ],
            'notHiddenForListForListPidSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'seminar_list',
                'listPid' => 1,
                'vipListPid' => 0,
            ],
            'notHiddenForListForVipListPidSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'seminar_list',
                'listPid' => 0,
                'vipListPid' => 1,
            ],
            'hiddenForOtherDatesForAndBothPidsSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'other_dates',
                'listPid' => 1,
                'vipListPid' => 1,
            ],
            'hiddenForEventsNextDayForBothPidsSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'events_next_day',
                'listPid' => 1,
                'vipListPid' => 1,
            ],
            'notHiddenForMyEventsForBothPidsSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'my_events',
                'listPid' => 1,
                'vipListPid' => 1,
            ],
            'hiddenForMyEventsForNoPidsSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'my_events',
                'listPid' => 0,
                'vipListPid' => 0,
            ],
            'notHiddenForMyEventsForListPidSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'my_events',
                'listPid' => 1,
                'vipListPid' => 0,
            ],
            'hiddenForMyEventsForVipListPidSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'my_events',
                'listPid' => 0,
                'vipListPid' => 1,
            ],
            'notHiddenForMyVipEventsForBothPidsSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'my_vip_events',
                'listPid' => 1,
                'vipListPid' => 1,
            ],
            'hiddenForMyVipEventsForNoPidsSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'my_vip_events',
                'listPid' => 0,
                'vipListPid' => 0,
            ],
            'hiddenForMyVipEventsForListPidSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'my_vip_events',
                'listPid' => 1,
                'vipListPid' => 0,
            ],
            'notHiddenForMyVipEventsForVipListPidSet' => [
                'getsHidden' => false,
                'whatToDisplay' => 'my_vip_events',
                'listPid' => 0,
                'vipListPid' => 1,
            ],
            'hiddenForTopicListForBothPidsSet' => [
                'getsHidden' => true,
                'whatToDisplay' => 'topic_list',
                'listPid' => 1,
                'vipListPid' => 1,
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider hideListRegistrationsColumnIfNecessaryDataProvider
     *
     * @param bool $getsHidden
     * @param string $whatToDisplay
     * @param int $listPid
     * @param int $vipListPid
     */
    public function hideListRegistrationsColumnIfNecessaryWithRegistrationEnabledAndLoggedIn(
        bool $getsHidden,
        string $whatToDisplay,
        int $listPid,
        int $vipListPid
    ): void {
        $subject = $this->createPartialMock(
            TestingDefaultController::class,
            ['isRegistrationEnabled', 'isLoggedIn', 'hideColumns'],
        );
        $subject
            ->method('isRegistrationEnabled')
            ->willReturn(true);
        $subject
            ->method('isLoggedIn')
            ->willReturn(true);

        if ($getsHidden) {
            $subject
                ->expects(self::once())->method('hideColumns')
                ->with(['list_registrations']);
        } else {
            $subject->expects(self::never())->method('hideColumns');
        }

        $subject->init(
            [
                'registrationsListPID' => $listPid,
                'registrationsVipListPID' => $vipListPid,
            ],
        );

        $subject->hideListRegistrationsColumnIfNecessary($whatToDisplay);
    }

    /**
     * @test
     *
     * @dataProvider hideListRegistrationsColumnIfNecessaryDataProvider
     *
     * @param bool $getsHidden (ignored)
     * @param string $whatToDisplay
     * @param int $listPid
     * @param int $vipListPid
     */
    public function hideListRegistrationsColumnIfNecessaryWithRegistrationEnabledAndNotLoggedInAlwaysHidesColumn(
        bool $getsHidden,
        string $whatToDisplay,
        int $listPid,
        int $vipListPid
    ): void {
        $subject = $this->createPartialMock(
            TestingDefaultController::class,
            ['isRegistrationEnabled', 'isLoggedIn', 'hideColumns'],
        );
        $subject
            ->method('isRegistrationEnabled')
            ->willReturn(true);
        $subject
            ->method('isLoggedIn')
            ->willReturn(false);

        $subject
            ->expects(self::once())->method('hideColumns')
            ->with(['list_registrations']);

        $subject->init(
            [
                'registrationsListPID' => $listPid,
                'registrationsVipListPID' => $vipListPid,
            ],
        );

        $subject->hideListRegistrationsColumnIfNecessary($whatToDisplay);
    }

    /**
     * @test
     *
     * @dataProvider hideListRegistrationsColumnIfNecessaryDataProvider
     *
     * @param bool $getsHidden (ignored)
     * @param string $whatToDisplay
     * @param int $listPid
     * @param int $vipListPid
     */
    public function hideListRegistrationsColumnIfNecessaryWithRegistrationDisabledAndLoggedInAlwaysHidesColumn(
        bool $getsHidden,
        string $whatToDisplay,
        int $listPid,
        int $vipListPid
    ): void {
        $subject = $this->createPartialMock(
            TestingDefaultController::class,
            ['isRegistrationEnabled', 'isLoggedIn', 'hideColumns'],
        );
        $subject
            ->method('isRegistrationEnabled')
            ->willReturn(false);
        $subject
            ->method('isLoggedIn')
            ->willReturn(true);

        $subject
            ->expects(self::once())->method('hideColumns')
            ->with(['list_registrations']);

        $subject->init(
            [
                'registrationsListPID' => $listPid,
                'registrationsVipListPID' => $vipListPid,
            ],
        );

        $subject->hideListRegistrationsColumnIfNecessary($whatToDisplay);
    }

    // Tests concerning the hooks for the event lists

    /**
     * @test
     */
    public function listViewCallsSeminarListViewHookMethodsForTopicList(): void
    {
        $topic = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 1000,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 2000,
            ],
        );
        $this->subject->setConfigurationValue('what_to_display', 'topic_list');

        $hook = $this->createMock(SeminarListView::class);
        $hook->expects(self::once())->method('modifyListHeader')->with($this->subject);
        $hook->expects(self::once())->method('modifyListRow')->with($this->subject);
        $hook->expects(self::never())->method('modifyMyEventsListRow');
        $hook->expects(self::once())->method('modifyListFooter')->with($this->subject);
        $hook
            ->expects(self::once())->method('modifyEventBagBuilder')
            ->with($this->subject, self::anything(), 'topic_list');
        $hook->expects(self::never())->method('modifyRegistrationBagBuilder');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarListView::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->main('', []);
    }

    /**
     * @test
     */
    public function listViewCallsSeminarListViewHookMethodsForSeminarList(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'seminar_list');

        $hook = $this->createMock(SeminarListView::class);
        $hook->expects(self::once())->method('modifyListHeader')->with($this->subject);
        $hook->expects(self::once())->method('modifyListRow')->with($this->subject);
        $hook->expects(self::never())->method('modifyMyEventsListRow');
        $hook->expects(self::once())->method('modifyListFooter')->with($this->subject);
        $hook
            ->expects(self::once())->method('modifyEventBagBuilder')
            ->with($this->subject, self::anything(), 'seminar_list');
        $hook->expects(self::never())->method('modifyRegistrationBagBuilder');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarListView::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->main('', []);
    }

    /**
     * @test
     */
    public function singleViewCallsSeminarListViewHookMethodsForOtherDates(): void
    {
        $topicUId = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
            ],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUId,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 1000,
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 2000,
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->systemFolderPid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUId,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 11000, // > 1 day after first date
                'end_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 12000,
            ],
        );
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = (string)$dateUid;

        $hook = $this->createMock(SeminarListView::class);
        $hook->expects(self::exactly(2))->method('modifyListHeader')->with($this->subject);
        $hook->expects(self::exactly(2))->method('modifyListRow')->with($this->subject);
        $hook->expects(self::never())->method('modifyMyEventsListRow');
        $hook->expects(self::exactly(2))->method('modifyListFooter')->with($this->subject);
        $hook->expects(self::exactly(2))->method('modifyEventBagBuilder')->withConsecutive(
            [$this->subject, self::anything(), 'events_next_day'],
            [$this->subject, self::anything(), 'other_dates'],
        );
        $hook->expects(self::never())->method('modifyRegistrationBagBuilder');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarListView::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->main('', []);
    }

    /**
     * @test
     */
    public function listViewCallsSeminarListViewHookMethodsForMyEventsList(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'my_events');

        $this->createLogInAndRegisterFeUser();

        $hook = $this->createMock(SeminarListView::class);
        $hook->expects(self::once())->method('modifyListHeader')->with($this->subject);
        $hook->expects(self::once())->method('modifyListRow')->with($this->subject);
        $hook->expects(self::once())->method('modifyMyEventsListRow')->with($this->subject);
        $hook->expects(self::once())->method('modifyListFooter')->with($this->subject);
        $hook->expects(self::never())->method('modifyEventBagBuilder');
        $hook
            ->expects(self::once())->method('modifyRegistrationBagBuilder')
            ->with($this->subject, self::anything(), 'my_events');
        // We don't test for the second parameter (the bag builder instance here)
        // because we cannot access it from the outside.

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarListView::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->main('', []);
    }

    /**
     * @test
     */
    public function listViewCallsSeminarListViewHookMethodsForMyVipEventsList(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'my_vip_events');

        $this->createLogInAndAddFeUserAsVip();

        $hook = $this->createMock(SeminarListView::class);
        $hook->expects(self::once())->method('modifyListHeader')->with($this->subject);
        $hook->expects(self::once())->method('modifyListRow')->with($this->subject);
        $hook->expects(self::never())->method('modifyMyEventsListRow');
        $hook->expects(self::once())->method('modifyListFooter')->with($this->subject);
        $hook
            ->expects(self::once())->method('modifyEventBagBuilder')
            ->with($this->subject, self::anything(), 'my_vip_events');
        $hook->expects(self::never())->method('modifyRegistrationBagBuilder');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarListView::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->main('', []);
    }

    // Tests concerning createSingleViewLink

    /**
     * @test
     */
    public function createSingleViewLinkCreatesLinkToSingleViewPage(): void
    {
        self::markTestIncomplete('Fix this test to work without a mocked SingleView.');

        // @phpstan-ignore-next-line Yes, this code is unreachable, and we know it.
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel([]);

        self::assertStringContainsString(
            'href="index.php?id=42&amp;tx_seminars_pi1%5BshowUid%5D=1337"',
            $this->subject->createSingleViewLink($event, 'foo'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewForEventWithoutDescriptionWithAlwaysLinkSettingLinkUsesLinkText(): void
    {
        $this->subject->setConfigurationValue('linkToSingleView', 'always');
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel(['description' => '']);

        self::assertStringContainsString(
            '>foo</a>',
            $this->subject->createSingleViewLink($event, 'foo'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewForEventWithDescriptionWithAlwaysLinkSettingLinkUsesLinkText(): void
    {
        $this->subject->setConfigurationValue('linkToSingleView', 'always');
        $event = MapperRegistry::get(EventMapper::class)
            ->getLoadedTestingModel(['description' => 'Hello world!']);

        self::assertStringContainsString(
            '>foo</a>',
            $this->subject->createSingleViewLink($event, 'foo'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewForEventWithoutDescriptionWithNeverLinkSettingReturnsOnlyLabel(): void
    {
        $this->subject->setConfigurationValue('linkToSingleView', 'never');
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel(['description' => '']);

        self::assertSame(
            'foo &amp; bar',
            $this->subject->createSingleViewLink($event, 'foo & bar'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewForEventWithDescriptionWithConditionalLinkSettingLinkUsesLinkText(): void
    {
        $this->subject->setConfigurationValue('linkToSingleView', 'onlyForNonEmptyDescription');
        $event = MapperRegistry::get(EventMapper::class)
            ->getLoadedTestingModel(['description' => 'Hello world!']);

        self::assertStringContainsString(
            '>foo &amp; bar</a>',
            $this->subject->createSingleViewLink($event, 'foo & bar'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewForEventWithoutDescriptionWithConditionalLinkSettingReturnsOnlyLabel(): void
    {
        $this->subject->setConfigurationValue('linkToSingleView', 'onlyForNonEmptyDescription');
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel(['description' => '']);

        self::assertSame(
            'foo &amp; bar',
            $this->subject->createSingleViewLink($event, 'foo & bar'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewForEventWithDescriptionWithNeverLinkSettingReturnsOnlyLabel(): void
    {
        $this->subject->setConfigurationValue('linkToSingleView', 'never');
        $event = MapperRegistry::get(EventMapper::class)
            ->getLoadedTestingModel(['description' => 'Hello world!']);

        self::assertSame(
            'foo &amp; bar',
            $this->subject->createSingleViewLink($event, 'foo & bar'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewLinkByDefaultHtmlSpecialCharsLinkText(): void
    {
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel([]);

        self::assertStringContainsString(
            'Chaos &amp; Confusion',
            $this->subject->createSingleViewLink($event, 'Chaos & Confusion'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewLinkByWithHtmlSpecialCharsTrueHtmlSpecialCharsLinkText(): void
    {
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel([]);

        self::assertStringContainsString(
            'Chaos &amp; Confusion',
            $this->subject->createSingleViewLink($event, 'Chaos & Confusion'),
        );
    }

    /**
     * @test
     */
    public function createSingleViewLinkByWithHtmlSpecialCharsFalseNotHtmlSpecialCharsLinkText(): void
    {
        $event = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel([]);

        self::assertStringContainsString(
            'Chaos & Confusion',
            $this->subject->createSingleViewLink($event, 'Chaos & Confusion', false),
        );
    }

    // Tests concerning the price in the single view

    /**
     * @test
     */
    public function singleViewForNoStandardPriceDisplaysForFree(): void
    {
        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $result = $this->subject->main('', []);

        self::assertStringContainsString($this->translate('message_forFree'), $result);
    }

    /**
     * @test
     */
    public function singleViewForPriceOnRequestDisplaysOnRequest(): void
    {
        $this->testingFramework->changeRecord('tx_seminars_seminars', $this->seminarUid, ['price_on_request' => 1]);

        $this->subject->setConfigurationValue('what_to_display', 'single_view');
        $this->subject->piVars['showUid'] = $this->seminarUid;

        $result = $this->subject->main('', []);

        self::assertStringContainsString($this->translate('message_onRequest'), $result);
    }
}
