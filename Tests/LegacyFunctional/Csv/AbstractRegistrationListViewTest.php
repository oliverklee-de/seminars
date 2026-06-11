<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Csv;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Csv\AbstractRegistrationListView;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Csv\AbstractRegistrationListView
 */
final class AbstractRegistrationListViewTest extends FunctionalTestCase
{
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

    /**
     * @var AbstractRegistrationListView&MockObject
     */
    private AbstractRegistrationListView $subject;

    private TestingFramework $testingFramework;

    private DummyConfiguration $configuration;

    private int $nowAsUnixTimestamp;

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

    protected function setUp(): void
    {
        parent::setUp();

        $now = new \DateTimeImmutable('2018-04-26 12:42:23');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect($now));
        $nowAsUnixTimestamp = $now->getTimestamp();
        \assert($nowAsUnixTimestamp > 0);
        $this->nowAsUnixTimestamp = $nowAsUnixTimestamp;

        $this->testingFramework = $this->get(TestingFramework::class);

        $configurationRegistry = $this->get(ConfigurationRegistry::class);
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);

        $pageUid = $this->testingFramework->createSystemFolder();
        $this->eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $pageUid,
                'begin_date' => $this->nowAsUnixTimestamp,
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
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $secondRegistrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => $this->nowAsUnixTimestamp + 1,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
                'crdate' => $this->nowAsUnixTimestamp,
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
}
