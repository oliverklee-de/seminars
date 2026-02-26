<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\Bag\OrganizerBag;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\FrontEnd\DefaultController;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\OldModel\LegacyEvent
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class LegacyEventTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected array $coreExtensionsToLoad = ['typo3/cms-extensionmanager'];

    private DummyConfiguration $configuration;

    private TestingLegacyEvent $subject;

    private TestingFramework $testingFramework;

    private int $unregistrationDeadline = 0;

    /**
     * @var positive-int
     */
    private int $now = 1524751343;

    private ?DefaultController $pi1 = null;

    private ConnectionPool $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();

        // Make sure that the test results do not depend on the machine's PHP time zone.
        \date_default_timezone_set('UTC');

        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));
        $this->now = (int)$context->getPropertyFromAspect('date', 'timestamp');

        $this->unregistrationDeadline = ($this->now + Time::SECONDS_PER_WEEK);

        $this->testingFramework = new TestingFramework('tx_seminars');
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $currenciesConnection = $this->connectionPool->getConnectionForTable('static_currencies');
        if ($currenciesConnection->count('*', 'static_currencies', []) === 0) {
            $currenciesConnection->insert(
                'static_currencies',
                [
                    'uid' => 49,
                    'cu_iso_3' => 'EUR',
                    'cu_iso_nr' => 978,
                    'cu_name_en' => 'Euro',
                    'cu_symbol_left' => '€',
                    'cu_thousands_point' => '.',
                    'cu_decimal_point' => ',',
                    'cu_decimal_digits' => 2,
                    'cu_sub_divisor' => 100,
                ],
            );
        }

        $this->configuration = new DummyConfiguration();
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $this->configuration);

        $this->getLanguageService();

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'deadline_unregistration' => $this->unregistrationDeadline,
                'attendees_min' => 5,
                'attendees_max' => 10,
                'object_type' => 0,
                'queue_size' => 0,
                'needs_registration' => 1,
            ],
        );
        $this->subject = new TestingLegacyEvent($uid);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        ConfigurationRegistry::purgeInstance();

        parent::tearDown();
    }

    // Utility functions

    /**
     * Creates a fake front end and a pi1 instance in `$this->pi1`.
     */
    private function createPi1(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);

        $this->pi1 = new DefaultController();
        $this->pi1->init(
            [
                'isStaticTemplateLoaded' => 1,
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
            ],
        );
        $this->pi1->getTemplateCode();
    }

    /**
     * Inserts a target group record into the database and creates a relation to
     * it from the fixture.
     *
     * @param array $targetGroupData data of the target group to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addTargetGroupRelation(array $targetGroupData = []): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_target_groups',
            $targetGroupData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_target_groups_mm',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfTargetGroups(
            $this->subject->getNumberOfTargetGroups() + 1,
        );

        return $uid;
    }

    /**
     * Inserts a payment method record into the database and creates a relation
     * to it from the fixture.
     *
     * @param array $paymentMethodData data of the payment method to add, may be empty
     *
     * @return positive-int the UID of the created record, will be > 0
     */
    private function addPaymentMethodRelation(array $paymentMethodData = []): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord('tx_seminars_payment_methods', $paymentMethodData);
        $this->testingFramework->createRelation('tx_seminars_seminars_payment_methods_mm', $eventUid, $uid);
        $this->subject->setNumberOfPaymentMethods($this->subject->getNumberOfPaymentMethods() + 1);

        return $uid;
    }

    /**
     * Inserts an organizer record into the database and creates a relation to
     * it from the fixture as a organizing partner.
     *
     * @param array $organizerData data of the organizer to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addOrganizingPartnerRelation(
        array $organizerData = []
    ): int {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            $organizerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizing_partners_mm',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfOrganizingPartners(
            $this->subject->getNumberOfOrganizingPartners() + 1,
        );

        return $uid;
    }

    /**
     * Inserts a category record into the database and creates a relation to it
     * from the fixture.
     *
     * @param array $categoryData data of the category to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addCategoryRelation(array $categoryData = []): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            $categoryData,
        );

        $this->testingFramework->createRelation('tx_seminars_seminars_categories_mm', $eventUid, $uid);
        $this->subject->setNumberOfCategories($this->subject->getNumberOfCategories() + 1);

        return $uid;
    }

    /**
     * Inserts a organizer record into the database and creates a relation to it
     * from the fixture.
     *
     * @param array $organizerData data of the organizer to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addOrganizerRelation(array $organizerData = []): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            $organizerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfOrganizers(
            $this->subject->getNumberOfOrganizers() + 1,
        );

        return $uid;
    }

    /**
     * Inserts a speaker record into the database and creates a relation to it
     * from the fixture.
     *
     * @param array<string, int|string> $speakerData data of the speaker to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addSpeakerRelation(array $speakerData = []): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            $speakerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfSpeakers(
            $this->subject->getNumberOfSpeakers() + 1,
        );

        return $uid;
    }

    /**
     * Inserts a speaker record into the database and creates a relation to it
     * from the fixture as partner.
     *
     * @param array $speakerData data of the speaker to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addPartnerRelation(array $speakerData): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            $speakerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm_partners',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfPartners(
            $this->subject->getNumberOfPartners() + 1,
        );

        return $uid;
    }

    /**
     * Inserts a speaker record into the database and creates a relation to it
     * from the fixture as tutor.
     *
     * @param array $speakerData data of the speaker to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addTutorRelation(array $speakerData): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            $speakerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm_tutors',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfTutors(
            $this->subject->getNumberOfTutors() + 1,
        );

        return $uid;
    }

    /**
     * Inserts a speaker record into the database and creates a relation to it
     * from the fixture as leader.
     *
     * @param array $speakerData data of the speaker to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addLeaderRelation(array $speakerData): int
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $uid = $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            $speakerData,
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_speakers_mm_leaders',
            $eventUid,
            $uid,
        );
        $this->subject->setNumberOfLeaders(
            $this->subject->getNumberOfLeaders() + 1,
        );

        return $uid;
    }

    /**
     * Inserts an event type record into the database and creates a relation to
     * it from the fixture.
     *
     * @param array $eventTypeData data of the event type to add, may be empty
     *
     * @return int the UID of the created record, will be > 0
     */
    private function addEventTypeRelation(array $eventTypeData): int
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            $eventTypeData,
        );

        $this->subject->setEventType($uid);

        return $uid;
    }

    // Tests for the utility functions

    /**
     * @test
     */
    public function createPi1CreatesFakeFrontEnd(): void
    {
        $GLOBALS['TSFE'] = null;

        $this->createPi1();

        self::assertNotNull($GLOBALS['TSFE']);
    }

    /**
     * @test
     */
    public function createPi1CreatesPi1Instance(): void
    {
        $this->pi1 = null;

        $this->createPi1();

        self::assertInstanceOf(DefaultController::class, $this->pi1);
    }

    /**
     * @test
     */
    public function addCategoryRelationReturnsUid(): void
    {
        $uid = $this->addCategoryRelation();

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addCategoryRelation(),
            $this->addCategoryRelation(),
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationIncreasesTheNumberOfCategories(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfCategories(),
        );

        $this->addCategoryRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfCategories(),
        );

        $this->addCategoryRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfCategories(),
        );
    }

    /**
     * @test
     */
    public function addCategoryRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_categories_mm');

        self::assertSame(
            0,
            $connection->count('*', 'tx_seminars_seminars_categories_mm', ['uid_local' => $eventUid]),
        );

        $this->addCategoryRelation();
        self::assertSame(
            1,
            $connection->count('*', 'tx_seminars_seminars_categories_mm', ['uid_local' => $eventUid]),
        );

        $this->addCategoryRelation();
        self::assertSame(
            2,
            $connection->count('*', 'tx_seminars_seminars_categories_mm', ['uid_local' => $eventUid]),
        );
    }

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
        self::assertNotSame(
            $this->addTargetGroupRelation(),
            $this->addTargetGroupRelation(),
        );
    }

    /**
     * @test
     */
    public function addTargetGroupRelationIncreasesTheNumberOfTargetGroups(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfTargetGroups(),
        );

        $this->addTargetGroupRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfTargetGroups(),
        );

        $this->addTargetGroupRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfTargetGroups(),
        );
    }

    /**
     * @test
     */
    public function addTargetGroupRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_target_groups_mm');

        self::assertSame(
            0,
            $connection->count('*', 'tx_seminars_seminars_target_groups_mm', ['uid_local' => $eventUid]),
        );

        $this->addTargetGroupRelation();
        self::assertSame(
            1,
            $connection->count('*', 'tx_seminars_seminars_target_groups_mm', ['uid_local' => $eventUid]),
        );

        $this->addTargetGroupRelation();
        self::assertSame(
            2,
            $connection->count('*', 'tx_seminars_seminars_target_groups_mm', ['uid_local' => $eventUid]),
        );
    }

    /**
     * @test
     */
    public function addPaymentMethodRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addPaymentMethodRelation(),
            $this->addPaymentMethodRelation(),
        );
    }

    /**
     * @test
     */
    public function addPaymentMethodRelationIncreasesTheNumberOfPaymentMethods(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfPaymentMethods(),
        );

        $this->addPaymentMethodRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfPaymentMethods(),
        );

        $this->addPaymentMethodRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfPaymentMethods(),
        );
    }

    /**
     * @test
     */
    public function addOrganizingPartnerRelationReturnsUid(): void
    {
        $uid = $this->addOrganizingPartnerRelation();

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addOrganizingPartnerRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addOrganizingPartnerRelation(),
            $this->addOrganizingPartnerRelation(),
        );
    }

    /**
     * @test
     */
    public function addOrganizingPartnerRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_organizing_partners_mm');

        self::assertSame(
            0,
            $connection->count(
                '*',
                'tx_seminars_seminars_organizing_partners_mm',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addOrganizingPartnerRelation();
        self::assertSame(
            1,
            $connection->count(
                '*',
                'tx_seminars_seminars_organizing_partners_mm',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addOrganizingPartnerRelation();
        self::assertSame(
            2,
            $connection->count(
                '*',
                'tx_seminars_seminars_organizing_partners_mm',
                ['uid_local' => $eventUid],
            ),
        );
    }

    /**
     * @test
     */
    public function addOrganizerRelationReturnsUid(): void
    {
        $uid = $this->addOrganizerRelation();

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addOrganizerRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addOrganizerRelation(),
            $this->addOrganizerRelation(),
        );
    }

    /**
     * @test
     */
    public function addOrganizerRelationIncreasesTheNumberOfOrganizers(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfOrganizers(),
        );

        $this->addOrganizerRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfOrganizers(),
        );

        $this->addOrganizerRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfOrganizers(),
        );
    }

    /**
     * @test
     */
    public function addSpeakerRelationReturnsUid(): void
    {
        $uid = $this->addSpeakerRelation();

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addSpeakerRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addSpeakerRelation(),
            $this->addSpeakerRelation(),
        );
    }

    /**
     * @test
     */
    public function addSpeakerRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_speakers_mm');

        self::assertSame(
            0,
            $connection->count('*', 'tx_seminars_seminars_speakers_mm', ['uid_local' => $eventUid]),
        );

        $this->addSpeakerRelation();
        self::assertSame(
            1,
            $connection->count('*', 'tx_seminars_seminars_speakers_mm', ['uid_local' => $eventUid]),
        );

        $this->addSpeakerRelation();
        self::assertSame(
            2,
            $connection->count('*', 'tx_seminars_seminars_speakers_mm', ['uid_local' => $eventUid]),
        );
    }

    /**
     * @test
     */
    public function addPartnerRelationReturnsUid(): void
    {
        $uid = $this->addPartnerRelation([]);

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addPartnerRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addPartnerRelation([]),
            $this->addPartnerRelation([]),
        );
    }

    /**
     * @test
     */
    public function addPartnerRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_speakers_mm_partners');

        self::assertSame(
            0,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_partners',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addPartnerRelation([]);
        self::assertSame(
            1,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_partners',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addPartnerRelation([]);
        self::assertSame(
            2,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_partners',
                ['uid_local' => $eventUid],
            ),
        );
    }

    /**
     * @test
     */
    public function addTutorRelationReturnsUid(): void
    {
        $uid = $this->addTutorRelation([]);

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addTutorRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addTutorRelation([]),
            $this->addTutorRelation([]),
        );
    }

    /**
     * @test
     */
    public function addTutorRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_speakers_mm_tutors');

        self::assertSame(
            0,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_tutors',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addTutorRelation([]);
        self::assertSame(
            1,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_tutors',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addTutorRelation([]);
        self::assertSame(
            2,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_tutors',
                ['uid_local' => $eventUid],
            ),
        );
    }

    /**
     * @test
     */
    public function addLeaderRelationReturnsUid(): void
    {
        $uid = $this->addLeaderRelation([]);

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addLeaderRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addLeaderRelation([]),
            $this->addLeaderRelation([]),
        );
    }

    /**
     * @test
     */
    public function addLeaderRelationCreatesRelations(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);
        $connection = $this->connectionPool->getConnectionForTable('tx_seminars_seminars_speakers_mm_leaders');

        self::assertSame(
            0,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_leaders',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addLeaderRelation([]);
        self::assertSame(
            1,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_leaders',
                ['uid_local' => $eventUid],
            ),
        );

        $this->addLeaderRelation([]);
        self::assertSame(
            2,
            $connection->count(
                '*',
                'tx_seminars_seminars_speakers_mm_leaders',
                ['uid_local' => $eventUid],
            ),
        );
    }

    /**
     * @test
     */
    public function addEventTypeRelationReturnsUid(): void
    {
        $uid = $this->addEventTypeRelation([]);

        self::assertTrue(
            $uid > 0,
        );
    }

    /**
     * @test
     */
    public function addEventTypeRelationCreatesNewUids(): void
    {
        self::assertNotSame(
            $this->addLeaderRelation([]),
            $this->addLeaderRelation([]),
        );
    }

    // Tests for some basic functionality

    /**
     * @test
     */
    public function isOk(): void
    {
        self::assertTrue(
            $this->subject->isOk(),
        );
    }

    // Tests concerning getTitle

    /**
     * @test
     */
    public function getTitleForSingleEventReturnsTitle(): void
    {
        self::assertSame(
            'a test event',
            $this->subject->getTitle(),
        );
    }

    /**
     * @test
     */
    public function getTitleForTopicReturnsTitle(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'a test topic',
            ],
        );
        $topic = new LegacyEvent($topicRecordUid);

        self::assertSame(
            'a test topic',
            $topic->getTitle(),
        );
    }

    /**
     * @test
     */
    public function getTitleForDateReturnsTopicTitle(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'a test topic',
            ],
        );
        $dateRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicRecordUid,
                'title' => 'a test date',
            ],
        );
        $date = new LegacyEvent($dateRecordUid);

        self::assertSame(
            'a test topic',
            $date->getTitle(),
        );
    }

    // Tests regarding the ability to register for an event

    /**
     * @test
     */
    public function canSomebodyRegisterIsTrueForEventWithFutureDate(): void
    {
        $this->subject->setBeginDate($this->now + 3600);
        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithFutureDateReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now + 3600);
        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterIsFalseForPastEvent(): void
    {
        $this->subject->setBeginDate($this->now - 7200);
        $this->subject->setEndDate($this->now - 3600);
        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForPastEventReturnsFalse(): void
    {
        $this->subject->setBeginDate($this->now - 7200);
        $this->subject->setEndDate($this->now - 3600);
        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterIsFalseForCurrentlyRunningEvent(): void
    {
        $this->subject->setBeginDate($this->now - 3600);
        $this->subject->setEndDate($this->now + 3600);
        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForCurrentlyRunningEventReturnsFalse(): void
    {
        $this->subject->setBeginDate($this->now - 3600);
        $this->subject->setEndDate($this->now + 3600);
        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithoutDateWithRegistrationDisabledReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(false);
        $this->subject->setBeginDate(0);

        self::assertFalse($this->subject->canSomebodyRegister());
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithoutDateWithRegistrationEnabledReturnsTrue(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setBeginDate(0);

        self::assertTrue($this->subject->canSomebodyRegister());
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithUnlimitedVacanciesReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now + 3600);
        $this->subject->setUnlimitedVacancies();

        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForCancelledEventReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithoutNeedeRegistrationReturnsFalse(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setNeedsRegistration(false);

        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForFullyBookedEventReturnsFalse(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(10);
        $this->subject->setNumberOfAttendances(10);

        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithRegistrationQueueAndNoRegularVacanciesReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(10);
        $this->subject->setNumberOfAttendances(10);
        $this->subject->setRegistrationQueue(true);

        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithRegistrationQueueAndRegularVacanciesReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(10);
        $this->subject->setNumberOfAttendances(5);
        $this->subject->setRegistrationQueue(true);

        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithRegistrationBeginInFutureReturnsFalse(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setRegistrationBeginDate(
            $this->now + 20,
        );

        self::assertFalse(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithRegistrationBeginInPastReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setRegistrationBeginDate(
            $this->now - 20,
        );

        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterForEventWithoutRegistrationBeginReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setRegistrationBeginDate(0);

        self::assertTrue(
            $this->subject->canSomebodyRegister(),
        );
    }

    // Tests concerning canSomebodyRegisterMessage

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithFutureDateReturnsEmptyString(): void
    {
        $this->subject->setBeginDate($this->now + 3600);

        self::assertSame(
            '',
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForPastEventReturnsSeminarRegistrationClosedMessage(): void
    {
        $this->subject->setBeginDate($this->now - 7200);
        $this->subject->setEndDate($this->now - 3600);

        self::assertSame(
            $this->translate('message_seminarRegistrationIsClosed'),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForPastEventReturnsRegistrationDeadlineOverMessage(): void
    {
        $this->subject->setBeginDate($this->now - 7200);
        $this->subject->setEndDate($this->now - 3600);

        self::assertSame(
            $this->translate('message_seminarRegistrationIsClosed'),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForCurrentlyRunningEventReturnsSeminarRegistrationClosesMessage(): void
    {
        $this->subject->setBeginDate($this->now - 3600);
        $this->subject->setEndDate($this->now + 3600);

        self::assertSame(
            $this->translate('message_seminarRegistrationIsClosed'),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithoutDateReturnsEmptyString(): void
    {
        $this->subject->setBeginDate(0);
        $this->subject->setRegistrationDeadline(0);

        self::assertSame(
            '',
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithUnlimitedVacanciesReturnsEmptyString(): void
    {
        $this->subject->setBeginDate($this->now + 3600);
        $this->subject->setUnlimitedVacancies();

        self::assertSame(
            '',
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForCancelledEventReturnsSeminarCancelledMessage(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertSame(
            $this->translate('message_seminarCancelled'),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithoutNeedeRegistrationReturnsNoRegistrationNecessaryMessage(): void
    {
        $this->subject->setNeedsRegistration(false);

        self::assertSame(
            $this->translate('message_noRegistrationNecessary'),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForFullyBookedEventReturnsNoVacanciesMessage(): void
    {
        $this->subject->setBeginDate($this->now + 3600);
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(10);
        $this->subject->setNumberOfAttendances(10);

        self::assertSame(
            $this->translate('message_noVacancies'),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForFullyBookedEventWithRegistrationQueueReturnsEmptyString(): void
    {
        $this->subject->setBeginDate($this->now + 3600);
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(10);
        $this->subject->setNumberOfAttendances(10);
        $this->subject->setRegistrationQueue(true);

        self::assertSame(
            '',
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithRegistrationBeginInFutureReturnsRegistrationOpensOnMessage(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setRegistrationBeginDate(
            $this->now + 20,
        );

        self::assertSame(
            sprintf(
                $this->translate('message_registrationOpensOn'),
                $this->subject->getRegistrationBegin(),
            ),
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithRegistrationBeginInPastReturnsEmptyString(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setRegistrationBeginDate(
            $this->now - 20,
        );

        self::assertSame(
            '',
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    /**
     * @test
     */
    public function canSomebodyRegisterMessageForEventWithoutRegistrationBeginReturnsEmptyString(): void
    {
        $this->subject->setBeginDate($this->now + 45);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setRegistrationBeginDate(0);

        self::assertSame(
            '',
            $this->subject->canSomebodyRegisterMessage(),
        );
    }

    // Tests regarding the registration.

    /**
     * @test
     */
    public function needsRegistrationForNeedsRegistrationTrueReturnsTrue(): void
    {
        $this->subject->setNeedsRegistration(true);

        self::assertTrue(
            $this->subject->needsRegistration(),
        );
    }

    /**
     * @test
     */
    public function needsRegistrationForNeedsRegistrationFalseReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(false);

        self::assertFalse(
            $this->subject->needsRegistration(),
        );
    }

    // Tests concerning hasUnlimitedVacancies

    /**
     * @test
     */
    public function hasUnlimitedVacanciesForNeedsRegistrationTrueAndMaxAttendeesZeroReturnsTrue(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(0);

        self::assertTrue(
            $this->subject->hasUnlimitedVacancies(),
        );
    }

    /**
     * @test
     */
    public function hasUnlimitedVacanciesForNeedsRegistrationTrueAndMaxAttendeesOneReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(1);

        self::assertFalse(
            $this->subject->hasUnlimitedVacancies(),
        );
    }

    /**
     * @test
     */
    public function hasUnlimitedVacanciesForNeedsRegistrationFalseAndMaxAttendeesZeroReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(false);
        $this->subject->setAttendancesMax(0);

        self::assertFalse(
            $this->subject->hasUnlimitedVacancies(),
        );
    }

    /**
     * @test
     */
    public function hasUnlimitedVacanciesForNeedsRegistrationFalseAndMaxAttendeesOneReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(false);
        $this->subject->setAttendancesMax(1);

        self::assertFalse(
            $this->subject->hasUnlimitedVacancies(),
        );
    }

    // Tests concerning isFull

    /**
     * @test
     */
    public function isFullForUnlimitedVacanciesAndZeroAttendancesReturnsFalse(): void
    {
        $this->subject->setUnlimitedVacancies();
        $this->subject->setNumberOfAttendances(0);

        self::assertFalse(
            $this->subject->isFull(),
        );
    }

    /**
     * @test
     */
    public function isFullForUnlimitedVacanciesAndOneAttendanceReturnsFalse(): void
    {
        $this->subject->setUnlimitedVacancies();
        $this->subject->setNumberOfAttendances(1);

        self::assertFalse(
            $this->subject->isFull(),
        );
    }

    /**
     * @test
     */
    public function isFullForOneVacancyAndNoAttendancesReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(1);
        $this->subject->setNumberOfAttendances(0);

        self::assertFalse(
            $this->subject->isFull(),
        );
    }

    /**
     * @test
     */
    public function isFullForOneVacancyAndOneAttendanceReturnsTrue(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(1);
        $this->subject->setNumberOfAttendances(1);

        self::assertTrue(
            $this->subject->isFull(),
        );
    }

    /**
     * @test
     */
    public function isFullForTwoVacanciesAndOneAttendanceReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(2);
        $this->subject->setNumberOfAttendances(1);

        self::assertFalse(
            $this->subject->isFull(),
        );
    }

    /**
     * @test
     */
    public function isFullForTwoVacanciesAndTwoAttendancesReturnsTrue(): void
    {
        $this->subject->setNeedsRegistration(true);
        $this->subject->setAttendancesMax(2);
        $this->subject->setNumberOfAttendances(2);

        self::assertTrue(
            $this->subject->isFull(),
        );
    }

    // Tests regarding the unregistration and the queue

    /**
     * @test
     */
    public function getUnregistrationDeadlineAsTimestampForNonZero(): void
    {
        $this->subject->setUnregistrationDeadline($this->unregistrationDeadline);

        self::assertSame(
            $this->unregistrationDeadline,
            $this->subject->getUnregistrationDeadlineAsTimestamp(),
        );
    }

    /**
     * @test
     */
    public function getUnregistrationDeadlineAsTimestampForZero(): void
    {
        $this->subject->setUnregistrationDeadline(0);

        self::assertSame(
            0,
            $this->subject->getUnregistrationDeadlineAsTimestamp(),
        );
    }

    /**
     * @test
     */
    public function getUnregistrationDeadlineWithoutTimeForNonZero(): void
    {
        $this->subject->setUnregistrationDeadline(1893488400);

        self::assertSame(
            '2030-01-01 09:00',
            $this->subject->getUnregistrationDeadline(),
        );
    }

    /**
     * @test
     */
    public function getNonUnregistrationDeadlineWithTimeForZero(): void
    {
        $this->subject->setUnregistrationDeadline(1893488400);

        self::assertSame('2030-01-01 09:00', $this->subject->getUnregistrationDeadline());
    }

    /**
     * @test
     */
    public function getUnregistrationDeadlineIsEmptyForZero(): void
    {
        $this->subject->setUnregistrationDeadline(0);

        self::assertSame(
            '',
            $this->subject->getUnregistrationDeadline(),
        );
    }

    /**
     * @test
     */
    public function hasUnregistrationDeadlineIsTrueForNonZeroDeadline(): void
    {
        $this->subject->setUnregistrationDeadline($this->unregistrationDeadline);

        self::assertTrue(
            $this->subject->hasUnregistrationDeadline(),
        );
    }

    /**
     * @test
     */
    public function hasUnregistrationDeadlineIsFalseForZeroDeadline(): void
    {
        $this->subject->setUnregistrationDeadline(0);

        self::assertFalse(
            $this->subject->hasUnregistrationDeadline(),
        );
    }

    // Tests concerning isUnregistrationPossible()

    /**
     * @test
     */
    public function isUnregistrationPossibleWithoutUnregistrationDeadlineReturnsTrue(): void
    {
        $this->subject->setUnregistrationDeadline(0);
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithNoBeginDateAndNoDeadlineReturnsTrue(): void
    {
        $this->subject->setUnregistrationDeadline(0);
        $this->subject->setBeginDate(0);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithFutureEventDeadlineReturnsTrue(): void
    {
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithPastEventDeadlineReturnsFalse(): void
    {
        $this->subject->setUnregistrationDeadline($this->now - Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);

        self::assertFalse($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithoutBeginDateAndWithFutureEventDeadlineReturnsTrue(): void
    {
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate(0);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithoutBeginDateAndWithPastEventDeadlineReturnsFalse(): void
    {
        $this->subject->setUnregistrationDeadline($this->now - Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate(0);

        self::assertFalse($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithUnregistrationDeadlineInFutureReturnsTrue(): void
    {
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithUnregistrationDeadlineInPastReturnsFalse(): void
    {
        $this->subject->setUnregistrationDeadline($this->now - Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_DAY);

        self::assertFalse($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithoutBeginDateAndWithUnregistrationDeadlineInFutureReturnsTrue(): void
    {
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate(0);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithoutBeginDateAndWithUnregistrationDeadlineInPastReturnsFalse(): void
    {
        $this->subject->setBeginDate(0);
        $this->subject->setUnregistrationDeadline($this->now - Time::SECONDS_PER_DAY);

        self::assertFalse($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithPastEventUnregistrationDeadlineReturnsFalse(): void
    {
        $this->subject->setBeginDate($this->now + 2 * Time::SECONDS_PER_DAY);
        $this->subject->setUnregistrationDeadline($this->now - Time::SECONDS_PER_DAY);

        self::assertFalse($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleForNeedsRegistrationFalseReturnsFalse(): void
    {
        $this->subject->setNeedsRegistration(false);
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_DAY);
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);

        self::assertFalse($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleForStartedEventWithUnregistrationDeadlineInFutureReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now - Time::SECONDS_PER_DAY);
        $this->subject->setEndDate($this->now + Time::SECONDS_PER_DAY);
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_WEEK);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleForPastEventWithUnregistrationDeadlineInFutureReturnsTrue(): void
    {
        $this->subject->setBeginDate($this->now - Time::SECONDS_PER_WEEK);
        $this->subject->setEndDate($this->now - Time::SECONDS_PER_DAY);
        $this->subject->setUnregistrationDeadline($this->now + Time::SECONDS_PER_WEEK);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    // Tests concerning getEffectiveUnregistrationDeadline

    /**
     * @test
     */
    public function getEffectiveUnregistrationDeadlineForNoBeginDateAndNoUnregistrationDeadlineReturnsNull(): void
    {
        $this->subject->setBeginDate(0);
        $this->subject->setUnregistrationDeadline(0);

        self::assertNull($this->subject->getEffectiveUnregistrationDeadline());
    }

    /**
     * @test
     */
    public function getEffectiveUnregistrationDeadlineForDeadlineSetAndNoBeginDateReturnsDeadline(): void
    {
        $deadline = $this->now + Time::SECONDS_PER_WEEK;
        $this->subject->setBeginDate(0);
        $this->subject->setUnregistrationDeadline($deadline);

        self::assertSame($deadline, $this->subject->getEffectiveUnregistrationDeadline());
    }

    /**
     * @test
     */
    public function getEffectiveUnregistrationDeadlineForDeadlineSetReturnsDeadline(): void
    {
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);
        $deadline = $this->now + Time::SECONDS_PER_DAY;
        $this->subject->setUnregistrationDeadline($deadline);

        self::assertSame($deadline, $this->subject->getEffectiveUnregistrationDeadline());
    }

    /**
     * @test
     */
    public function getEffectiveUnregistrationDeadlineBeginDateSetAndNoDeadlineReturnsBeginDate(): void
    {
        $start = $this->now + Time::SECONDS_PER_WEEK;
        $this->subject->setBeginDate($start);
        $this->subject->setUnregistrationDeadline(0);

        self::assertSame($start, $this->subject->getEffectiveUnregistrationDeadline());
    }

    // Tests concerning hasRegistrationQueue

    /**
     * @test
     */
    public function hasRegistrationQueueWithQueueReturnsTrue(): void
    {
        $this->subject->setRegistrationQueue(true);

        self::assertTrue(
            $this->subject->hasRegistrationQueue(),
        );
    }

    /**
     * @test
     */
    public function hasRegistrationQueueWithoutQueueReturnsFalse(): void
    {
        $this->subject->setRegistrationQueue(false);

        self::assertFalse(
            $this->subject->hasRegistrationQueue(),
        );
    }

    /**
     * @test
     */
    public function isUnregistrationPossibleWithEmptyQueueByDefaultIsTrue(): void
    {
        $this->subject->setAttendancesMax(1);
        $this->subject->setRegistrationQueue(true);
        $this->subject->setNumberOfAttendances(1);
        $this->subject->setUnregistrationDeadline($this->now + (6 * Time::SECONDS_PER_DAY));
        $this->subject->setBeginDate($this->now + Time::SECONDS_PER_WEEK);

        self::assertTrue($this->subject->isUnregistrationPossible());
    }

    // Tests regarding the target groups

    /**
     * @test
     */
    public function hasTargetGroupsIsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasTargetGroups(),
        );
    }

    /**
     * @test
     */
    public function hasTargetGroups(): void
    {
        $this->addTargetGroupRelation();

        self::assertTrue(
            $this->subject->hasTargetGroups(),
        );
    }

    /**
     * @test
     */
    public function getTargetGroupNamesWithNoTargetGroup(): void
    {
        self::assertSame(
            '',
            $this->subject->getTargetGroupNames(),
        );
    }

    /**
     * @test
     */
    public function getTargetGroupNamesWithSingleTargetGroup(): void
    {
        $title = 'TEST target group 1';
        $this->addTargetGroupRelation(['title' => $title]);

        self::assertSame(
            $title,
            $this->subject->getTargetGroupNames(),
        );
    }

    /**
     * @test
     */
    public function getTargetGroupNamesWithMultipleTargetGroups(): void
    {
        $titleTargetGroup1 = 'TEST target group 1';
        $this->addTargetGroupRelation(['title' => $titleTargetGroup1]);

        $titleTargetGroup2 = 'TEST target group 2';
        $this->addTargetGroupRelation(['title' => $titleTargetGroup2]);

        self::assertSame(
            $titleTargetGroup1 . ', ' . $titleTargetGroup2,
            $this->subject->getTargetGroupNames(),
        );
    }

    // Tests regarding the payment methods

    /**
     * @test
     */
    public function hasPaymentMethodsReturnsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasPaymentMethods(),
        );
    }

    /**
     * @test
     */
    public function canHaveOnePaymentMethod(): void
    {
        $this->addPaymentMethodRelation();

        self::assertTrue(
            $this->subject->hasPaymentMethods(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainWithNoPaymentMethodReturnsAnEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getPaymentMethodsPlain(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainWithSinglePaymentMethodReturnsASinglePaymentMethod(): void
    {
        $title = 'Test title';
        $this->addPaymentMethodRelation(['title' => $title]);

        self::assertStringContainsString(
            $title,
            $this->subject->getPaymentMethodsPlain(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainWithMultiplePaymentMethodsReturnsMultiplePaymentMethods(): void
    {
        $firstTitle = 'Payment Method 1';
        $secondTitle = 'Payment Method 2';
        $this->addPaymentMethodRelation(['title' => $firstTitle]);
        $this->addPaymentMethodRelation(['title' => $secondTitle]);

        self::assertStringContainsString(
            $firstTitle,
            $this->subject->getPaymentMethodsPlain(),
        );
        self::assertStringContainsString(
            $secondTitle,
            $this->subject->getPaymentMethodsPlain(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsWithoutPaymentMethodsReturnsAnEmptyArray(): void
    {
        self::assertSame(
            [],
            $this->subject->getPaymentMethods(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsWithOnePaymentMethodReturnsOnePaymentMethod(): void
    {
        $this->addPaymentMethodRelation(['title' => 'Payment Method']);

        self::assertSame(
            ['Payment Method'],
            $this->subject->getPaymentMethods(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsWithTwoPaymentMethodsReturnsTwoPaymentMethods(): void
    {
        $this->addPaymentMethodRelation(['title' => 'Payment Method 1']);
        $this->addPaymentMethodRelation(['title' => 'Payment Method 2']);

        self::assertSame(
            ['Payment Method 1', 'Payment Method 2'],
            $this->subject->getPaymentMethods(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsWithTwoPaymentMethodsReturnsTwoPaymentMethodsSorted(): void
    {
        $this->addPaymentMethodRelation(['title' => 'Payment Method 2']);
        $this->addPaymentMethodRelation(['title' => 'Payment Method 1']);

        self::assertSame(
            ['Payment Method 2', 'Payment Method 1'],
            $this->subject->getPaymentMethods(),
        );
    }

    // Tests concerning getPaymentMethodsPlainShort

    /**
     * @test
     */
    public function getPaymentMethodsPlainShortWithNoPaymentMethodReturnsAnEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getPaymentMethodsPlainShort(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainShortWithSinglePaymentMethodReturnsASinglePaymentMethod(): void
    {
        $title = 'Test title';
        $this->addPaymentMethodRelation(['title' => $title]);

        self::assertStringContainsString(
            $title,
            $this->subject->getPaymentMethodsPlainShort(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainShortWithMultiplePaymentMethodsReturnsMultiplePaymentMethods(): void
    {
        $this->addPaymentMethodRelation(['title' => 'Payment Method 1']);
        $this->addPaymentMethodRelation(['title' => 'Payment Method 2']);

        self::assertStringContainsString(
            'Payment Method 1',
            $this->subject->getPaymentMethodsPlainShort(),
        );
        self::assertStringContainsString(
            'Payment Method 2',
            $this->subject->getPaymentMethodsPlainShort(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainShortSeparatesMultiplePaymentMethodsWithLineFeeds(): void
    {
        $this->addPaymentMethodRelation(['title' => 'Payment Method 1']);
        $this->addPaymentMethodRelation(['title' => 'Payment Method 2']);

        self::assertStringContainsString(
            "Payment Method 1\nPayment Method 2",
            $this->subject->getPaymentMethodsPlainShort(),
        );
    }

    /**
     * @test
     */
    public function getPaymentMethodsPlainShortDoesNotSeparateMultiplePaymentMethodsWithCarriageReturnsAndLineFeeds(): void
    {
        $this->addPaymentMethodRelation(['title' => 'Payment Method 1']);
        $this->addPaymentMethodRelation(['title' => 'Payment Method 2']);

        self::assertStringNotContainsString(
            "Payment Method 1\r\nPayment Method 2",
            $this->subject->getPaymentMethodsPlainShort(),
        );
    }

    // Tests concerning getSinglePaymentMethodPlain

    /**
     * @test
     */
    public function getSinglePaymentMethodPlainWithInvalidPaymentMethodUidReturnsAnEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getSinglePaymentMethodPlain(0),
        );
    }

    /**
     * @test
     */
    public function getSinglePaymentMethodPlainWithValidPaymentMethodUidWithoutDescriptionReturnsTitle(): void
    {
        $title = 'Test payment method';
        $uid = $this->addPaymentMethodRelation(['title' => $title]);

        self::assertSame(
            $title . "\n\n",
            $this->subject->getSinglePaymentMethodPlain($uid),
        );
    }

    /**
     * @test
     */
    public function getSinglePaymentMethodPlainWithValidPaymentMethodUidWithDescriptionReturnsTitleAndDescription(): void
    {
        $title = 'Test payment method';
        $description = 'some description';
        $uid = $this->addPaymentMethodRelation(['title' => $title, 'description' => $description]);

        self::assertSame(
            $title . ': ' . $description . "\n\n",
            $this->subject->getSinglePaymentMethodPlain($uid),
        );
    }

    /**
     * @test
     */
    public function getSinglePaymentMethodPlainWithNonExistentPaymentMethodUidReturnsAnEmptyString(): void
    {
        $uid = $this->addPaymentMethodRelation();

        self::assertSame(
            '',
            $this->subject->getSinglePaymentMethodPlain($uid + 1),
        );
    }

    /**
     * @test
     */
    public function getSinglePaymentMethodShortWithInvalidPaymentMethodUidReturnsAnEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getSinglePaymentMethodShort(0),
        );
    }

    /**
     * @test
     */
    public function getSinglePaymentMethodShortWithValidPaymentMethodUidReturnsTheTitleOfThePaymentMethod(): void
    {
        $title = 'Test payment method';
        $uid = $this->addPaymentMethodRelation(['title' => $title]);

        self::assertStringContainsString(
            $title,
            $this->subject->getSinglePaymentMethodShort($uid),
        );
    }

    /**
     * @test
     */
    public function getSinglePaymentMethodShortWithNonExistentPaymentMethodUidReturnsAnEmptyString(): void
    {
        $uid = $this->addPaymentMethodRelation();

        self::assertSame(
            '',
            $this->subject->getSinglePaymentMethodShort($uid + 1),
        );
    }

    // Tests regarding the event type

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function setEventTypeIsAllowedWithZero(): void
    {
        $this->subject->setEventType(0);
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function setEventTypeIsAllowedWithPositiveInteger(): void
    {
        $this->subject->setEventType(1);
    }

    /**
     * @test
     */
    public function hasEventTypeInitiallyReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasEventType(),
        );
    }

    /**
     * @test
     */
    public function hasEventTypeReturnsTrueIfSingleEventHasNonZeroEventType(): void
    {
        $this->subject->setEventType(
            $this->testingFramework->createRecord('tx_seminars_event_types'),
        );

        self::assertTrue(
            $this->subject->hasEventType(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeReturnsEmptyStringForSingleEventWithoutType(): void
    {
        self::assertSame(
            '',
            $this->subject->getEventType(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeReturnsTitleOfRelatedEventTypeForSingleEvent(): void
    {
        $this->subject->setEventType(
            $this->testingFramework->createRecord(
                'tx_seminars_event_types',
                ['title' => 'foo type'],
            ),
        );

        self::assertSame(
            'foo type',
            $this->subject->getEventType(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeForDateRecordReturnsTitleOfEventTypeFromTopicRecord(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'event_type' => $this->testingFramework->createRecord(
                    'tx_seminars_event_types',
                    ['title' => 'foo type'],
                ),
            ],
        );
        $dateRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicRecordUid,
            ],
        );
        $seminar = new LegacyEvent($dateRecordUid);

        self::assertSame(
            'foo type',
            $seminar->getEventType(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeForTopicRecordReturnsTitleOfRelatedEventType(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'event_type' => $this->testingFramework->createRecord(
                    'tx_seminars_event_types',
                    ['title' => 'foo type'],
                ),
            ],
        );
        $seminar = new LegacyEvent($topicRecordUid);

        self::assertSame(
            'foo type',
            $seminar->getEventType(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeUidReturnsUidFromTopicRecord(): void
    {
        // This test comes from bug #1515.
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'event_type' => 99999,
            ],
        );
        $dateRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicRecordUid,
                'event_type' => 199999,
            ],
        );
        $seminar = new LegacyEvent($dateRecordUid);

        self::assertSame(
            99999,
            $seminar->getEventTypeUid(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeUidInitiallyReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getEventTypeUid(),
        );
    }

    /**
     * @test
     */
    public function getEventTypeUidWithEventTypeReturnsEventTypeUid(): void
    {
        $eventTypeUid = $this->addEventTypeRelation([]);
        self::assertSame(
            $eventTypeUid,
            $this->subject->getEventTypeUid(),
        );
    }

    // Tests regarding the organizing partners

    /**
     * @test
     */
    public function hasOrganizingPartnersReturnsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasOrganizingPartners(),
        );
    }

    /**
     * @test
     */
    public function canHaveOneOrganizingPartner(): void
    {
        $this->addOrganizingPartnerRelation();

        self::assertTrue(
            $this->subject->hasOrganizingPartners(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfOrganizingPartnersWithNoOrganizingPartnerReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfOrganizingPartners(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfOrganizingPartnersWithSingleOrganizingPartnerReturnsOne(): void
    {
        $this->addOrganizingPartnerRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfOrganizingPartners(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfOrganizingPartnersWithMultipleOrganizingPartnersReturnsTwo(): void
    {
        $this->addOrganizingPartnerRelation();
        $this->addOrganizingPartnerRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfOrganizingPartners(),
        );
    }

    // Tests regarding the categories

    /**
     * @test
     */
    public function initiallyHasNoCategories(): void
    {
        self::assertFalse(
            $this->subject->hasCategories(),
        );
        self::assertSame(
            0,
            $this->subject->getNumberOfCategories(),
        );
        self::assertSame(
            [],
            $this->subject->getCategories(),
        );
    }

    /**
     * @test
     */
    public function getCategoriesCanReturnOneCategory(): void
    {
        $categoryUid = $this->addCategoryRelation(['title' => 'Test']);

        self::assertTrue(
            $this->subject->hasCategories(),
        );
        self::assertSame(
            1,
            $this->subject->getNumberOfCategories(),
        );
        self::assertSame(
            [$categoryUid => ['title' => 'Test']],
            $this->subject->getCategories(),
        );
    }

    /**
     * @test
     */
    public function canHaveTwoCategories(): void
    {
        $categoryUid1 = $this->addCategoryRelation(['title' => 'Test 1']);
        $categoryUid2 = $this->addCategoryRelation(['title' => 'Test 2']);

        self::assertTrue(
            $this->subject->hasCategories(),
        );
        self::assertSame(
            2,
            $this->subject->getNumberOfCategories(),
        );

        $categories = $this->subject->getCategories();

        self::assertCount(
            2,
            $categories,
        );
        self::assertSame(
            'Test 1',
            $categories[$categoryUid1]['title'],
        );
        self::assertSame(
            'Test 2',
            $categories[$categoryUid2]['title'],
        );
    }

    /**
     * @test
     */
    public function getCategoriesReturnsCategoriesOrderedBySorting(): void
    {
        $categoryUid1 = $this->addCategoryRelation(['title' => 'Test 1']);
        $categoryUid2 = $this->addCategoryRelation(['title' => 'Test 2']);

        self::assertTrue(
            $this->subject->hasCategories(),
        );

        self::assertSame(
            [
                $categoryUid1 => ['title' => 'Test 1'],
                $categoryUid2 => ['title' => 'Test 2'],
            ],
            $this->subject->getCategories(),
        );
    }

    // Tests regarding the time slots

    /**
     * @test
     */
    public function getTimeSlotsAsArrayWithMarkersReturnsArraySortedByDate(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $eventUid,
                'begin_date' => 200,
                'room' => 'Room1',
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_timeslots',
            [
                'seminar' => $eventUid,
                'begin_date' => 100,
                'room' => 'Room2',
            ],
        );

        $timeSlotsWithMarkers = $this->subject->getTimeSlotsAsArrayWithMarkers();
        self::assertSame(
            $timeSlotsWithMarkers[0]['room'],
            'Room2',
        );
        self::assertSame(
            $timeSlotsWithMarkers[1]['room'],
            'Room1',
        );
    }

    // Tests regarding the organizers

    /**
     * @test
     */
    public function hasOrganizersReturnsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasOrganizers(),
        );
    }

    /**
     * @test
     */
    public function canHaveOneOrganizer(): void
    {
        $this->addOrganizerRelation();

        self::assertTrue(
            $this->subject->hasOrganizers(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfOrganizersWithNoOrganizerReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfOrganizers(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfOrganizersWithSingleOrganizerReturnsOne(): void
    {
        $this->addOrganizerRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfOrganizers(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfOrganizersWithMultipleOrganizersReturnsTwo(): void
    {
        $this->addOrganizerRelation();
        $this->addOrganizerRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfOrganizers(),
        );
    }

    // Tests concerning getOrganizers

    /**
     * @test
     */
    public function getOrganizersWithNoOrganizersReturnsEmptyString(): void
    {
        $this->createPi1();

        self::assertSame(
            '',
            $this->subject->getOrganizers(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersForOneOrganizerReturnsOrganizerName(): void
    {
        $this->createPi1();
        $this->addOrganizerRelation(['title' => 'foo']);

        self::assertStringContainsString(
            'foo',
            $this->subject->getOrganizers(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersForOneOrganizerWithHomepageReturnsOrganizerLinkedToOrganizersHomepage(): void
    {
        $this->createPi1();
        $this->addOrganizerRelation(
            [
                'title' => 'foo',
                'homepage' => 'www.bar.com',
            ],
        );

        $result = $this->subject->getOrganizers();

        self::assertStringContainsString('<a href="', $result);
        self::assertStringContainsString('://www.bar.com"', $result);
    }

    /**
     * @test
     */
    public function getOrganizersWithTwoOrganizersReturnsBothOrganizerNames(): void
    {
        $this->createPi1();
        $this->addOrganizerRelation(['title' => 'foo']);
        $this->addOrganizerRelation(['title' => 'bar']);

        $organizers = $this->subject->getOrganizers();

        self::assertStringContainsString(
            'foo',
            $organizers,
        );
        self::assertStringContainsString(
            'bar',
            $organizers,
        );
    }

    // Tests concerning getOrganizersRaw

    /**
     * @test
     */
    public function getOrganizersRawWithNoOrganizersReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getOrganizersRaw(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersRawWithSingleOrganizerWithoutHomepageReturnsSingleOrganizer(): void
    {
        $organizer = [
            'title' => 'test organizer 1',
            'homepage' => '',
        ];
        $this->addOrganizerRelation($organizer);
        self::assertSame(
            $organizer['title'],
            $this->subject->getOrganizersRaw(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersRawWithSingleOrganizerWithHomepageReturnsSingleOrganizerWithHomepage(): void
    {
        $organizer = [
            'title' => 'test organizer 1',
            'homepage' => 'test homepage 1',
        ];
        $this->addOrganizerRelation($organizer);
        self::assertSame(
            $organizer['title'] . ', ' . $organizer['homepage'],
            $this->subject->getOrganizersRaw(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersRawForTwoOrganizersWithoutHomepageReturnsTwoOrganizers(): void
    {
        $this->addOrganizerRelation(
            ['title' => 'test organizer 1', 'homepage' => ''],
        );
        $this->addOrganizerRelation(
            ['title' => 'test organizer 2', 'homepage' => ''],
        );

        self::assertStringContainsString(
            'test organizer 1',
            $this->subject->getOrganizersRaw(),
        );
        self::assertStringContainsString(
            'test organizer 2',
            $this->subject->getOrganizersRaw(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersRawForTwoOrganizersWithHomepageReturnsTwoOrganizersWithHomepage(): void
    {
        $this->addOrganizerRelation(
            [
                'title' => 'test organizer 1',
                'homepage' => 'test homepage 1',
            ],
        );
        $this->addOrganizerRelation(
            [
                'title' => 'test organizer 2',
                'homepage' => 'test homepage 2',
            ],
        );

        self::assertStringContainsString(
            'test homepage 1',
            $this->subject->getOrganizersRaw(),
        );
        self::assertStringContainsString(
            'test homepage 2',
            $this->subject->getOrganizersRaw(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersRawSeparatesMultipleOrganizersWithLineFeeds(): void
    {
        $this->addOrganizerRelation(['title' => 'test organizer 1']);
        $this->addOrganizerRelation(['title' => 'test organizer 2']);

        self::assertStringContainsString(
            "test organizer 1\ntest organizer 2",
            $this->subject->getOrganizersRaw(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersRawDoesNotSeparateMultipleOrganizersWithCarriageReturnsAndLineFeeds(): void
    {
        $this->addOrganizerRelation(['title' => 'test organizer 1']);
        $this->addOrganizerRelation(['title' => 'test organizer 2']);

        self::assertStringNotContainsString(
            "test organizer 1\r\ntest organizer 2",
            $this->subject->getOrganizersRaw(),
        );
    }

    // Tests concerning getOrganizersNameAndEmail

    /**
     * @test
     */
    public function getOrganizersNameAndEmailWithNoOrganizersReturnsEmptyString(): void
    {
        self::assertSame(
            [],
            $this->subject->getOrganizersNameAndEmail(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersNameAndEmailWithSingleOrganizerReturnsSingleOrganizer(): void
    {
        $organizer = [
            'title' => 'test organizer',
            'email' => 'test@organizer.org',
        ];
        $this->addOrganizerRelation($organizer);
        self::assertSame(
            ['"' . $organizer['title'] . '" <' . $organizer['email'] . '>'],
            $this->subject->getOrganizersNameAndEmail(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersNameAndEmailWithMultipleOrganizersReturnsTwoOrganizers(): void
    {
        $firstOrganizer = [
            'title' => 'test organizer 1',
            'email' => 'test1@organizer.org',
        ];
        $secondOrganizer = [
            'title' => 'test organizer 2',
            'email' => 'test2@organizer.org',
        ];
        $this->addOrganizerRelation($firstOrganizer);
        $this->addOrganizerRelation($secondOrganizer);
        self::assertSame(
            [
                '"' . $firstOrganizer['title'] . '" <' . $firstOrganizer['email'] . '>',
                '"' . $secondOrganizer['title'] . '" <' . $secondOrganizer['email'] . '>',
            ],
            $this->subject->getOrganizersNameAndEmail(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersEmailWithNoOrganizersReturnsEmptyString(): void
    {
        self::assertSame(
            [],
            $this->subject->getOrganizersEmail(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersEmailWithSingleOrganizerReturnsSingleOrganizer(): void
    {
        $organizer = ['email' => 'test@organizer.org'];
        $this->addOrganizerRelation($organizer);
        self::assertSame(
            [$organizer['email']],
            $this->subject->getOrganizersEmail(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersEmailWithMultipleOrganizersReturnsTwoOrganizers(): void
    {
        $firstOrganizer = ['email' => 'test1@organizer.org'];
        $secondOrganizer = ['email' => 'test2@organizer.org'];
        $this->addOrganizerRelation($firstOrganizer);
        $this->addOrganizerRelation($secondOrganizer);
        self::assertSame(
            [$firstOrganizer['email'], $secondOrganizer['email']],
            $this->subject->getOrganizersEmail(),
        );
    }

    // Tests concerning getOrganizersFooter

    /**
     * @test
     */
    public function getOrganizersFootersWithNoOrganizersReturnsEmptyArray(): void
    {
        self::assertSame(
            [],
            $this->subject->getOrganizersFooter(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersFootersWithSingleOrganizerReturnsSingleOrganizerFooter(): void
    {
        $organizer = ['email_footer' => 'test email footer'];
        $this->addOrganizerRelation($organizer);
        self::assertSame(
            [$organizer['email_footer']],
            $this->subject->getOrganizersFooter(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersFootersWithMultipleOrganizersReturnsTwoOrganizerFooters(): void
    {
        $firstOrganizer = ['email_footer' => 'test email footer'];
        $secondOrganizer = ['email_footer' => 'test email footer'];
        $this->addOrganizerRelation($firstOrganizer);
        $this->addOrganizerRelation($secondOrganizer);
        self::assertSame(
            [
                $firstOrganizer['email_footer'],
                $secondOrganizer['email_footer'],
            ],
            $this->subject->getOrganizersFooter(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersFootersWithSingleOrganizerWithoutEmailFooterReturnsEmptyArray(): void
    {
        $this->addOrganizerRelation();

        self::assertSame(
            [],
            $this->subject->getOrganizersFooter(),
        );
    }

    /**
     * @test
     */
    public function getOrganizersFootersWithTwoOrganizersOneWithFooterOneWithoutrReturnsOnlyTheNonEmptyFooter(): void
    {
        $secondOrganizer = ['email_footer' => 'test email footer'];
        $this->addOrganizerRelation();
        $this->addOrganizerRelation($secondOrganizer);
        self::assertSame(
            [$secondOrganizer['email_footer']],
            $this->subject->getOrganizersFooter(),
        );
    }

    // Tests concerning getFirstOrganizer

    /**
     * @test
     */
    public function getFirstOrganizerWithNoOrganizersThrowsException(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1724278146);
        $this->expectExceptionMessage('This event does not have any organizers.');

        $this->subject->getFirstOrganizer();
    }

    /**
     * @test
     */
    public function getFirstOrganizerForOneOrganizerReturnsThatOrganizer(): void
    {
        $organizerUid = $this->addOrganizerRelation();

        self::assertSame(
            $organizerUid,
            $this->subject->getFirstOrganizer()->getUid(),
        );
    }

    /**
     * @test
     */
    public function getFirstOrganizerForTwoOrganizerReturnsFirstOrganizer(): void
    {
        $firstOrganizerUid = $this->addOrganizerRelation();
        $this->addOrganizerRelation();

        self::assertSame(
            $firstOrganizerUid,
            $this->subject->getFirstOrganizer()->getUid(),
        );
    }

    // Tests regarding getOrganizerBag().

    /**
     * @test
     */
    public function getOrganizerBagWithoutOrganizersReturnsOrganizerBag(): void
    {
        $bag = $this->subject->getOrganizerBag();

        self::assertInstanceOf(OrganizerBag::class, $bag);
    }

    /**
     * @test
     */
    public function getOrganizerBagWithOrganizerReturnsOrganizerBag(): void
    {
        $this->addOrganizerRelation();

        self::assertInstanceOf(OrganizerBag::class, $this->subject->getOrganizerBag());
    }

    // Tests regarding the speakers

    /**
     * @test
     */
    public function getNumberOfSpeakersWithNoSpeakerReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfSpeakers(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfSpeakersWithSingleSpeakerReturnsOne(): void
    {
        $this->addSpeakerRelation();
        self::assertSame(
            1,
            $this->subject->getNumberOfSpeakers(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfSpeakersWithMultipleSpeakersReturnsTwo(): void
    {
        $this->addSpeakerRelation();
        $this->addSpeakerRelation();
        self::assertSame(
            2,
            $this->subject->getNumberOfSpeakers(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfPartnersWithNoPartnerReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfPartners(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfPartnersWithSinglePartnerReturnsOne(): void
    {
        $this->addPartnerRelation([]);
        self::assertSame(
            1,
            $this->subject->getNumberOfPartners(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfPartnersWithMultiplePartnersReturnsTwo(): void
    {
        $this->addPartnerRelation([]);
        $this->addPartnerRelation([]);
        self::assertSame(
            2,
            $this->subject->getNumberOfPartners(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfTutorsWithNoTutorReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfTutors(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfTutorsWithSingleTutorReturnsOne(): void
    {
        $this->addTutorRelation([]);
        self::assertSame(
            1,
            $this->subject->getNumberOfTutors(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfTutorsWithMultipleTutorsReturnsTwo(): void
    {
        $this->addTutorRelation([]);
        $this->addTutorRelation([]);
        self::assertSame(
            2,
            $this->subject->getNumberOfTutors(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfLeadersWithNoLeaderReturnsZero(): void
    {
        self::assertSame(
            0,
            $this->subject->getNumberOfLeaders(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfLeadersWithSingleLeaderReturnsOne(): void
    {
        $this->addLeaderRelation([]);
        self::assertSame(
            1,
            $this->subject->getNumberOfLeaders(),
        );
    }

    /**
     * @test
     */
    public function getNumberOfLeadersWithMultipleLeadersReturnsTwo(): void
    {
        $this->addLeaderRelation([]);
        $this->addLeaderRelation([]);
        self::assertSame(
            2,
            $this->subject->getNumberOfLeaders(),
        );
    }

    /**
     * @test
     */
    public function hasSpeakersOfTypeIsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasSpeakersOfType(),
        );
        self::assertFalse(
            $this->subject->hasSpeakersOfType('partners'),
        );
        self::assertFalse(
            $this->subject->hasSpeakersOfType('tutors'),
        );
        self::assertFalse(
            $this->subject->hasSpeakersOfType('leaders'),
        );
    }

    /**
     * @test
     */
    public function hasSpeakersOfTypeWithSingleSpeakerOfTypeReturnsTrue(): void
    {
        $this->addSpeakerRelation();
        self::assertTrue(
            $this->subject->hasSpeakersOfType(),
        );

        $this->addPartnerRelation([]);
        self::assertTrue(
            $this->subject->hasSpeakersOfType('partners'),
        );

        $this->addTutorRelation([]);
        self::assertTrue(
            $this->subject->hasSpeakersOfType('tutors'),
        );

        $this->addLeaderRelation([]);
        self::assertTrue(
            $this->subject->hasSpeakersOfType('leaders'),
        );
    }

    /**
     * @test
     */
    public function hasSpeakersIsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasSpeakers(),
        );
    }

    /**
     * @test
     */
    public function canHaveOneSpeaker(): void
    {
        $this->addSpeakerRelation();
        self::assertTrue(
            $this->subject->hasSpeakers(),
        );
    }

    /**
     * @test
     */
    public function hasPartnersIsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasPartners(),
        );
    }

    /**
     * @test
     */
    public function canHaveOnePartner(): void
    {
        $this->addPartnerRelation([]);
        self::assertTrue(
            $this->subject->hasPartners(),
        );
    }

    /**
     * @test
     */
    public function hasTutorsIsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasTutors(),
        );
    }

    /**
     * @test
     */
    public function canHaveOneTutor(): void
    {
        $this->addTutorRelation([]);
        self::assertTrue(
            $this->subject->hasTutors(),
        );
    }

    /**
     * @test
     */
    public function hasLeadersIsInitiallyFalse(): void
    {
        self::assertFalse(
            $this->subject->hasLeaders(),
        );
    }

    /**
     * @test
     */
    public function canHaveOneLeader(): void
    {
        $this->addLeaderRelation([]);
        self::assertTrue(
            $this->subject->hasLeaders(),
        );
    }

    // Tests concerning getSpeakersWithDescriptionRaw

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawWithNoSpeakersReturnsAnEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawReturnsTitleOfSpeaker(): void
    {
        $this->addSpeakerRelation(['title' => 'test speaker']);

        self::assertStringContainsString(
            'test speaker',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForSpeakerWithOrganizationReturnsSpeakerWithOrganization(): void
    {
        $this->addSpeakerRelation(['organization' => 'test organization']);

        self::assertStringContainsString(
            'test organization',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForSpeakerWithHomepageReturnsSpeakerWithHomepage(): void
    {
        $this->addSpeakerRelation(['homepage' => 'test homepage']);

        self::assertStringContainsString(
            'test homepage',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForSpeakerWithOrganizationAndHomepageReturnsSpeakerWithOrganizationAndHomepage(): void
    {
        $this->addSpeakerRelation(
            [
                'organization' => 'test organization',
                'homepage' => 'test homepage',
            ],
        );

        self::assertMatchesRegularExpression(
            '/test organization.*test homepage/',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForSpeakerWithDescriptionReturnsSpeakerWithDescription(): void
    {
        $this->addSpeakerRelation(['description' => 'test description']);

        self::assertStringContainsString(
            'test description',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForSpeakerWithOrganizationAndDescriptionReturnsOrganizationAndDescription(): void
    {
        $this->addSpeakerRelation(
            [
                'organization' => 'foo',
                'description' => 'bar',
            ],
        );
        self::assertMatchesRegularExpression(
            '/foo.*bar/s',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForSpeakerWithHomepageAndDescriptionReturnsHomepageAndDescription(): void
    {
        $this->addSpeakerRelation(
            [
                'homepage' => 'test homepage',
                'description' => 'test description',
            ],
        );

        self::assertMatchesRegularExpression(
            '/test homepage.*test description/s',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForTwoSpeakersReturnsTwoSpeakers(): void
    {
        $this->addSpeakerRelation(['title' => 'test speaker 1']);
        $this->addSpeakerRelation(['title' => 'test speaker 2']);

        self::assertStringContainsString(
            'test speaker 1',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
        self::assertStringContainsString(
            'test speaker 2',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawForTwoSpeakersWithOrganizationReturnsTwoSpeakersWithOrganization(): void
    {
        $this->addSpeakerRelation(
            ['organization' => 'test organization 1'],
        );
        $this->addSpeakerRelation(
            ['organization' => 'test organization 2'],
        );

        self::assertStringContainsString(
            'test organization 1',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
        self::assertStringContainsString(
            'test organization 2',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawOnlyReturnsSpeakersOfGivenType(): void
    {
        $this->addSpeakerRelation(['title' => 'test speaker']);
        $this->addPartnerRelation(['title' => 'test partner']);

        self::assertStringNotContainsString(
            'test partner',
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawCanReturnSpeakersOfTypePartner(): void
    {
        $this->addPartnerRelation(['title' => 'test partner']);

        self::assertStringContainsString(
            'test partner',
            $this->subject->getSpeakersWithDescriptionRaw('partners'),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawCanReturnSpeakersOfTypeLeaders(): void
    {
        $this->addLeaderRelation(['title' => 'test leader']);

        self::assertStringContainsString(
            'test leader',
            $this->subject->getSpeakersWithDescriptionRaw('leaders'),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawCanReturnSpeakersOfTypeTutors(): void
    {
        $this->addTutorRelation(['title' => 'test tutor']);

        self::assertStringContainsString(
            'test tutor',
            $this->subject->getSpeakersWithDescriptionRaw('tutors'),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawSeparatesMultipleSpeakersWithLineFeeds(): void
    {
        $this->addSpeakerRelation(['title' => 'foo']);
        $this->addSpeakerRelation(['title' => 'bar']);

        self::assertStringContainsString(
            "foo\nbar",
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawDoesNotSeparateMultipleSpeakersWithCarriageReturnsAndLineFeeds(): void
    {
        $this->addSpeakerRelation(['title' => 'foo']);
        $this->addSpeakerRelation(['title' => 'bar']);

        self::assertStringNotContainsString(
            "foo\r\nbar",
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawDoesNotSeparateSpeakersDescriptionAndTitleWithCarriageReturnsAndLineFeeds(): void
    {
        $this->addSpeakerRelation(
            [
                'title' => 'foo',
                'description' => 'bar',
            ],
        );

        self::assertDoesNotMatchRegularExpression(
            "/foo\r\nbar/",
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersWithDescriptionRawSeparatesSpeakersDescriptionAndTitleWithLineFeeds(): void
    {
        $this->addSpeakerRelation(
            [
                'title' => 'foo',
                'description' => 'bar',
            ],
        );

        self::assertMatchesRegularExpression(
            "/foo\nbar/",
            $this->subject->getSpeakersWithDescriptionRaw(),
        );
    }

    // Tests concerning getSpeakersShort

    /**
     * @test
     */
    public function getSpeakersShortWithNoSpeakersReturnsAnEmptyString(): void
    {
        $this->createPi1();

        self::assertSame(
            '',
            $this->subject->getSpeakersShort(),
        );
        self::assertSame(
            '',
            $this->subject->getSpeakersShort('partners'),
        );
        self::assertSame(
            '',
            $this->subject->getSpeakersShort('tutors'),
        );
        self::assertSame(
            '',
            $this->subject->getSpeakersShort('leaders'),
        );
    }

    /**
     * @test
     */
    public function getSpeakersShortWithSingleSpeakersReturnsSingleSpeaker(): void
    {
        $this->createPi1();
        $speaker = ['title' => 'test speaker'];

        $this->addSpeakerRelation($speaker);
        self::assertSame(
            $speaker['title'],
            $this->subject->getSpeakersShort(),
        );

        $this->addPartnerRelation($speaker);
        self::assertSame(
            $speaker['title'],
            $this->subject->getSpeakersShort('partners'),
        );

        $this->addTutorRelation($speaker);
        self::assertSame(
            $speaker['title'],
            $this->subject->getSpeakersShort('tutors'),
        );

        $this->addLeaderRelation($speaker);
        self::assertSame(
            $speaker['title'],
            $this->subject->getSpeakersShort('leaders'),
        );
    }

    /**
     * @test
     */
    public function getSpeakersShortWithMultipleSpeakersReturnsTwoSpeakers(): void
    {
        $firstSpeaker = ['title' => 'test speaker 1'];
        $secondSpeaker = ['title' => 'test speaker 2'];

        $this->addSpeakerRelation($firstSpeaker);
        $this->addSpeakerRelation($secondSpeaker);
        $this->createPi1();
        self::assertSame(
            $firstSpeaker['title'] . ', ' . $secondSpeaker['title'],
            $this->subject->getSpeakersShort(),
        );

        $this->addPartnerRelation($firstSpeaker);
        $this->addPartnerRelation($secondSpeaker);
        self::assertSame(
            $firstSpeaker['title'] . ', ' . $secondSpeaker['title'],
            $this->subject->getSpeakersShort('partners'),
        );

        $this->addTutorRelation($firstSpeaker);
        $this->addTutorRelation($secondSpeaker);
        self::assertSame(
            $firstSpeaker['title'] . ', ' . $secondSpeaker['title'],
            $this->subject->getSpeakersShort('tutors'),
        );

        $this->addLeaderRelation($firstSpeaker);
        $this->addLeaderRelation($secondSpeaker);
        self::assertSame(
            $firstSpeaker['title'] . ', ' . $secondSpeaker['title'],
            $this->subject->getSpeakersShort('leaders'),
        );
    }

    /**
     * @test
     */
    public function getSpeakersShortReturnsSpeakerLinkedToSpeakerHomepage(): void
    {
        $speakerWithLink = [
            'title' => 'test speaker',
            'homepage' => 'https://www.foo.com',
        ];
        $this->addSpeakerRelation($speakerWithLink);
        $this->createPi1();

        self::assertMatchesRegularExpression(
            '/href="https:\\/\\/www.foo.com".*>test speaker/',
            $this->subject->getSpeakersShort(),
        );
    }

    /**
     * @test
     */
    public function getSpeakersForSpeakerWithoutHomepageReturnsSpeakerNameWithoutLinkTag(): void
    {
        $speaker = [
            'title' => 'test speaker',
        ];

        $this->addSpeakerRelation($speaker);
        $this->createPi1();

        $shortSpeakerOutput = $this->subject->getSpeakersShort();

        self::assertStringContainsString(
            'test speaker',
            $shortSpeakerOutput,
        );
        self::assertStringNotContainsString(
            '<a',
            $shortSpeakerOutput,
        );
    }

    // Tests for hasSeparateDetailsPage

    /**
     * @test
     */
    public function hasSeparateDetailsPageIsFalseByDefault(): void
    {
        self::assertFalse(
            $this->subject->hasSeparateDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function hasSeparateDetailsPageReturnsTrueForInternalSeparateDetailsPage(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $detailsPageUid = $this->testingFramework->createFrontEndPage($rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailsPageUid, ['slug' => '/eventDetail']);
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'details_page' => $detailsPageUid,
            ],
        );
        $event = new TestingLegacyEvent($eventUid);

        self::assertTrue(
            $event->hasSeparateDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function hasSeparateDetailsPageReturnsTrueForExternalSeparateDetailsPage(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'details_page' => 'www.example.com',
            ],
        );
        $event = new TestingLegacyEvent($eventUid);

        self::assertTrue(
            $event->hasSeparateDetailsPage(),
        );
    }

    // Tests for getDetailsPage

    /**
     * @test
     */
    public function getDetailsPageForNoSeparateDetailsPageSetReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function getDetailsPageForInternalSeparateDetailsPageSetReturnsThisPage(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $detailsPageUid = $this->testingFramework->createFrontEndPage($rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailsPageUid, ['slug' => '/eventDetail']);
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'details_page' => $detailsPageUid,
            ],
        );
        $event = new TestingLegacyEvent($eventUid);

        self::assertSame(
            (string)$detailsPageUid,
            $event->getDetailsPage(),
        );
    }

    /**
     * @test
     */
    public function getDetailsPageForExternalSeparateDetailsPageSetReturnsThisPage(): void
    {
        $externalUrl = 'www.example.com';
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'details_page' => $externalUrl,
            ],
        );
        $event = new TestingLegacyEvent($eventUid);

        self::assertSame(
            $externalUrl,
            $event->getDetailsPage(),
        );
    }

    // Tests concerning isOwnerFeUser

    /**
     * @test
     */
    public function isOwnerFeUserForNoOwnerReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->isOwnerFeUser(),
        );
    }

    /**
     * @test
     */
    public function isOwnerFeUserForLoggedInUserOtherThanOwnerReturnsFalse(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $userUid = $this->testingFramework->createAndLoginFrontEndUser();

        $this->subject->setOwnerUid($userUid + 1);

        self::assertFalse(
            $this->subject->isOwnerFeUser(),
        );
    }

    /**
     * @test
     */
    public function isOwnerFeUserForLoggedInUserOtherThanOwnerReturnsTrue(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $ownerUid = $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setOwnerUid($ownerUid);

        self::assertTrue(
            $this->subject->isOwnerFeUser(),
        );
    }

    // Tests concerning getOwner

    /**
     * @test
     */
    public function getOwnerForExistingOwnerReturnsFrontEndUserInstance(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $ownerUid = $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setOwnerUid($ownerUid);

        self::assertInstanceOf(FrontEndUser::class, $this->subject->getOwner());
    }

    /**
     * @test
     */
    public function getOwnerForExistingOwnerReturnsUserWithOwnersUid(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $ownerUid = $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setOwnerUid($ownerUid);

        self::assertSame(
            $ownerUid,
            $this->subject->getOwner()->getUid(),
        );
    }

    /**
     * @test
     */
    public function getOwnerForNoOwnerReturnsNull(): void
    {
        self::assertNull(
            $this->subject->getOwner(),
        );
    }

    // Tests concerning hasOwner

    /**
     * @test
     */
    public function hasOwnerForExistingOwnerReturnsTrue(): void
    {
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $ownerUid = $this->testingFramework->createAndLoginFrontEndUser();
        $this->subject->setOwnerUid($ownerUid);

        self::assertTrue(
            $this->subject->hasOwner(),
        );
    }

    /**
     * @test
     */
    public function hasOwnerForNoOwnerReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasOwner(),
        );
    }

    // Tests concerning getVacanciesString

    /**
     * @test
     */
    public function getVacanciesStringForCanceledEventWithVacanciesReturnsEmptyString(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 10);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setAttendancesMax(5);
        $this->subject->setNumberOfAttendances(0);
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertSame('', $this->subject->getVacanciesString());
    }

    /**
     * @test
     */
    public function getVacanciesStringWithoutRegistrationNeededReturnsEmptyString(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 10);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setNeedsRegistration(false);

        self::assertSame('', $this->subject->getVacanciesString());
    }

    /**
     * @test
     */
    public function getVacanciesStringForNonZeroVacanciesAndPastDeadlineReturnsEmptyString(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 10);
        $this->subject->setAttendancesMax(5);
        $this->subject->setNumberOfAttendances(0);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setRegistrationDeadline($this->now - 10000);

        self::assertSame('', $this->subject->getVacanciesString());
    }

    /**
     * @test
     */
    public function getVacanciesStringForNonZeroVacanciesBelowThresholdReturnsNumberOfVacancies(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 10);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setAttendancesMax(5);
        $this->subject->setNumberOfAttendances(0);

        self::assertSame(
            '5',
            $this->subject->getVacanciesString(),
        );
    }

    /**
     * @test
     */
    public function getVacanciesStringForNoVancanciesReturnsFullyBooked(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 10);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setAttendancesMax(5);
        $this->subject->setNumberOfAttendances(5);

        self::assertSame(
            $this->translate('message_fullyBooked'),
            $this->subject->getVacanciesString(),
        );
    }

    /**
     * @test
     */
    public function getVacanciesStringForVacanciesGreaterThanThresholdReturnsEnough(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 10);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setAttendancesMax(42);
        $this->subject->setNumberOfAttendances(0);

        self::assertSame(
            $this->translate('message_enough'),
            $this->subject->getVacanciesString(),
        );
    }

    /**
     * @test
     */
    public function getVacanciesStringForVacanciesEqualToThresholdReturnsEnough(): void
    {
        $this->configuration->setAsInteger('showVacanciesThreshold', 42);
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setAttendancesMax(42);
        $this->subject->setNumberOfAttendances(0);

        self::assertSame(
            $this->translate('message_enough'),
            $this->subject->getVacanciesString(),
        );
    }

    /**
     * @test
     */
    public function getVacanciesStringForUnlimitedVacanciesAndZeroRegistrationsReturnsEnough(): void
    {
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setNumberOfAttendances(0);

        self::assertSame(
            $this->translate('message_enough'),
            $this->subject->getVacanciesString(),
        );
    }

    /**
     * @test
     */
    public function getVacanciesStringForUnlimitedVacanciesAndOneRegistrationReturnsEnough(): void
    {
        $this->subject->setBeginDate($this->now + 10000);
        $this->subject->setUnlimitedVacancies();
        $this->subject->setNumberOfAttendances(1);

        self::assertSame(
            $this->translate('message_enough'),
            $this->subject->getVacanciesString(),
        );
    }

    // Tests for getLanguageKeySuffixForType

    /**
     * @test
     */
    public function getLanguageKeySuffixForTypeReturnsSpeakerType(): void
    {
        $this->addLeaderRelation([]);

        self::assertStringContainsString(
            'leaders_',
            $this->subject->getLanguageKeySuffixForType('leaders'),
        );
    }

    /**
     * @test
     */
    public function getLanguageKeySuffixForTypeForSingleSpeakerReturnsUnknownMarkerPart(): void
    {
        $this->addLeaderRelation([]);

        self::assertStringContainsString(
            '_unknown',
            $this->subject->getLanguageKeySuffixForType('leaders'),
        );
    }

    /**
     * @test
     */
    public function getLanguageKeySuffixForTypeForSingleSpeakerReturnsSingleMarkerPart(): void
    {
        $this->addSpeakerRelation();

        self::assertStringContainsString(
            '_single_',
            $this->subject->getLanguageKeySuffixForType('speakers'),
        );
    }

    /**
     * @test
     */
    public function getLanguageKeySuffixForTypeForMultipleSpeakersReturnsSpeakerType(): void
    {
        $this->addSpeakerRelation();
        $this->addSpeakerRelation();

        self::assertStringContainsString(
            'speakers',
            $this->subject->getLanguageKeySuffixForType('speakers'),
        );
    }

    // Tests concerning hasRequirements

    /**
     * @test
     */
    public function hasRequirementsForTopicWithoutRequirementsReturnsFalse(): void
    {
        $topic = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                    'requirements' => 0,
                ],
            ),
        );

        self::assertFalse(
            $topic->hasRequirements(),
        );
    }

    /**
     * @test
     */
    public function hasRequirementsForDateOfTopicWithoutRequirementsReturnsFalse(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 0,
            ],
        );
        $date = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_DATE,
                    'topic' => $topicUid,
                ],
            ),
        );

        self::assertFalse(
            $date->hasRequirements(),
        );
    }

    /**
     * @test
     */
    public function hasRequirementsForTopicWithOneRequirementReturnsTrue(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid,
            'requirements',
        );
        $topic = new TestingLegacyEvent($topicUid);

        self::assertTrue(
            $topic->hasRequirements(),
        );
    }

    /**
     * @test
     */
    public function hasRequirementsForDateOfTopicWithOneRequirementReturnsTrue(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid,
            'requirements',
        );
        $date = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_DATE,
                    'topic' => $topicUid,
                ],
            ),
        );

        self::assertTrue(
            $date->hasRequirements(),
        );
    }

    /**
     * @test
     */
    public function hasRequirementsForTopicWithTwoRequirementsReturnsTrue(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredTopicUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid1,
            'requirements',
        );
        $requiredTopicUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid2,
            'requirements',
        );
        $topic = new TestingLegacyEvent($topicUid);

        self::assertTrue(
            $topic->hasRequirements(),
        );
    }

    // Tests concerning hasDependencies

    /**
     * @test
     */
    public function hasDependenciesForTopicWithoutDependenciesReturnsFalse(): void
    {
        $topic = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                    'dependencies' => 0,
                ],
            ),
        );

        self::assertFalse(
            $topic->hasDependencies(),
        );
    }

    /**
     * @test
     */
    public function hasDependenciesForDateOfTopicWithoutDependenciesReturnsFalse(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 0,
            ],
        );
        $date = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_DATE,
                    'topic' => $topicUid,
                ],
            ),
        );

        self::assertFalse(
            $date->hasDependencies(),
        );
    }

    /**
     * @test
     */
    public function hasDependenciesForTopicWithOneDependencyReturnsTrue(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependentTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid,
            $topicUid,
        );
        $topic = new TestingLegacyEvent($topicUid);

        self::assertTrue(
            $topic->hasDependencies(),
        );
    }

    /**
     * @test
     */
    public function hasDependenciesForDateOfTopicWithOneDependencyReturnsTrue(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependentTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid,
            $topicUid,
        );
        $date = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_DATE,
                    'topic' => $topicUid,
                ],
            ),
        );

        self::assertTrue(
            $date->hasDependencies(),
        );
    }

    /**
     * @test
     */
    public function hasDependenciesForTopicWithTwoDependenciesReturnsTrue(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 2,
            ],
        );
        $dependentTopicUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid1,
            $topicUid,
        );
        $dependentTopicUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid2,
            $topicUid,
        );

        $result = (new TestingLegacyEvent($topicUid))->hasDependencies();

        self::assertTrue(
            $result,
        );
    }

    // Tests concerning getRequirements

    /**
     * @test
     */
    public function getRequirementsReturnsSeminarBag(): void
    {
        self::assertInstanceOf(EventBag::class, $this->subject->getRequirements());
    }

    /**
     * @test
     */
    public function getRequirementsForNoRequirementsReturnsEmptyBag(): void
    {
        self::assertTrue(
            $this->subject->getRequirements()->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function getRequirementsForOneRequirementReturnsBagWithOneTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid,
            'requirements',
        );

        $result = (new TestingLegacyEvent($topicUid))->getRequirements();

        self::assertSame(
            1,
            $result->count(),
        );
        self::assertSame(
            $requiredTopicUid,
            $result->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function getRequirementsForDateOfTopicWithOneRequirementReturnsBagWithOneTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid,
            'requirements',
        );
        $date = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_DATE,
                    'topic' => $topicUid,
                ],
            ),
        );

        $result = $date->getRequirements();

        self::assertSame(
            1,
            $result->count(),
        );
        self::assertSame(
            $requiredTopicUid,
            $result->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function getRequirementsForTwoRequirementsReturnsBagWithTwoItems(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredTopicUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid1,
            'requirements',
        );
        $requiredTopicUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $requiredTopicUid2,
            'requirements',
        );

        $requirements = (new TestingLegacyEvent($topicUid))->getRequirements();

        self::assertSame(
            2,
            $requirements->count(),
        );
    }

    // Tests concerning getDependencies

    /**
     * @test
     */
    public function getDependenciesReturnsSeminarBag(): void
    {
        self::assertInstanceOf(EventBag::class, $this->subject->getDependencies());
    }

    /**
     * @test
     */
    public function getDependenciesForNoDependenciesReturnsEmptyBag(): void
    {
        self::assertTrue(
            $this->subject->getDependencies()->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function getDependenciesForOneDependencyReturnsBagWithOneTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependentTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid,
            $topicUid,
        );

        $result = (new TestingLegacyEvent($topicUid))->getDependencies();

        self::assertSame(
            1,
            $result->count(),
        );
        self::assertSame(
            $dependentTopicUid,
            $result->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function getDependenciesForDateOfTopicWithOneDependencyReturnsBagWithOneTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 1,
            ],
        );
        $dependentTopicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid,
            $topicUid,
        );
        $date = new TestingLegacyEvent(
            $this->testingFramework->createRecord(
                'tx_seminars_seminars',
                [
                    'object_type' => EventInterface::TYPE_EVENT_DATE,
                    'topic' => $topicUid,
                ],
            ),
        );

        $result = $date->getDependencies();

        self::assertSame(
            1,
            $result->count(),
        );
        self::assertSame(
            $dependentTopicUid,
            $result->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function getDependenciesForTwoDependenciesReturnsBagWithTwoItems(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'dependencies' => 2,
            ],
        );
        $dependentTopicUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid1,
            $topicUid,
        );
        $dependentTopicUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'requirements' => 1,
            ],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_requirements_mm',
            $dependentTopicUid2,
            $topicUid,
        );

        $dependencies = (new TestingLegacyEvent($topicUid))->getDependencies();

        self::assertSame(
            2,
            $dependencies->count(),
        );
    }

    // Tests concerning isConfirmed

    /**
     * @test
     */
    public function isConfirmedForStatusPlannedReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertFalse(
            $this->subject->isConfirmed(),
        );
    }

    /**
     * @test
     */
    public function isConfirmedForStatusConfirmedReturnsTrue(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertTrue(
            $this->subject->isConfirmed(),
        );
    }

    /**
     * @test
     */
    public function isConfirmedForStatusCanceledReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertFalse(
            $this->subject->isConfirmed(),
        );
    }

    // Tests concerning isCanceled

    /**
     * @test
     */
    public function isCanceledForPlannedEventReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertFalse(
            $this->subject->isCanceled(),
        );
    }

    /**
     * @test
     */
    public function isCanceledForCanceledEventReturnsTrue(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertTrue(
            $this->subject->isCanceled(),
        );
    }

    /**
     * @test
     */
    public function isCanceledForConfirmedEventReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertFalse(
            $this->subject->isCanceled(),
        );
    }

    // Tests concerning isPlanned

    /**
     * @test
     */
    public function isPlannedForStatusPlannedReturnsTrue(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_PLANNED);

        self::assertTrue(
            $this->subject->isPlanned(),
        );
    }

    /**
     * @test
     */
    public function isPlannedForStatusConfirmedReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CONFIRMED);

        self::assertFalse(
            $this->subject->isPlanned(),
        );
    }

    /**
     * @test
     */
    public function isPlannedForStatusCanceledReturnsFalse(): void
    {
        $this->subject->setStatus(EventInterface::STATUS_CANCELED);

        self::assertFalse(
            $this->subject->isPlanned(),
        );
    }

    // Tests concerning setEventTakesPlaceReminderSentFlag

    /**
     * @test
     */
    public function setEventTakesPlaceReminderSentFlagSetsFlagToTrue(): void
    {
        $this->subject->setEventTakesPlaceReminderSentFlag();

        self::assertTrue(
            $this->subject->getRecordPropertyBoolean(
                'event_takes_place_reminder_sent',
            ),
        );
    }

    // Tests concerning setCancelationDeadlineReminderSentFlag

    /**
     * @test
     */
    public function setCancellationDeadlineReminderSentFlagToTrue(): void
    {
        $this->subject->setCancelationDeadlineReminderSentFlag();

        self::assertTrue(
            $this->subject->getRecordPropertyBoolean(
                'cancelation_deadline_reminder_sent',
            ),
        );
    }

    // Tests concerning getCancelationDeadline

    /**
     * @test
     */
    public function getCancellationDeadlineForEventWithoutSpeakerReturnsBeginDateOfEvent(): void
    {
        $this->subject->setBeginDate($this->now);

        self::assertSame(
            $this->subject->getBeginDateAsTimestamp(),
            $this->subject->getCancelationDeadline(),
        );
    }

    /**
     * @test
     */
    public function getCancellationDeadlineForEventWithSpeakerWithoutCancellationPeriodReturnsBeginDateOfEvent(): void
    {
        $this->subject->setBeginDate($this->now);
        $this->addSpeakerRelation(['cancelation_period' => 0]);

        self::assertSame(
            $this->subject->getBeginDateAsTimestamp(),
            $this->subject->getCancelationDeadline(),
        );
    }

    /**
     * @test
     */
    public function getCancellationDeadlineForEventWithTwoSpeakersWithoutCancellationPeriodReturnsBeginDateOfEvent(): void
    {
        $this->subject->setBeginDate($this->now);
        $this->addSpeakerRelation(['cancelation_period' => 0]);
        $this->addSpeakerRelation(['cancelation_period' => 0]);

        self::assertSame(
            $this->subject->getBeginDateAsTimestamp(),
            $this->subject->getCancelationDeadline(),
        );
    }

    /**
     * @test
     */
    public function getCancellationDeadlineForEventWithOneSpeakersWithCancellationPeriodReturnsBeginDateMinusCancelationPeriod(): void
    {
        $this->subject->setBeginDate($this->now);
        $this->addSpeakerRelation(['cancelation_period' => 1]);

        self::assertSame(
            $this->now - Time::SECONDS_PER_DAY,
            $this->subject->getCancelationDeadline(),
        );
    }

    /**
     * @test
     */
    public function getCancellationDeadlineForEventWithTwoSpeakersWithCancellationPeriodsReturnsBeginDateMinusBiggestCancelationPeriod(): void
    {
        $this->subject->setBeginDate($this->now);
        $this->addSpeakerRelation(['cancelation_period' => 21]);
        $this->addSpeakerRelation(['cancelation_period' => 42]);

        self::assertSame(
            $this->now - (42 * Time::SECONDS_PER_DAY),
            $this->subject->getCancelationDeadline(),
        );
    }

    /**
     * @test
     */
    public function getCancellationDeadlineForEventWithoutBeginDateThrowsException(): void
    {
        $this->subject->setBeginDate(0);

        $this->expectException(
            \BadMethodCallException::class,
        );
        $this->expectExceptionMessage(
            'The event has no begin date. Please call this function only if the event has a begin date.',
        );

        $this->subject->getCancelationDeadline();
    }

    // Tests concerning the license expiry

    /**
     * @test
     */
    public function hasExpiryForNoExpiryReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasExpiry(),
        );
    }

    /**
     * @test
     */
    public function hasExpiryForNonZeroExpiryReturnsTrue(): void
    {
        $this->subject->setExpiry(42);

        self::assertTrue(
            $this->subject->hasExpiry(),
        );
    }

    /**
     * @test
     */
    public function getExpiryForNoExpiryReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getExpiry(),
        );
    }

    /**
     * @test
     */
    public function getExpiryForNonZeroExpiryReturnsFormattedDate(): void
    {
        $this->subject->setExpiry(mktime(0, 0, 0, 12, 31, 2000));

        self::assertSame(
            '2000-12-31',
            $this->subject->getExpiry(),
        );
    }

    // Tests concerning getEventData

    /**
     * @test
     */
    public function getEventDataReturnsFormattedUnregistrationDeadlineWithTime(): void
    {
        $this->subject->setUnregistrationDeadline(1893488400);

        self::assertSame('2030-01-01 09:00', $this->subject->getEventData('deadline_unregistration'));
    }

    /**
     * @test
     */
    public function getEventDataForUnregistrationDeadlineZeroReturnsEmptyString(): void
    {
        $this->subject->setUnregistrationDeadline(0);
        self::assertSame(
            '',
            $this->subject->getEventData('deadline_unregistration'),
        );
    }

    /**
     * @test
     */
    public function getEventDataForEventWithMultipleLodgingsSeparatesLodgingsWithLineFeeds(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $lodgingUid1 = $this->testingFramework->createRecord(
            'tx_seminars_lodgings',
            ['title' => 'foo'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_lodgings_mm',
            $eventUid,
            $lodgingUid1,
        );

        $lodgingUid2 = $this->testingFramework->createRecord(
            'tx_seminars_lodgings',
            ['title' => 'bar'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_lodgings_mm',
            $eventUid,
            $lodgingUid2,
        );

        $this->subject->setNumberOfLodgings(2);

        self::assertStringContainsString(
            "foo\nbar",
            $this->subject->getEventData('lodgings'),
        );
    }

    /**
     * @test
     */
    public function getEventDataForEventWithMultipleLodgingsDoesNotSeparateLodgingsWithCarriageReturnsAndLineFeeds(): void
    {
        $eventUid = $this->subject->getUid();
        \assert($eventUid > 0);

        $lodgingUid1 = $this->testingFramework->createRecord(
            'tx_seminars_lodgings',
            ['title' => 'foo'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_lodgings_mm',
            $eventUid,
            $lodgingUid1,
        );

        $lodgingUid2 = $this->testingFramework->createRecord(
            'tx_seminars_lodgings',
            ['title' => 'bar'],
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_lodgings_mm',
            $eventUid,
            $lodgingUid2,
        );

        $this->subject->setNumberOfLodgings(2);

        self::assertStringNotContainsString(
            "foo\r\nbar",
            $this->subject->getEventData('lodgings'),
        );
    }

    /**
     * @test
     */
    public function getEventDataDataWithCarriageReturnAndLinefeedGetsConvertedToLineFeedOnly(): void
    {
        $this->subject->setDescription("foo\r\nbar");

        self::assertStringContainsString(
            "foo\nbar",
            $this->subject->getEventData('description'),
        );
    }

    /**
     * @test
     */
    public function getEventDataDataWithTwoAdjacentLineFeedsReturnsStringWithOnlyOneLineFeed(): void
    {
        $this->subject->setDescription("foo\n\nbar");

        self::assertStringContainsString(
            "foo\nbar",
            $this->subject->getEventData('description'),
        );
    }

    /**
     * @test
     */
    public function getEventDataDataWithThreeAdjacentLineFeedsReturnsStringWithOnlyOneLineFeed(): void
    {
        $this->subject->setDescription("foo\n\n\nbar");

        self::assertStringContainsString(
            "foo\nbar",
            $this->subject->getEventData('description'),
        );
    }

    /**
     * @test
     */
    public function getEventDataDataWithFourAdjacentLineFeedsReturnsStringWithOnlyOneLineFeed(): void
    {
        $this->subject->setDescription("foo\n\n\n\nbar");

        self::assertStringContainsString(
            "foo\nbar",
            $this->subject->getEventData('description'),
        );
    }

    /**
     * @test
     */
    public function getEventDataForEventWithDateUsesHyphenAsDateSeparator(): void
    {
        $this->subject->setBeginDate($this->now);
        $this->subject->setEndDate($this->now + Time::SECONDS_PER_DAY);

        self::assertStringContainsString(
            '-',
            $this->subject->getEventData('date'),
        );
    }

    /**
     * @test
     */
    public function getEventDataForEventWithTimeUsesHyphenAsTimeSeparator(): void
    {
        $this->subject->setBeginDate($this->now);
        $this->subject->setEndDate($this->now + 3600);

        self::assertStringContainsString(
            '-',
            $this->subject->getEventData('time'),
        );
    }

    /**
     * @test
     */
    public function getEventDataSeparatesPlacePartsByCommaAndSpace(): void
    {
        $place = [
            'title' => 'Hotel Ibis',
            'homepage' => '',
            'address' => 'Kaiser-Karl-Ring 91, 53111 Bonn',
            'city' => 'Bonn',
            'directions' => '',
        ];

        $subject = $this->createPartialMock(LegacyEvent::class, ['getPlacesAsArray', 'hasPlace']);
        $subject->method('getPlacesAsArray')->willReturn([$place]);
        $subject->method('hasPlace')->willReturn(true);

        self::assertSame(
            'Hotel Ibis, Kaiser-Karl-Ring 91, 53111 Bonn',
            $subject->getEventData('place'),
        );
    }

    /**
     * @test
     */
    public function getEventDataSeparatesTwoPlacesByLineFeed(): void
    {
        $place1 = [
            'title' => 'Hotel Ibis',
            'homepage' => '',
            'address' => '',
            'city' => '',
            'directions' => '',
        ];
        $place2 = [
            'title' => 'Wasserwerk',
            'homepage' => '',
            'address' => '',
            'city' => '',
            'directions' => '',
        ];

        $subject = $this->createPartialMock(LegacyEvent::class, ['getPlacesAsArray', 'hasPlace']);
        $subject->method('getPlacesAsArray')->willReturn([$place1, $place2]);
        $subject->method('hasPlace')->willReturn(true);

        self::assertSame(
            "Hotel Ibis\nWasserwerk",
            $subject->getEventData('place'),
        );
    }

    /**
     * @test
     */
    public function getEventDataForPlaceWithoutZipContainsTitleAndAddressAndCity(): void
    {
        $place = [
            'title' => 'Hotel Ibis',
            'address' => 'Kaiser-Karl-Ring 91, 53111 Bonn',
            'city' => 'Bonn',
            'homepage' => '',
            'directions' => '',
        ];

        $subject = $this->createPartialMock(LegacyEvent::class, ['getPlacesAsArray', 'hasPlace']);
        $subject->method('getPlacesAsArray')->willReturn([$place]);
        $subject->method('hasPlace')->willReturn(true);

        self::assertSame(
            'Hotel Ibis, Kaiser-Karl-Ring 91, 53111 Bonn',
            $subject->getEventData('place'),
        );
    }

    // Tests concerning dumpSeminarValues

    /**
     * @test
     */
    public function dumpSeminarValuesForTitleGivenReturnsTitle(): void
    {
        self::assertStringContainsString(
            $this->subject->getTitle(),
            $this->subject->dumpSeminarValues('title'),
        );
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForTitleGivenReturnsLabelForTitle(): void
    {
        self::assertStringContainsString(
            $this->translate('label_title'),
            $this->subject->dumpSeminarValues('title'),
        );
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForTitleGivenReturnsTitleWithLineFeedAtEndOfLine(): void
    {
        self::assertMatchesRegularExpression(
            '/\\n$/',
            $this->subject->dumpSeminarValues('title'),
        );
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForTitleAndDescriptionGivenReturnsTitleAndDescription(): void
    {
        $this->subject->setDescription('foo bar');

        self::assertMatchesRegularExpression(
            '/.*' . $this->subject->getTitle() . '.*\\n.*' .
            $this->subject->getRecordPropertyString('description') . '/',
            $this->subject->dumpSeminarValues('title,description'),
        );
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForEventWithoutDescriptionAndDescriptionGivenReturnsDescriptionLabelWithColonsAndLineFeed(): void
    {
        $this->subject->setDescription('');

        self::assertStringContainsString(":\n", $this->subject->dumpSeminarValues('description'));
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForEventWithNoVacanciesAndVacanciesGivenReturnsVacanciesLabelWithNumber(): void
    {
        $this->subject->setNumberOfAttendances(2);
        $this->subject->setAttendancesMax(2);
        $this->subject->setNeedsRegistration(true);

        self::assertSame(
            $this->translate('label_vacancies') . ": 0\n",
            $this->subject->dumpSeminarValues('vacancies'),
        );
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForEventWithOneVacancyAndVacanciesGivenReturnsNumberOfVacancies(): void
    {
        $this->subject->setNumberOfAttendances(1);
        $this->subject->setAttendancesMax(2);
        $this->subject->setNeedsRegistration(true);

        self::assertSame(
            $this->translate('label_vacancies') . ": 1\n",
            $this->subject->dumpSeminarValues('vacancies'),
        );
    }

    /**
     * @test
     */
    public function dumpSeminarValuesForEventWithUnlimitedVacanciesAndVacanciesGivenReturnsVacanciesUnlimitedString(): void
    {
        $this->subject->setUnlimitedVacancies();

        self::assertSame(
            $this->translate('label_vacancies') . ': ' .
            $this->translate('label_unlimited') . "\n",
            $this->subject->dumpSeminarValues('vacancies'),
        );
    }

    /**
     * @return string[][]
     */
    public function dumpableEventFieldsDataProvider(): array
    {
        $fields = [
            'uid',
            'event_type',
            'title',
            'subtitle',
            'titleanddate',
            'date',
            'time',
            'accreditation_number',
            'credit_points',
            'room',
            'place',
            'speakers',
            'price_regular',
            'price_regular_early',
            'price_special',
            'price_special_early',
            'allows_multiple_registrations',
            'attendees',
            'attendees_min',
            'attendees_max',
            'vacancies',
            'enough_attendees',
            'is_full',
            'notes',
        ];

        $result = [];
        foreach ($fields as $field) {
            $result[$field] = [$field];
        }

        return $result;
    }

    /**
     * @test
     *
     * @param non-empty-string $fieldName
     *
     * @dataProvider dumpableEventFieldsDataProvider
     */
    public function dumpSeminarValuesCreatesNoDoubleColonsAfterLabel(string $fieldName): void
    {
        $this->subject->setRecordPropertyString($fieldName, '1234 some value');

        $result = $this->subject->dumpSeminarValues($fieldName);

        self::assertStringNotContainsString('::', $result);
    }

    // Tests regarding the registration begin date

    /**
     * @test
     */
    public function hasRegistrationBeginForNoRegistrationBeginReturnsFalse(): void
    {
        $this->subject->setRegistrationBeginDate(0);

        self::assertFalse(
            $this->subject->hasRegistrationBegin(),
        );
    }

    /**
     * @test
     */
    public function hasRegistrationBeginForEventWithRegistrationBeginReturnsTrue(): void
    {
        $this->subject->setRegistrationBeginDate(42);

        self::assertTrue(
            $this->subject->hasRegistrationBegin(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationBeginAsUnixTimestampForEventWithoutRegistrationBeginReturnsZero(): void
    {
        $this->subject->setRegistrationBeginDate(0);

        self::assertSame(
            0,
            $this->subject->getRegistrationBeginAsUnixTimestamp(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationBeginAsUnixTimestampForEventWithRegistrationBeginReturnsRegistrationBeginAsUnixTimestamp(): void
    {
        $this->subject->setRegistrationBeginDate(42);

        self::assertSame(
            42,
            $this->subject->getRegistrationBeginAsUnixTimestamp(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationBeginForEventWithoutRegistrationBeginReturnsEmptyString(): void
    {
        $this->subject->setRegistrationBeginDate(0);

        self::assertSame(
            '',
            $this->subject->getRegistrationBegin(),
        );
    }

    /**
     * @test
     */
    public function getRegistrationBeginForEventWithRegistrationBeginReturnsFormattedRegistrationBegin(): void
    {
        $this->subject->setRegistrationBeginDate($this->now);

        self::assertSame(\date('Y-m-d H:i', $this->now), $this->subject->getRegistrationBegin());
    }

    // Tests regarding the description.

    /**
     * @test
     */
    public function getDescriptionWithoutDescriptionReturnEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getDescription(),
        );
    }

    /**
     * @test
     */
    public function setDescriptionSetsDescription(): void
    {
        $this->subject->setDescription('this is a great event.');

        self::assertSame(
            'this is a great event.',
            $this->subject->getDescription(),
        );
    }

    /**
     * @test
     */
    public function hasDescriptionWithoutDescriptionReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasDescription(),
        );
    }

    /**
     * @test
     */
    public function hasDescriptionWithDescriptionReturnsTrue(): void
    {
        $this->subject->setDescription('this is a great event.');

        self::assertTrue(
            $this->subject->hasDescription(),
        );
    }

    // Tests regarding the additional information.

    /**
     * @test
     */
    public function getAdditionalInformationWithoutAdditionalInformationReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getAdditionalInformation(),
        );
    }

    /**
     * @test
     */
    public function setAdditionalInformationSetsAdditionalInformation(): void
    {
        $this->subject->setAdditionalInformation('this is good to know');

        self::assertSame(
            'this is good to know',
            $this->subject->getAdditionalInformation(),
        );
    }

    /**
     * @test
     */
    public function hasAdditionalInformationWithoutAdditionalInformationReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasAdditionalInformation(),
        );
    }

    /**
     * @test
     */
    public function hasAdditionalInformationWithAdditionalInformationReturnsTrue(): void
    {
        $this->subject->setAdditionalInformation('this is good to know');

        self::assertTrue(
            $this->subject->hasAdditionalInformation(),
        );
    }

    // Tests concerning getLatestPossibleRegistrationTime

    /**
     * @test
     */
    public function getLatestPossibleRegistrationTimeForEventWithoutAnyDatesReturnsZero(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'needs_registration' => 1,
                'deadline_registration' => 0,
                'begin_date' => 0,
                'end_date' => 0,
            ],
        );
        $subject = new TestingLegacyEvent($uid);

        self::assertSame(
            0,
            $subject->getLatestPossibleRegistrationTime(),
        );
    }

    /**
     * @test
     */
    public function getLatestPossibleRegistrationTimeForEventWithBeginDateReturnsBeginDate(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'needs_registration' => 1,
                'deadline_registration' => 0,
                'begin_date' => $this->now,
                'end_date' => 0,
            ],
        );
        $subject = new TestingLegacyEvent($uid);

        self::assertSame(
            $this->now,
            $subject->getLatestPossibleRegistrationTime(),
        );
    }

    /**
     * @test
     */
    public function getLatestPossibleRegistrationTimeForEventWithBeginDateAndRegistrationDeadlineReturnsRegistrationDeadline(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'title' => 'a test event',
                'needs_registration' => 1,
                'deadline_registration' => $this->now,
                'begin_date' => $this->now + 1000,
                'end_date' => 0,
            ],
        );
        $subject = new TestingLegacyEvent($uid);

        self::assertSame(
            $this->now,
            $subject->getLatestPossibleRegistrationTime(),
        );
    }

    // Tests concerning getTopicInteger

    /**
     * @test
     */
    public function getTopicIntegerForSingleEventReturnsDataFromRecord(): void
    {
        $this->subject->setRecordPropertyInteger('credit_points', 42);

        self::assertSame(
            42,
            $this->subject->getTopicInteger('credit_points'),
        );
    }

    /**
     * @test
     */
    public function getTopicIntegerForDateReturnsDataFromTopic(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'credit_points' => 42,
            ],
        );
        $dateRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicRecordUid,
            ],
        );

        $date = new TestingLegacyEvent($dateRecordUid);

        self::assertSame(
            42,
            $date->getTopicInteger('credit_points'),
        );
    }

    // Tests concerning hasTopicInteger

    /**
     * @test
     */
    public function hasTopicIntegerForSingleEventForZeroReturnsFalse(): void
    {
        $this->subject->setRecordPropertyInteger('credit_points', 0);

        self::assertFalse(
            $this->subject->hasTopicInteger('credit_points'),
        );
    }

    /**
     * @test
     */
    public function hasTopicIntegerForSingleEventForPositiveIntegerReturnsFalse(): void
    {
        $this->subject->setRecordPropertyInteger('credit_points', 1);

        self::assertTrue(
            $this->subject->hasTopicInteger('credit_points'),
        );
    }

    /**
     * @test
     */
    public function hasTopicIntegerForSingleEventForNegativeIntegerReturnsFalse(): void
    {
        $this->subject->setRecordPropertyInteger('credit_points', -1);

        self::assertTrue(
            $this->subject->hasTopicInteger('credit_points'),
        );
    }

    /**
     * @test
     */
    public function hasTopicIntegerForDateForZeroInTopicReturnsFalse(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'credit_points' => 0,
            ],
        );
        $dateRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicRecordUid,
            ],
        );

        $date = new TestingLegacyEvent($dateRecordUid);

        self::assertFalse(
            $date->hasTopicInteger('credit_points'),
        );
    }

    /**
     * @test
     */
    public function hasTopicIntegerForDateForPositiveIntegerInTopicReturnsTrue(): void
    {
        $topicRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'credit_points' => 1,
            ],
        );
        $dateRecordUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicRecordUid,
            ],
        );

        $date = new TestingLegacyEvent($dateRecordUid);

        self::assertTrue(
            $date->hasTopicInteger('credit_points'),
        );
    }

    // Tests concerning canViewRegistrationsList

    /**
     * Data provider for testing the canViewRegistrationsList function
     * with default access and access only for attendees and managers.
     *
     * @return mixed[][] test data for canViewRegistrationsList with each row
     *               having the following elements:
     *               [expected] boolean: expected value (TRUE or FALSE)
     *               [loggedIn] boolean: whether a user is logged in
     *               [isRegistered] boolean: whether the logged-in user is
     *                              registered for that event
     *               [isVip] boolean: whether the logged-in user is a VIP
     *                                that event
     *               [whichPlugin] string: value for that parameter
     *               [registrationsListPID] integer: value for that parameter
     *               [registrationsVipListPID] integer: value for that parameter
     */
    public function canViewRegistrationsListDataProvider(): array
    {
        return [
            'seminarListWithNothingElse' => [
                'expected' => false,
                'loggedIn' => false,
                'isRegistered' => false,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'seminarListLoggedInWithListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsRegisteredWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsRegisteredWithoutListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsVipWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => true,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 1,
            ],
            'seminarListIsVipWithoutListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'myEventsIsRegisteredWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'my_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'myEventsIsVipWithListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'my_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'myVipEventsIsRegisteredWithListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'my_vip_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'myVipEventsIsVipWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'my_vip_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'listRegistrationsIsRegistered' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'list_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'listRegistrationsIsVip' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'list_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'listVipRegistrationsIsRegistered' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'list_vip_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'listVipRegistrationsIsVip' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'list_vip_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider canViewRegistrationsListDataProvider
     */
    public function canViewRegistrationsListWithNeedsRegistrationAndDefaultAccess(
        bool $expected,
        bool $loggedIn,
        bool $isRegistered,
        bool $isVip,
        string $whichPlugin,
        int $registrationsListPID,
        int $registrationsVipListPID
    ): void {
        $subject = $this->createPartialMock(
            LegacyEvent::class,
            ['needsRegistration', 'isUserRegistered', 'isUserVip'],
        );
        $subject
            ->method('needsRegistration')
            ->willReturn(true);
        $subject
            ->method('isUserRegistered')
            ->willReturn($isRegistered);
        $subject
            ->method('isUserVip')
            ->willReturn($isVip);

        if ($loggedIn) {
            $rootPageUid = $this->testingFramework->createFrontEndPage();
            $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
            $this->testingFramework->createFakeFrontEnd($rootPageUid);
            $this->testingFramework->createAndLoginFrontEndUser();
        }

        self::assertSame(
            $expected,
            $subject->canViewRegistrationsList(
                $whichPlugin,
                $registrationsListPID,
                $registrationsVipListPID,
            ),
        );
    }

    /**
     * @test
     *
     * @dataProvider canViewRegistrationsListDataProvider
     */
    public function canViewRegistrationsListWithNeedsRegistrationAndAttendeesManagersAccess(
        bool $expected,
        bool $loggedIn,
        bool $isRegistered,
        bool $isVip,
        string $whichPlugin,
        int $registrationsListPID,
        int $registrationsVipListPID
    ): void {
        $subject = $this->createPartialMock(
            LegacyEvent::class,
            ['needsRegistration', 'isUserRegistered', 'isUserVip'],
        );
        $subject
            ->method('needsRegistration')
            ->willReturn(true);
        $subject
            ->method('isUserRegistered')
            ->willReturn($isRegistered);
        $subject
            ->method('isUserVip')
            ->willReturn($isVip);

        if ($loggedIn) {
            $rootPageUid = $this->testingFramework->createFrontEndPage();
            $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
            $this->testingFramework->createFakeFrontEnd($rootPageUid);
            $this->testingFramework->createAndLoginFrontEndUser();
        }

        self::assertSame(
            $expected,
            $subject->canViewRegistrationsList(
                $whichPlugin,
                $registrationsListPID,
                $registrationsVipListPID,
            ),
        );
    }

    /**
     * Data provider for testing the canViewRegistrationsList function
     * with login access.
     *
     * @return mixed[][] test data for canViewRegistrationsList with each row
     *               having the following elements:
     *               [expected] boolean: expected value (TRUE or FALSE)
     *               [loggedIn] boolean: whether a user is logged in
     *               [isRegistered] boolean: whether the logged-in user is
     *                              registered for that event
     *               [isVip] boolean: whether the logged-in user is a VIP
     *                                that event
     *               [whichPlugin] string: value for that parameter
     *               [registrationsListPID] integer: value for that parameter
     *               [registrationsVipListPID] integer: value for that parameter
     */
    public function canViewRegistrationsListDataProviderForLoggedIn(): array
    {
        return [
            'seminarListWithNothingElse' => [
                'expected' => false,
                'loggedIn' => false,
                'isRegistered' => false,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'seminarListLoggedInWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsRegisteredWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsRegisteredWithoutListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsVipWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => true,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 0,
            ],
            'seminarListIsVipWithVipListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => true,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 1,
            ],
            'seminarListIsVipWithoutListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'seminar_list',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'myEventsIsRegisteredWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'my_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'myEventsIsVipWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'my_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'myVipEventsIsRegisteredWithListPid' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'my_vip_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'myVipEventsIsVipWithListPid' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'my_vip_events',
                'registrationsListPID' => 1,
                'registrationsVipListPID' => 1,
            ],
            'listRegistrationsIsRegistered' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'list_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'listRegistrationsIsVip' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'list_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'listVipRegistrationsIsRegistered' => [
                'expected' => false,
                'loggedIn' => true,
                'isRegistered' => true,
                'isVip' => false,
                'whichPlugin' => 'list_vip_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
            'listVipRegistrationsIsVip' => [
                'expected' => true,
                'loggedIn' => true,
                'isRegistered' => false,
                'isVip' => true,
                'whichPlugin' => 'list_vip_registrations',
                'registrationsListPID' => 0,
                'registrationsVipListPID' => 0,
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider canViewRegistrationsListDataProviderForLoggedIn
     */
    public function canViewRegistrationsListWithNeedsRegistrationAndLoginAccess(
        bool $expected,
        bool $loggedIn,
        bool $isRegistered,
        bool $isVip,
        string $whichPlugin,
        int $registrationsListPID,
        int $registrationsVipListPID
    ): void {
        $subject = $this->createPartialMock(
            LegacyEvent::class,
            ['needsRegistration', 'isUserRegistered', 'isUserVip'],
        );
        $subject
            ->method('needsRegistration')
            ->willReturn(true);
        $subject
            ->method('isUserRegistered')
            ->willReturn($isRegistered);
        $subject
            ->method('isUserVip')
            ->willReturn($isVip);

        if ($loggedIn) {
            $rootPageUid = $this->testingFramework->createFrontEndPage();
            $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
            $this->testingFramework->createFakeFrontEnd($rootPageUid);
            $this->testingFramework->createAndLoginFrontEndUser();
        }

        self::assertSame(
            $expected,
            $subject->canViewRegistrationsList(
                $whichPlugin,
                $registrationsListPID,
                $registrationsVipListPID,
                'login',
            ),
        );
    }

    // Tests concerning canViewRegistrationsListMessage

    /**
     * @test
     */
    public function canViewRegistrationsListMessageWithoutNeededRegistrationReturnsNoRegistrationMessage(): void
    {
        $subject = $this->createPartialMock(LegacyEvent::class, ['needsRegistration']);
        $subject->method('needsRegistration')->willReturn(false);

        self::assertSame(
            $this->translate('message_noRegistrationNecessary'),
            $subject->canViewRegistrationsListMessage('list_registrations'),
        );
    }

    /**
     * @test
     */
    public function canViewRegistrationsListMessageForListAndNoLoginAndAttendeesAccessReturnsPleaseLoginMessage(): void
    {
        $subject = $this->createPartialMock(LegacyEvent::class, ['needsRegistration']);
        $subject->method('needsRegistration')->willReturn(true);

        self::assertSame(
            $this->translate('message_notLoggedIn'),
            $subject->canViewRegistrationsListMessage('list_registrations'),
        );
    }

    /**
     * @test
     */
    public function canViewRegistrationsListMessageForListAndNoLoginAndLoginAccessReturnsPleaseLoginMessage(): void
    {
        $subject = $this->createPartialMock(LegacyEvent::class, ['needsRegistration']);
        $subject->method('needsRegistration')->willReturn(true);

        self::assertSame(
            $this->translate('message_notLoggedIn'),
            $subject->canViewRegistrationsListMessage('list_registrations', 'login'),
        );
    }

    /**
     * Data provider that returns all possible access level codes for the
     * FE registration lists.
     *
     * @return string[][] the possible access levels, will not be empty
     */
    public function registrationListAccessLevelsDataProvider(): array
    {
        return [
            'attendeesAndManagers' => ['attendees_and_managers'],
            'login' => ['login'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider registrationListAccessLevelsDataProvider
     */
    public function canViewRegistrationsListMessageForVipListAndNoLoginReturnsPleaseLoginMessage(
        string $accessLevel
    ): void {
        $subject = $this->createPartialMock(LegacyEvent::class, ['needsRegistration']);
        $subject->method('needsRegistration')->willReturn(true);

        self::assertSame(
            $this->translate('message_notLoggedIn'),
            $subject->canViewRegistrationsListMessage('list_vip_registrations', $accessLevel),
        );
    }

    /**
     * Data provider that returns all possible parameter combinations for
     * canViewRegistrationsList as called from canViewRegistrationsListMessage.
     *
     * @return string[][] the possible parameter combinations, will not be empty
     */
    public function registrationListParametersDataProvider(): array
    {
        return [
            'attendeesAndManagers' => ['list_registrations', 'attendees_and_managers'],
            'login' => ['list_registrations', 'login'],
            'attendeesAndManagersVip' => ['list_vip_registrations', 'attendees_and_managers'],
            'loginVip' => ['list_vip_registrations', 'login'],
        ];
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     *
     * @dataProvider registrationListParametersDataProvider
     */
    public function canViewRegistrationsListMessageWithLoginRoutesParameters(
        string $whichPlugin,
        string $accessLevel
    ): void {
        $subject = $this->createPartialMock(
            LegacyEvent::class,
            ['needsRegistration', 'canViewRegistrationsList'],
        );
        $subject->method('needsRegistration')->willReturn(true);
        $subject
            ->method('canViewRegistrationsList')
            ->with($whichPlugin, 0, 0, $accessLevel)
            ->willReturn(true);

        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $this->testingFramework->createAndLoginFrontEndUser();

        $subject->canViewRegistrationsListMessage($whichPlugin, $accessLevel);
    }

    /**
     * @test
     */
    public function canViewRegistrationsListMessageWithLoginAndAccessGrantedReturnsEmptyString(): void
    {
        $subject = $this->createPartialMock(
            LegacyEvent::class,
            ['needsRegistration', 'canViewRegistrationsList'],
        );
        $subject->method('needsRegistration')->willReturn(true);
        $subject->method('canViewRegistrationsList')->willReturn(true);

        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertSame(
            '',
            $subject->canViewRegistrationsListMessage('list_registrations'),
        );
    }

    /**
     * @test
     */
    public function canViewRegistrationsListMessageWithLoginAndAccessDeniedReturnsAccessDeniedMessage(): void
    {
        $subject = $this->createPartialMock(
            LegacyEvent::class,
            ['needsRegistration', 'canViewRegistrationsList'],
        );
        $subject->method('needsRegistration')->willReturn(true);
        $subject->method('canViewRegistrationsList')->willReturn(false);

        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);
        $this->testingFramework->createAndLoginFrontEndUser();

        self::assertSame(
            $this->translate('message_accessDenied'),
            $subject->canViewRegistrationsListMessage('list_registrations'),
        );
    }

    // Tests regarding the flag for organizers having been notified about enough attendees.

    /**
     * @test
     */
    public function haveOrganizersBeenNotifiedAboutEnoughAttendeesByDefaultReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->haveOrganizersBeenNotifiedAboutEnoughAttendees(),
        );
    }

    /**
     * @test
     */
    public function haveOrganizersBeenNotifiedAboutEnoughAttendeesReturnsTrueValueFromDatabase(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers_notified_about_minimum_reached' => 1],
        );
        $subject = new TestingLegacyEvent($uid);

        self::assertTrue(
            $subject->haveOrganizersBeenNotifiedAboutEnoughAttendees(),
        );
    }

    /**
     * @test
     */
    public function setOrganizersBeenNotifiedAboutEnoughAttendeesMarksItAsTrue(): void
    {
        $this->subject->setOrganizersBeenNotifiedAboutEnoughAttendees();

        self::assertTrue(
            $this->subject->haveOrganizersBeenNotifiedAboutEnoughAttendees(),
        );
    }

    // Tests regarding the flag for organizers having been notified about enough attendees.

    /**
     * @test
     */
    public function shouldMuteNotificationEmailsByDefaultReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->shouldMuteNotificationEmails(),
        );
    }

    /**
     * @test
     */
    public function shouldMuteNotificationEmailsReturnsTrueValueFromDatabase(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['mute_notification_emails' => 1],
        );
        $subject = new TestingLegacyEvent($uid);

        self::assertTrue(
            $subject->shouldMuteNotificationEmails(),
        );
    }

    /**
     * @test
     */
    public function muteNotificationEmailsSetsShouldMute(): void
    {
        $this->subject->muteNotificationEmails();

        self::assertTrue(
            $this->subject->shouldMuteNotificationEmails(),
        );
    }

    // Tests regarding the flag for automatic cancelation/confirmation

    /**
     * @test
     */
    public function shouldAutomaticallyConfirmOrCancelByDefaultReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->shouldAutomaticallyConfirmOrCancel(),
        );
    }

    /**
     * @test
     */
    public function shouldAutomaticallyConfirmOrCancelReturnsTrueValueFromDatabase(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['automatic_confirmation_cancelation' => 1],
        );
        $subject = new TestingLegacyEvent($uid);

        self::assertTrue(
            $subject->shouldAutomaticallyConfirmOrCancel(),
        );
    }

    // Tests concerning the price

    /**
     * @test
     */
    public function getPriceOnRequestByDefaultReturnsFalse(): void
    {
        self::assertFalse($this->subject->getPriceOnRequest());
    }

    /**
     * @test
     */
    public function getPriceOnRequestReturnsPriceOnRequest(): void
    {
        $this->subject->setRecordPropertyInteger('price_on_request', 1);

        self::assertTrue($this->subject->getPriceOnRequest());
    }

    /**
     * @test
     */
    public function setPriceOnRequestSetsPriceOnRequest(): void
    {
        $this->subject->setPriceOnRequest(true);

        self::assertTrue($this->subject->getPriceOnRequest());
    }

    /**
     * @test
     */
    public function getPriceOnRequestForEventDateReturnsFalseValueFromTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC, 'price_on_request' => false],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_DATE, 'topic' => $topicUid],
        );
        $date = new LegacyEvent($dateUid);

        self::assertFalse($date->getPriceOnRequest());
    }

    /**
     * @test
     */
    public function getPriceOnRequestForEventDateReturnsTrueValueFromTopic(): void
    {
        $topicUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC, 'price_on_request' => true],
        );
        $dateUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_EVENT_DATE, 'topic' => $topicUid],
        );
        $date = new LegacyEvent($dateUid);

        self::assertTrue($date->getPriceOnRequest());
    }

    /**
     * @test
     */
    public function getCurrentPriceRegularForZeroPriceReturnsForFree(): void
    {
        $this->subject->setRecordPropertyString('price_regular', '0.00');

        $result = $this->subject->getCurrentPriceRegular();

        self::assertSame($this->translate('message_forFree'), $result);
    }

    /**
     * @test
     */
    public function getCurrentPriceRegularForNonZeroPriceReturnsPrice(): void
    {
        $this->subject->setRecordPropertyString('price_regular', '123.45');

        $result = $this->subject->getCurrentPriceRegular();

        self::assertSame('€ 123,45', $result);
    }

    /**
     * @test
     */
    public function getCurrentPriceRegularForPriceOnRequestReturnsLocalizedString(): void
    {
        $this->subject->setRecordPropertyInteger('price_on_request', 1);
        $this->subject->setRecordPropertyString('price_regular', '123.45');

        $result = $this->subject->getCurrentPriceRegular();

        self::assertSame($this->translate('message_onRequest'), $result);
    }

    /**
     * @test
     */
    public function getCurrentPriceSpecialReturnsRegularNonZeroPrice(): void
    {
        $this->subject->setRecordPropertyString('price_regular', '57');
        $this->subject->setRecordPropertyString('price_special', '123.45');

        $result = $this->subject->getCurrentPriceSpecial();

        self::assertSame('€ 123,45', $result);
    }

    /**
     * @test
     */
    public function getCurrentPriceSpecialForPriceOnRequestReturnsLocalizedString(): void
    {
        $this->subject->setRecordPropertyInteger('price_on_request', 1);
        $this->subject->setRecordPropertyString('price_regular', '57');
        $this->subject->setRecordPropertyString('price_special', '123.45');

        $result = $this->subject->getCurrentPriceSpecial();

        self::assertSame($this->translate('message_onRequest'), $result);
    }
}
