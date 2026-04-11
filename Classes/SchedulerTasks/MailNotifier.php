<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\SchedulerTasks;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\PageFinder;
use OliverKlee\Oelib\Email\SystemEmailFromBuilder;
use OliverKlee\Oelib\Interfaces\Configuration;
use OliverKlee\Oelib\Interfaces\MailRole;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\Csv\EmailRegistrationListView;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Email\EmailBuilder;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyOrganizer;
use OliverKlee\Seminars\Service\EmailService;
use OliverKlee\Seminars\Service\EventStatusService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * This class sends reminders to the organizers.
 */
class MailNotifier extends AbstractTask
{
    private const CSV_FILENAME = 'registrations.csv';

    /**
     * @var int<0, max>
     */
    protected int $configurationPageUid = 0;

    protected EventStatusService $eventStatusService;

    protected EmailService $emailService;

    protected EventMapper $eventMapper;

    protected RegistrationDigest $registrationDigest;

    private bool $dependenciesAreSetUp = false;

    /**
     * Sets up the dependencies (as we cannot use dependency injection on scheduler tasks).
     */
    protected function constituteDependencies(): void
    {
        if ($this->dependenciesAreSetUp) {
            return;
        }

        // This is necessary so that the configuration is fetched from the provided page UID early.
        $this->getConfiguration();
        $this->eventStatusService = GeneralUtility::makeInstance(EventStatusService::class);
        $this->emailService = GeneralUtility::makeInstance(EmailService::class);
        $this->eventMapper = MapperRegistry::get(EventMapper::class);
        $this->registrationDigest = GeneralUtility::makeInstance(RegistrationDigest::class);

        $this->dependenciesAreSetUp = true;
    }

    public function execute(): bool
    {
        if ($this->getConfigurationPageUid() <= 0) {
            return false;
        }

        $this->constituteDependencies();
        $this->executeAfterInitialization();

        return true;
    }

    /**
     * Executes the single steps.
     */
    protected function executeAfterInitialization(): void
    {
        $this->sendEventTakesPlaceReminders();
        $this->sendCancellationDeadlineReminders();
        $this->automaticallyChangeEventStatuses();
        $this->registrationDigest->execute();
    }

    /**
     * Sets the UID of the page with the TS configuration for this task.
     *
     * @param int<0, max> $pageUid
     */
    public function setConfigurationPageUid(int $pageUid): void
    {
        $this->configurationPageUid = $pageUid;
    }

    /**
     * @return int<0, max>
     */
    public function getConfigurationPageUid(): int
    {
        return $this->configurationPageUid;
    }

    /**
     * Sends event-takes-place reminders to the corresponding organizers and
     * commits the flag for this reminder being sent to the database.
     */
    public function sendEventTakesPlaceReminders(): void
    {
        foreach ($this->getEventsToSendEventTakesPlaceReminderFor() as $event) {
            $this->sendRemindersToOrganizers(
                $event,
                'email_eventTakesPlaceReminder',
            );
            $event->setEventTakesPlaceReminderSentFlag();
            $event->commitToDatabase();
        }
    }

    /**
     * Sends cancellation deadline reminders to the corresponding organizers and
     * commits the flag for this reminder being sent to the database.
     */
    public function sendCancellationDeadlineReminders(): void
    {
        foreach ($this->getEventsToSendCancellationDeadlineReminderFor() as $event) {
            $this->sendRemindersToOrganizers(
                $event,
                'email_cancelationDeadlineReminder',
            );
            $event->setCancelationDeadlineReminderSentFlag();
            $event->commitToDatabase();
        }
    }

    /**
     * Sends an email to the organizers of the provided event.
     *
     * @param LegacyEvent $event event for which to send the reminder to its organizers
     * @param non-empty-string $messageKey locallang key for the message content and the subject for the email to send
     */
    private function sendRemindersToOrganizers(LegacyEvent $event, string $messageKey): void
    {
        $replyTo = $event->getFirstOrganizer();
        $sender = $this->determineEmailSenderForEvent($event);
        $subject = $this->customizeMessage($messageKey . 'Subject', $event);
        /** @var string|null $attachmentBody */
        $attachmentBody = null;
        if ($this->shouldCsvFileBeAdded($event)) {
            $eventUid = $event->getUid();
            \assert($eventUid > 0);
            $attachmentBody = $this->getCsv($eventUid);
        }

        /** @var LegacyOrganizer $organizer */
        foreach ($event->getOrganizerBag() as $organizer) {
            $emailBuilder = GeneralUtility::makeInstance(EmailBuilder::class);
            $emailBuilder
                ->from($sender)
                ->to($organizer)
                ->subject($subject)
                ->text($this->customizeMessage($messageKey, $event, $organizer->getName()));
            $emailBuilder->replyTo($replyTo);
            if (\is_string($attachmentBody)) {
                $emailBuilder->attach($attachmentBody, 'text/csv', self::CSV_FILENAME);
            }

            $emailBuilder->build()->send();
        }
    }

    /**
     * Returns a `MailRole` with the default email data from the TYPO3 configuration if possible.
     *
     * Otherwise, returns the first organizer of the given event.
     */
    private function determineEmailSenderForEvent(LegacyEvent $event): MailRole
    {
        $systemEmailFromBuilder = GeneralUtility::makeInstance(SystemEmailFromBuilder::class);
        if ($systemEmailFromBuilder->canBuild()) {
            $sender = $systemEmailFromBuilder->build();
        } else {
            $sender = $event->getFirstOrganizer();
        }

        return $sender;
    }

    /**
     * Returns events in confirmed status which are about to take place and for
     * which no reminder has been sent yet.
     *
     * @return list<LegacyEvent> events for which to send the event-takes-place reminder to
     *               their organizers, will be empty if there are none
     */
    private function getEventsToSendEventTakesPlaceReminderFor(): array
    {
        $days = $this->getDaysBeforeBeginDate();
        if ($days === 0) {
            return [];
        }

        $result = [];

        $builder = $this->getSeminarBagBuilder(EventInterface::STATUS_CONFIRMED);
        $builder->limitToEventTakesPlaceReminderNotSent();
        $builder->limitToDaysBeforeBeginDate($days);
        foreach ($builder->build() as $event) {
            if ($event instanceof LegacyEvent) {
                $result[] = $event;
            }
        }

        return $result;
    }

    /**
     * Returns events in planned status for which the cancellation deadline has
     * just passed and for which no reminder has been sent yet.
     *
     * @return list<LegacyEvent> events for which to send the cancellation reminder to their
     *               organizers, will be empty if there are none
     */
    private function getEventsToSendCancellationDeadlineReminderFor(): array
    {
        if (!$this->getConfiguration()->getAsBoolean('sendCancelationDeadlineReminder')) {
            return [];
        }

        $result = [];

        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $builder = $this->getSeminarBagBuilder(EventInterface::STATUS_PLANNED);
        $builder->limitToCancelationDeadlineReminderNotSent();
        foreach ($builder->build() as $event) {
            if ($event instanceof LegacyEvent && $event->getCancelationDeadline() < $now) {
                $result[] = $event;
            }
        }

        return $result;
    }

    /**
     * Returns the TS setup configuration value of
     * 'sendEventTakesPlaceReminderDaysBeforeBeginDate'.
     *
     * @return int<0, max> how many days before an event the event-takes-place
     *                 reminder should be sent, will be > 0 if this option is
     *                 enabled, zero disables sending the reminder
     */
    private function getDaysBeforeBeginDate(): int
    {
        return $this->getConfiguration()->getAsNonNegativeInteger('sendEventTakesPlaceReminderDaysBeforeBeginDate');
    }

    /**
     * Returns a seminar bag builder already limited to upcoming events with a begin date and the given status.
     *
     * @param EventInterface::STATUS_* $status status to limit the builder to
     *
     * @return EventBagBuilder builder for the seminar bag
     */
    private function getSeminarBagBuilder(int $status): EventBagBuilder
    {
        $builder = GeneralUtility::makeInstance(EventBagBuilder::class);
        $builder->setTimeFrame('upcomingWithBeginDate');
        $builder->limitToStatus($status);

        return $builder;
    }

    /**
     * Returns the CSV output for the list of registrations for the event with the provided UID.
     *
     * @param positive-int $eventUid UID of the event to create the output for
     */
    private function getCsv(int $eventUid): string
    {
        $csvCreator = GeneralUtility::makeInstance(EmailRegistrationListView::class);
        $csvCreator->setEventUid($eventUid);

        return $csvCreator->render();
    }

    /**
     * Returns localized email content customized for the provided event and
     * the provided organizer.
     *
     * @param non-empty-string $locallangKey locallang key for the text in which to replace keywords beginning
     *        with "%" by the event's data
     * @param LegacyEvent $event
     *        event for which to customize the text
     * @param string $organizerName name of the organizer, may be empty if no organizer name needs to be inserted
     *        in the text
     *
     * @return string the localized email content, will not be empty
     */
    private function customizeMessage(string $locallangKey, LegacyEvent $event, string $organizerName = ''): string
    {
        $result = LocalizationUtility::translate($locallangKey, 'seminars');
        \assert(is_string($result));

        foreach (
            [
                '%begin_date' => $this->getDate($event->getBeginDateAsTimestamp()),
                '%days' => $this->getDaysBeforeBeginDate(),
                '%event' => $event->getTitle(),
                '%organizer' => $organizerName,
                '%registrations' => $event->getAttendances(),
                '%uid' => $event->getUid(),
            ] as $search => $replace
        ) {
            $result = \str_replace($search, (string)$replace, $result);
        }

        return $result;
    }

    /**
     * Returns a timestamp formated as a localized date.
     *
     * @param int<0, max> $timestamp
     */
    private function getDate(int $timestamp): string
    {
        $format = LocalizationUtility::translate('dateFormat', 'seminars');
        \assert(\is_string($format));

        return \date($format, $timestamp);
    }

    /**
     * Checks whether the CSV file should be added to the email.
     *
     * @param LegacyEvent $event the event to send the email for
     */
    private function shouldCsvFileBeAdded(LegacyEvent $event): bool
    {
        return $this->getConfiguration()->getAsBoolean('addRegistrationCsvToOrganizerReminderMail')
            && $event->hasAttendances();
    }

    /**
     * Automatically changes the status for events for which this is enabled.
     *
     * @throws \UnexpectedValueException
     */
    public function automaticallyChangeEventStatuses(): void
    {
        $this->constituteDependencies();

        foreach ($this->eventMapper->findForAutomaticStatusChange() as $event) {
            $statusWasChanged = $this->eventStatusService->updateStatusAndSave($event);
            if (!$statusWasChanged) {
                continue;
            }

            if ($event->isConfirmed()) {
                $subject = LocalizationUtility::translate('email-event-confirmed-subject', 'seminars');
                $body = LocalizationUtility::translate('email-event-confirmed-body', 'seminars');
            } elseif ($event->isCanceled()) {
                $subject = LocalizationUtility::translate('email-event-canceled-subject', 'seminars');
                $body = LocalizationUtility::translate('email-event-canceled-body', 'seminars');
            } else {
                throw new \UnexpectedValueException(
                    'Event status for event #' . $event->getUid() . ' was still "planned" after the status change.',
                    1457982810,
                );
            }
            \assert(\is_string($subject));
            \assert(\is_string($body));

            $this->emailService->sendEmailToAttendees($event, $subject, $body);
        }
    }

    protected function getConfiguration(): Configuration
    {
        $pageUid = $this->getConfigurationPageUid();
        if ($pageUid > 0) {
            PageFinder::getInstance()->setPageUid($pageUid);
        }

        return ConfigurationRegistry::get('plugin.tx_seminars');
    }
}
