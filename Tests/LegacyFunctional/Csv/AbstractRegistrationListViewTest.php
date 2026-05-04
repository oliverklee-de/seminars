<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\Csv;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Csv\AbstractRegistrationListView;
use OliverKlee\Seminars\Hooks\Interfaces\RegistrationListCsv;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Csv\AbstractRegistrationListView
 */
final class AbstractRegistrationListViewTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    /**
     * @var AbstractRegistrationListView&MockObject
     */
    private AbstractRegistrationListView $subject;

    private TestingFramework $testingFramework;

    private DummyConfiguration $configuration;

    /**
     * PID of the system folder in which we store our test data
     */
    private int $pageUid = 0;

    /**
     * UID of a test event record
     */
    private int $eventUid = 0;

    /**
     * @var list<non-empty-string>
     */
    public $frontEndUserFieldKeys = [];

    /**
     * @var list<non-empty-string>
     */
    public $registrationFieldKeys = [];

    /**
     * @var list<class-string>
     */
    private array $mockedClassNames = [];

    /**
     * backed-up extension configuration of the TYPO3 configuration variables
     *
     * @var array<string, mixed>
     */
    private array $extConfBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->extConfBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'] = [];

        $this->testingFramework = new TestingFramework();

        $configurationRegistry = ConfigurationRegistry::getInstance();
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);

        $this->pageUid = $this->testingFramework->createSystemFolder();
        $this->eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->pageUid,
                'begin_date' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ),
            ],
        );

        $subject = $this->getMockForAbstractClass(AbstractRegistrationListView::class);

        $testCase = $this;
        $subject
            ->method('getFrontEndUserFieldKeys')
            ->willReturnCallback(
                static fn (): array => $testCase->frontEndUserFieldKeys,
            );
        $subject
            ->method('getRegistrationFieldKeys')
            ->willReturnCallback(
                static fn (): array => $testCase->registrationFieldKeys,
            );

        $subject->setEventUid($this->eventUid);
        $this->subject = $subject;
    }

    protected function tearDown(): void
    {
        $this->purgeMockedInstances();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] = $this->extConfBackup;

        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    // Utility functions

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
    public function mockedInstancesListIsEmptyInitially(): void
    {
        self::assertEmpty($this->mockedClassNames);
    }

    /**
     * @test
     */
    public function addMockedInstanceAddsClassnameToList(): void
    {
        /** @var MockObject $mockedInstance */
        $mockedInstance = $this->createMock(\stdClass::class);
        $mockedClassName = \get_class($mockedInstance);

        $this->addMockedInstance($mockedClassName, $mockedInstance);
        // manually purge the Typo3 FIFO here, as purgeMockedInstances() is not tested yet
        GeneralUtility::makeInstance($mockedClassName);

        self::assertCount(1, $this->mockedClassNames);
        self::assertSame($mockedClassName, $this->mockedClassNames[0]);
    }

    /**
     * @test
     */
    public function addMockedInstanceAddsInstanceToTypo3InstanceBuffer(): void
    {
        /** @var MockObject $mockedInstance */
        $mockedInstance = $this->createMock(\stdClass::class);
        $mockedClassName = \get_class($mockedInstance);

        $this->addMockedInstance($mockedClassName, $mockedInstance);

        self::assertSame($mockedInstance, GeneralUtility::makeInstance($mockedClassName));
    }

    /**
     * @test
     */
    public function purgeMockedInstancesRemovesClassnameFromList(): void
    {
        /** @var MockObject $mockedInstance */
        $mockedInstance = $this->createMock(\stdClass::class);
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
        /** @var MockObject $mockedInstance */
        $mockedInstance = $this->createMock(\stdClass::class);
        $mockedClassName = \get_class($mockedInstance);
        $this->addMockedInstance($mockedClassName, $mockedInstance);

        $this->purgeMockedInstances();

        self::assertNotSame($mockedInstance, GeneralUtility::makeInstance($mockedClassName));
    }

    /**
     * @test
     */
    public function renderForNoPageAndNoEventThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $subject = $this->getMockForAbstractClass(AbstractRegistrationListView::class);

        self::assertSame(
            '',
            $subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderCanContainOneRegistrationUid(): void
    {
        $this->registrationFieldKeys = ['uid'];

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
    public function renderCanContainTwoRegistrationUids(): void
    {
        $this->registrationFieldKeys = ['uid'];

        $firstRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $secondRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect(
                    'date',
                    'timestamp',
                ) + 1,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $registrationsList = $this->subject->render();
        self::assertStringContainsString(
            (string)$firstRegistrationUid,
            $registrationsList,
        );
        self::assertStringContainsString(
            (string)$secondRegistrationUid,
            $registrationsList,
        );
    }

    /**
     * @test
     */
    public function renderCanContainNameOfUser(): void
    {
        $this->frontEndUserFieldKeys = ['name'];

        $frontEndUserUid = $this->testingFramework->createFrontEndUser('', ['name' => 'foo_user']);
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $frontEndUserUid,
            ],
        );

        self::assertStringContainsString(
            'foo_user',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderNotContainsUidOfRegistrationWithDeletedUser(): void
    {
        $this->registrationFieldKeys = ['uid'];

        $frontEndUserUid = $this->testingFramework->createFrontEndUser('', ['deleted' => 1]);
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $frontEndUserUid,
            ],
        );

        self::assertStringNotContainsString(
            (string)$registrationUid,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderNotContainsUidOfRegistrationWithInexistentUser(): void
    {
        $this->registrationFieldKeys = ['uid'];

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => 9999,
            ],
        );

        self::assertStringNotContainsString(
            (string)$registrationUid,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderSeparatesLinesWithCarriageReturnAndLineFeed(): void
    {
        $this->registrationFieldKeys = ['uid'];

        $firstRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => 1,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $secondRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => 2,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringContainsString(
            "\r\n" . $firstRegistrationUid . "\r\n" .
            $secondRegistrationUid . "\r\n",
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderHasResultThatEndsWithCarriageReturnAndLineFeed(): void
    {
        $this->registrationFieldKeys = ['uid'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertMatchesRegularExpression(
            '/\\r\\n$/',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderEscapesDoubleQuotes(): void
    {
        $this->registrationFieldKeys = ['uid', 'address'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'address' => 'foo " bar',
            ],
        );

        self::assertStringContainsString(
            'foo "" bar',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderNotEscapesRegularValues(): void
    {
        $this->registrationFieldKeys = ['address'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'address' => 'foo " bar',
            ],
        );

        self::assertStringNotContainsString(
            '"foo bar"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderWrapsValuesWithSemicolonsInDoubleQuotes(): void
    {
        $this->registrationFieldKeys = ['address'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'address' => 'foo ; bar',
            ],
        );

        self::assertStringContainsString(
            '"foo ; bar"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderWrapsValuesWithLineFeedsInDoubleQuotes(): void
    {
        $this->registrationFieldKeys = ['address'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'address' => "foo\nbar",
            ],
        );

        self::assertStringContainsString(
            "\"foo\nbar\"",
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderWrapsValuesWithDoubleQuotesInDoubleQuotes(): void
    {
        $this->registrationFieldKeys = ['address'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'address' => 'foo " bar',
            ],
        );

        self::assertStringContainsString(
            '"foo "" bar"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderSeparatesTwoValuesWithSemicolons(): void
    {
        $this->registrationFieldKeys = ['address', 'title'];

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
                'user' => $this->testingFramework->createFrontEndUser(),
                'address' => 'foo',
                'title' => 'test',
            ],
        );

        self::assertStringContainsString(
            'foo;test',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderDoesNotWrapHeadlineFieldsInDoubleQuotes(): void
    {
        $this->registrationFieldKeys = ['address'];

        $registrationsList = $this->subject->render();

        self::assertStringContainsString('tx_seminars_attendances.address', $registrationsList);
        self::assertStringNotContainsString('"tx_seminars_attendances.address"', $registrationsList);
    }

    /**
     * @test
     */
    public function renderSeparatesHeadlineFieldsWithSemicolons(): void
    {
        $this->registrationFieldKeys = ['address', 'title'];

        self::assertStringContainsString(
            'tx_seminars_attendances.address;tx_seminars_attendances.title',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForConfigurationAttendanceCsvFieldsEmptyDoesNotAddSemicolonOnEndOfHeadline(): void
    {
        $this->frontEndUserFieldKeys = ['name'];

        self::assertStringNotContainsString(
            'name;',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForConfigurationFeUserCsvFieldsEmptyDoesNotAddSemicolonAtBeginningOfHeadline(): void
    {
        $this->registrationFieldKeys = ['address'];

        self::assertStringNotContainsString(
            ';address',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForBothConfigurationFieldsNotEmptyAddsSemicolonBetweenConfigurationFields(): void
    {
        $this->registrationFieldKeys = ['address'];
        $this->frontEndUserFieldKeys = ['name'];

        self::assertStringContainsString('fe_users.name;tx_seminars_attendances.address', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderForBothConfigurationFieldsEmptyAndSeparatorEnabledReturnsSeparatorMarkerAndEmptyLine(): void
    {
        $this->configuration->setAsBoolean('addExcelSpecificSeparatorLineToCsv', true);

        self::assertSame(
            "sep=;\r\n\r\n",
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForBothConfigurationFieldsEmptyAndSeparatorDisabledReturnsEmptyLine(): void
    {
        $this->configuration->setAsBoolean('addExcelSpecificSeparatorLineToCsv', false);

        self::assertSame(
            "\r\n",
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderCallsHookAndReturnsModifiedValue(): void
    {
        $this->configuration->setAsBoolean('addExcelSpecificSeparatorLineToCsv', false);
        $renderResult = "\r\n";
        $modifiedResult = "modified CSV\r\n";

        $hook = $this->createMock(RegistrationListCsv::class);
        $hook
            ->expects(self::once())->method('modifyCsv')
            ->with($renderResult, $this->subject)
            ->willReturn($modifiedResult);

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][RegistrationListCsv::class][] = $hookClass;
        $this->addMockedInstance($hookClass, $hook);

        self::assertSame($modifiedResult, $this->subject->render());
    }
}
