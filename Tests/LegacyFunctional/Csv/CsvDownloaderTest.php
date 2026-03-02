<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\Csv;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Csv\CsvDownloader;
use OliverKlee\Seminars\Tests\Support\BackEndTestsTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Csv\CsvDownloader
 */
final class CsvDownloaderTest extends FunctionalTestCase
{
    use BackEndTestsTrait;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private CsvDownloader $subject;

    private TestingFramework $testingFramework;

    /**
     * PID of the system folder in which we store our test data
     *
     * @var positive-int
     */
    private int $pid;

    /**
     * UID of a test event record
     *
     * @var positive-int
     */
    private int $eventUid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unifyTestingEnvironment();

        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->pid = $this->testingFramework->createSystemFolder();
        $this->eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->pid,
                'begin_date' => (int)GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp'),
            ],
        );

        $this->subject = new CsvDownloader();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();
        $this->restoreOriginalEnvironment();

        parent::tearDown();
    }

    // Tests for the CSV export of registrations.

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainOneRegistrationUid(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringContainsString(
            (string)$registrationUid,
            $this->subject->createListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainOneRegistrationUidOfHiddenEvent(): void
    {
        $this->testingFramework->changeRecord('tx_seminars_seminars', $this->eventUid, ['hidden' => 1]);

        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringContainsString(
            (string)$registrationUid,
            $this->subject->createListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainOneRegistrationUidOfEventWithPastEndTime(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->eventUid,
            [
                'endtime' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') - 1000,
            ],
        );

        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        self::assertStringContainsString(
            (string)$registrationUid,
            $this->subject->createListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainLocalizedRegisteredThemselves(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'registered_themselves');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
                'registered_themselves' => 1,
            ],
        );

        self::assertStringContainsString(
            'tx_seminars_attendances.registered_themselves',
            $this->subject->createListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainLocalizedCompanyHeading(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'company');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
                'company' => 'foo',
            ],
        );

        self::assertStringContainsString(
            'tx_seminars_attendances.company',
            $this->subject->createListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainCompanyContent(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'company');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
                'company' => 'foo bar inc.',
            ],
        );

        self::assertStringContainsString(
            'foo bar inc.',
            $this->subject->createListOfRegistrations($this->eventUid),
        );
    }

    // Tests concerning the main function

    /**
     * @test
     */
    public function mainCanExportOneRegistrationUid(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $_GET['eventUid'] = $this->eventUid;

        self::assertStringContainsString(
            (string)$registrationUid,
            $this->subject->main(),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsCanContainTwoRegistrationUids(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

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
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + 1,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $registrationsList
            = $this->subject->createListOfRegistrations($this->eventUid);

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
    public function mainKeepsRegistrationDataInUtf8(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'title');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'pid' => $this->pid,
                'title' => 'Schöne Bären führen',
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $_GET['eventUid'] = $this->eventUid;

        self::assertStringContainsString(
            'Schöne Bären führen',
            $this->subject->main(),
        );
    }

    // Tests concerning createAndOutputListOfRegistrations

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsCanContainTwoRegistrationUids(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

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
                'crdate' => (int)GeneralUtility::makeInstance(Context::class)
                        ->getPropertyFromAspect('date', 'timestamp') + 1,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );

        $registrationsList = $this->subject->createAndOutputListOfRegistrations($this->eventUid);

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
    public function createAndOutputListOfRegistrationsCanContainNameOfUser(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'name');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', '');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsDoesNotContainUidOfRegistrationWithDeletedUser(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsDoesNotContainUidOfRegistrationWithInexistentUser(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsSeparatesLinesWithCarriageReturnAndLineFeed(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsHasResultThatEndsWithCarriageReturnAndLineFeed(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsEscapesDoubleQuotes(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid,address');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsDoesNotEscapeRegularValues(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsWrapsValuesWithSemicolonsInDoubleQuotes(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsWrapsValuesWithLineFeedsInDoubleQuotes(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsWrapsValuesWithDoubleQuotesInDoubleQuotes(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsSeparatesTwoValuesWithSemicolons(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address,title');

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
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsDoesNotWrapHeaderFieldsInDoubleQuotes(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address');

        $registrationsList = $this->subject->createAndOutputListOfRegistrations($this->eventUid);
        self::assertStringContainsString('tx_seminars_attendances.address', $registrationsList);
        self::assertStringNotContainsString('"tx_seminars_attendances.address"', $registrationsList);
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsSeparatesHeaderFieldsWithSemicolons(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address,title');

        self::assertStringContainsString(
            'tx_seminars_attendances.address;tx_seminars_attendances.title',
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsForConfigurationAttendanceCsvFieldsEmptyDoesNotAddSemicolonOnEndOfHeader(
    ): void {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', '');
        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'name');

        self::assertStringNotContainsString(
            'name;',
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsForConfigurationFeUserCsvFieldsEmptyDoesNotAddSemicolonAtBeginningOfHeader(
    ): void {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'address');
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');

        self::assertStringNotContainsString(
            ';address',
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createListOfRegistrationsForBothConfigurationFieldsEmptyReturnsCrLf(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', '');
        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');

        self::assertSame(
            "\r\n",
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsForNoEventUidGivenThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('No event UID or page UID set');
        $this->expectExceptionCode(1390320210);

        $this->subject->createAndOutputListOfRegistrations();
    }

    // Tests concerning the export mode and the configuration

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsForWebModeNotUsesUserFieldsFromEmailConfiguration(): void
    {
        $this->configuration->setAsString('fieldsFromFeUserForEmailCsv', 'email');
        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'name');

        $frontEndUserUid = $this->testingFramework->createFrontEndUser('', ['email' => 'foo@bar.com']);
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            ['seminar' => $this->eventUid, 'user' => $frontEndUserUid],
        );

        self::assertStringNotContainsString(
            'foo@bar.com',
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }

    /**
     * @test
     */
    public function createAndOutputListOfRegistrationsForWebModeDoesNotExportWaitingListRegistrations(): void
    {
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'uid');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
            ],
        );
        $queueUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(),
                'registration_queue' => 1,
            ],
        );

        self::assertStringNotContainsString(
            (string)$queueUid,
            $this->subject->createAndOutputListOfRegistrations($this->eventUid),
        );
    }
}
