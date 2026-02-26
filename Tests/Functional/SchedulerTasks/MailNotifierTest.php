<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\SchedulerTasks;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\SchedulerTasks\MailNotifier;
use OliverKlee\Seminars\SchedulerTasks\RegistrationDigest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\SchedulerTasks\MailNotifier
 */
final class MailNotifierTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['typo3/cms-scheduler'];

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private MailNotifier $subject;

    /**
     * @var RegistrationDigest&MockObject
     */
    private RegistrationDigest $registrationDigestMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        ConfigurationRegistry::getInstance()->set('plugin', new DummyConfiguration());
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', new DummyConfiguration());

        $this->registrationDigestMock = $this->createMock(RegistrationDigest::class);
        GeneralUtility::setSingletonInstance(RegistrationDigest::class, $this->registrationDigestMock);

        $this->subject = new MailNotifier();
    }

    protected function tearDown(): void
    {
        ConfigurationRegistry::purgeInstance();
        MapperRegistry::purgeInstance();
        GeneralUtility::resetSingletonInstances([]);

        parent::tearDown();
    }

    // Basic tests

    /**
     * @test
     */
    public function classIsSchedulerTask(): void
    {
        self::assertInstanceOf(AbstractTask::class, $this->subject);
    }

    /**
     * @test
     */
    public function setConfigurationPageUidSetsConfigurationPageUid(): void
    {
        $uid = 42;
        $this->subject->setConfigurationPageUid($uid);

        $result = $this->subject->getConfigurationPageUid();

        self::assertSame($uid, $result);
    }

    /**
     * @test
     */
    public function executeWithoutPageConfigurationReturnsFalse(): void
    {
        $result = (new MailNotifier())->execute();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function executeWithZeroPageConfigurationReturnsFalse(): void
    {
        $subject = new MailNotifier();
        $subject->setConfigurationPageUid(0);

        $result = $subject->execute();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function executeWithPageConfigurationReturnsTrue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');

        $subject = new MailNotifier();
        $subject->setConfigurationPageUid(1);

        $result = $subject->execute();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function executeWithPageConfigurationCallsAllSeparateSteps(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $subject = $this->createPartialMock(
            MailNotifier::class,
            ['sendEventTakesPlaceReminders', 'sendCancellationDeadlineReminders', 'automaticallyChangeEventStatuses'],
        );
        $subject->setConfigurationPageUid(1);

        $subject->expects(self::once())->method('sendEventTakesPlaceReminders');
        $subject->expects(self::once())->method('sendCancellationDeadlineReminders');
        $subject->expects(self::once())->method('automaticallyChangeEventStatuses');

        $subject->execute();
    }

    /**
     * @test
     */
    public function executeWithoutPageConfigurationNotCallsAnySeparateStep(): void
    {
        $subject = $this->createPartialMock(
            MailNotifier::class,
            ['sendEventTakesPlaceReminders', 'sendCancellationDeadlineReminders', 'automaticallyChangeEventStatuses'],
        );
        $subject->setConfigurationPageUid(0);

        $subject->expects(self::never())->method('sendEventTakesPlaceReminders');
        $subject->expects(self::never())->method('sendCancellationDeadlineReminders');
        $subject->expects(self::never())->method('automaticallyChangeEventStatuses');

        $subject->execute();
    }

    /**
     * @test
     */
    public function executeWithPageConfigurationExecutesRegistrationDigest(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $subject = $this->createPartialMock(
            MailNotifier::class,
            ['sendEventTakesPlaceReminders', 'sendCancellationDeadlineReminders', 'automaticallyChangeEventStatuses'],
        );
        $subject->setConfigurationPageUid(1);
        $this->registrationDigestMock->expects(self::once())->method('execute');

        $subject->execute();
    }
}
