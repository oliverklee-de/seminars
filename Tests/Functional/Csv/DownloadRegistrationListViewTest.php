<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Csv;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Csv\DownloadRegistrationListView;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Csv\AbstractRegistrationListView
 * @covers \OliverKlee\Seminars\Csv\DownloadRegistrationListView
 */
final class DownloadRegistrationListViewTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private DownloadRegistrationListView $subject;

    private DummyConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $configurationRegistry = ConfigurationRegistry::getInstance();
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);

        $this->subject = new DownloadRegistrationListView();
    }

    /**
     * @test
     */
    public function renderForEventWithoutRegistrationsHasHeadersOnly(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithoutRegistrations.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        self::assertStringContainsString('tx_seminars_attendances.uid', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderCanContainOneRegistrationUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithRegistration.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromFeUserForCsv', '');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');

        self::assertStringContainsString('1', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderContainsFrontEndUserFieldsForDownload(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithRegistration.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'first_name');

        self::assertStringContainsString('John', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderDoesNotContainFrontEndUserFieldsForEmail(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithRegistration.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'first_name');
        $this->configuration->setAsString('fieldsFromFeUserForEmailCsv', 'last_name');

        self::assertStringNotContainsString('Doe', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderContainsRegistrationFieldsForDownload(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithRegistration.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'known_from');
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'notes');

        self::assertStringContainsString('Google', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderDoesNotContainRegistrationFieldsForEmail(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithRegistration.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'known_from');
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'notes');

        self::assertStringNotContainsString('Looking forward to the event!', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderDoesNotContainRegistrationOnQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/EventWithQueueRegistration.csv');
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromAttendanceForCsv', 'uid');
        $this->configuration->setAsString('fieldsFromAttendanceForEmailCsv', 'uid');

        self::assertStringNotContainsString('1', $this->subject->render());
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function unavailableUserDataProvider(): array
    {
        return [
            'deleted' => ['EventWithRegistrationWithDeletedUser.csv'],
            'disabled' => ['EventWithRegistrationWithDisabledUser.csv'],
            'missing' => ['EventWithRegistrationWithMissingUser.csv'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $fixtureName
     *
     * @dataProvider unavailableUserDataProvider
     */
    public function renderForUnavailableUserRecordsSkipsUnavailableUsers(string $fixtureName): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DownloadRegistrationListView/' . $fixtureName);
        $this->subject->setPageUid(1);

        $this->configuration->setAsString('fieldsFromFeUserForCsv', 'uid');
        $this->configuration->setAsString('fieldsFromAttendanceForCsv', '');

        self::assertSame("fe_users.uid\r\n", $this->subject->render());
    }
}
