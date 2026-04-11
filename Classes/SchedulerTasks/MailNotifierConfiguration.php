<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\SchedulerTasks;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * This is the configuration for the email notifier task.
 */
class MailNotifierConfiguration extends AbstractAdditionalFieldProvider
{
    private const LABEL_PREFIX = 'LLL:EXT:seminars/Resources/Private/Language/locallang.xlf:';

    /**
     * Gets additional fields to render in the form to add/edit a task
     *
     * @param array<string, string> $taskInfo Values of the fields from the add/edit task form
     * @param AbstractTask|null $task The task object being edited. Null when adding a task!
     * @param SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     *
     * @return array{"task-page-uid": array{code: string, label: string}}
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $pageUid = $task instanceof MailNotifier ? (string)$task->getConfigurationPageUid() : '';
        $taskInfo['seminars_configurationPageUid'] = $pageUid;

        $fieldId = 'task-page-uid';
        $fieldCode = '<input type="text" name="tx_scheduler[seminars_configurationPageUid]" id="'
            . $fieldId . '" value="' . $pageUid . '" size="4" />';

        return [
            $fieldId => [
                'code' => $fieldCode,
                'label' => self::LABEL_PREFIX . 'schedulerTasks.fields.page-uid',
            ],
        ];
    }

    /**
     * Validates the additional field values.
     *
     * @param array<string, string> $submittedData an array containing the data submitted by the add/edit task form
     * @param SchedulerModuleController $schedulerModule reference to the scheduler backend module
     *
     * @return bool true if validation was OK (or selected class is not relevant), false otherwise
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        $pageUid = (int)$submittedData['seminars_configurationPageUid'];
        $submittedData['seminars_configurationPageUid'] = $pageUid;

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $pageWithUidExist = $connection->count('*', 'pages', ['uid' => $pageUid]) > 0;
        $hasPageUid = $pageUid > 0 && $pageWithUidExist;
        if ($hasPageUid) {
            return true;
        }

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $severity = ContextualFeedbackSeverity::ERROR;
        } else {
            $severity = AbstractMessage::ERROR;
        }
        $message = $this->getLanguageService()->sL(self::LABEL_PREFIX . 'schedulerTasks.errors.page-uid');
        $this->addMessage($message, $severity);

        return false;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Takes care of saving the additional fields' values in the task.
     *
     * @param array<string, string> $submittedData an array containing the data submitted by the add/edit task form
     * @param AbstractTask $task the task that is being configured
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        $pageUid = (int)($submittedData['seminars_configurationPageUid'] ?? 0);
        if ($pageUid > 0 && $task instanceof MailNotifier) {
            $task->setConfigurationPageUid($pageUid);
        }
    }
}
