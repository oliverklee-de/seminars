<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Csv;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Csv\DownloadRegistrationListView;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Csv\AbstractRegistrationListView
 * @covers \OliverKlee\Seminars\Csv\DownloadRegistrationListView
 */
final class DownloadRegistrationListViewTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private DownloadRegistrationListView $subject;

    private TestingFramework $testingFramework;

    private DummyConfiguration $configuration;

    /**
     * UID of a test event record
     */
    private int $eventUid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->getLanguageService()->includeLLFile('EXT:core/Resources/Private/Language/locallang_general.xlf');
        $this->getLanguageService()->includeLLFile('EXT:seminars/Resources/Private/Language/locallang_db.xlf');

        $this->testingFramework = new TestingFramework('tx_seminars');

        $configurationRegistry = ConfigurationRegistry::getInstance();
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);

        $pageUid = $this->testingFramework->createSystemFolder();
        $this->eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $pageUid,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ),
            ],
        );

        $this->subject = new DownloadRegistrationListView();
        $this->subject->setEventUid($this->eventUid);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function renderCanContainOneRegistrationUid(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringContainsString(
            (string)$registrationUid,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderContainsFrontEndUserFieldsForDownload(): void
    {
        $firstName = 'John';
        $lastName = 'Doe';

        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'first_name');
        $this->configuration->setAsString('fieldsFromFeUserForEmailCsv', 'last_name');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(
                    '',
                    ['first_name' => $firstName, 'last_name' => $lastName],
                ),
            ],
        );

        self::assertStringContainsString(
            $firstName,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderNotContainsFrontEndUserFieldsForEmail(): void
    {
        $firstName = 'John';
        $lastName = 'Doe';

        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'first_name');
        $this->configuration->setAsString('fieldsFromFeUserForEmailCsv', 'last_name');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(
                    '',
                    ['first_name' => $firstName, 'last_name' => $lastName],
                ),
            ],
        );

        self::assertStringNotContainsString(
            $lastName,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderContainsRegistrationFieldsForDownload(): void
    {
        $knownFrom = 'Google';
        $notes = 'Looking forward to the event!';

        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'known_from');
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'notes');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'known_from' => $knownFrom,
                'notes' => $notes,
            ],
        );

        self::assertStringContainsString(
            $knownFrom,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderNotContainsRegistrationFieldsForEmail(): void
    {
        $knownFrom = 'Google';
        $notes = 'Looking forward to the event!';

        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'known_from');
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'notes');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'known_from' => $knownFrom,
                'notes' => $notes,
            ],
        );

        self::assertStringNotContainsString(
            $notes,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForQueueRegistrationsDoesNotContainRegistrationOnQueue(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'uid');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'registration_queue' => true,
            ],
        );

        self::assertStringNotContainsString(
            (string)$registrationUid,
            $this->subject->render(),
        );
    }
}
