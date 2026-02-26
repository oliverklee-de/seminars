<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\SchedulerTasks;

use OliverKlee\Seminars\SchedulerTasks\MailNotifier;
use OliverKlee\Seminars\SchedulerTasks\MailNotifierConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\SchedulerTasks\MailNotifierConfiguration
 */
final class MailNotifierConfigurationTest extends FunctionalTestCase
{
    private const LABEL_PREFIX = 'LLL:EXT:seminars/Resources/Private/Language/locallang.xlf:';

    protected array $coreExtensionsToLoad = ['typo3/cms-scheduler'];

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private MailNotifierConfiguration $subject;

    /**
     * @var SchedulerModuleController&MockObject
     */
    private SchedulerModuleController $moduleController;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $this->moduleController = $this->createMock(SchedulerModuleController::class);

        $this->subject = new MailNotifierConfiguration();
    }

    private function getFlashMessageQueue(): FlashMessageQueue
    {
        return GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier();
    }

    /**
     * @test
     */
    public function getAdditionalFieldsInitiallyReturnsEmptyField(): void
    {
        $taskInfo = [];
        $result = $this->subject->getAdditionalFields($taskInfo, null, $this->moduleController);

        self::assertSame(
            [
                'task-page-uid' => [
                    'code' => '<input type="text" name="tx_scheduler[seminars_configurationPageUid]" '
                        . 'id="task-page-uid" value="" size="4" />',
                    'label' => self::LABEL_PREFIX . 'schedulerTasks.fields.page-uid',
                ],
            ],
            $result,
        );
    }

    /**
     * @test
     */
    public function getAdditionalFieldsForTaskWithPageUidReturnsFieldWithUid(): void
    {
        $taskInfo = [];
        $uid = 112;
        $task = new MailNotifier();
        $task->setConfigurationPageUid($uid);

        $result = $this->subject->getAdditionalFields($taskInfo, $task, $this->moduleController);

        self::assertSame(
            [
                'task-page-uid' => [
                    'code' => '<input type="text" name="tx_scheduler[seminars_configurationPageUid]" '
                        . 'id="task-page-uid" value="' . $uid . '" size="4" />',
                    'label' => self::LABEL_PREFIX . 'schedulerTasks.fields.page-uid',
                ],
            ],
            $result,
        );
    }

    /**
     * @test
     */
    public function validateAdditionalFieldsForUidOfExistingPageReturnsTrue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $submittedData = ['seminars_configurationPageUid' => '1'];

        $result = $this->subject->validateAdditionalFields($submittedData, $this->moduleController);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function validateAdditionalFieldsForUidOfExistingPageNotAddsErrorMessage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $submittedData = ['seminars_configurationPageUid' => '1'];

        $this->subject->validateAdditionalFields($submittedData, $this->moduleController);

        self::assertCount(0, $this->getFlashMessageQueue());
    }

    /**
     * @test
     */
    public function validateAdditionalFieldsForUidOfInexistentPageReturnsFalse(): void
    {
        $submittedData = ['seminars_configurationPageUid' => '2'];

        $result = $this->subject->validateAdditionalFields($submittedData, $this->moduleController);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function validateAdditionalFieldsForUidOfInexistentPageAddsErrorMessage(): void
    {
        $submittedData = ['seminars_configurationPageUid' => '2'];

        $this->subject->validateAdditionalFields($submittedData, $this->moduleController);

        self::assertCount(1, $this->getFlashMessageQueue()->getAllMessages(FlashMessage::ERROR));
    }

    /**
     * @test
     */
    public function validateAdditionalFieldsForZeroUidReturnsFalse(): void
    {
        $submittedData = ['seminars_configurationPageUid' => '0'];

        $result = $this->subject->validateAdditionalFields($submittedData, $this->moduleController);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function validateAdditionalFieldsForZeroUidAddsErrorMessage(): void
    {
        $submittedData = ['seminars_configurationPageUid' => '0'];

        $this->subject->validateAdditionalFields($submittedData, $this->moduleController);

        self::assertCount(1, $this->getFlashMessageQueue()->getAllMessages(FlashMessage::ERROR));
    }

    /**
     * @test
     */
    public function saveAdditionalFieldsSavesIntegerPageUidToTask(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MailNotifierConfiguration.csv');
        $pageUid = 1;
        $submittedData = ['seminars_configurationPageUid' => (string)$pageUid];

        $task = $this->createMock(MailNotifier::class);
        $task->expects(self::once())->method('setConfigurationPageUid')->with($pageUid);

        $this->subject->saveAdditionalFields($submittedData, $task);
    }
}
