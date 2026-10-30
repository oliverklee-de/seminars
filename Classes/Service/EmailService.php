<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Service;

use OliverKlee\Oelib\Email\SystemEmailFromBuilder;
use OliverKlee\Oelib\Interfaces\MailRole;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Organizer;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Email\EmailBuilder;
use OliverKlee\Seminars\Email\SalutationBuilder;
use OliverKlee\Seminars\Model\Event;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class takes care of sending emails related to events and registrations.
 */
class EmailService implements SingletonInterface
{
    private SalutationBuilder $salutationBuilder;

    private EventRepository $eventRepository;

    private RegistrationRepository $registrationRepository;

    public function __construct(
        SalutationBuilder $salutationBuilder,
        EventRepository $eventRepository,
        RegistrationRepository $registrationRepository
    ) {
        $this->salutationBuilder = $salutationBuilder;
        $this->registrationRepository = $registrationRepository;
        $this->eventRepository = $eventRepository;
    }

    /**
     * Sends an email to of registered users of the given event.
     *
     * @param string $body can contain %salutation which will expand to a full salutation with the user's name
     */
    public function sendEmailToAttendees(Event $legacyEvent, string $subject, string $body): void
    {
        $eventUid = $legacyEvent->getUid();
        assert($eventUid > 0);
        $event = $this->eventRepository->findByUid($eventUid);
        assert($event instanceof EventDateInterface);

        $sender = $this->determineEmailSenderForEvent($event);
        $firstOrganizer = $event->getFirstOrganizer();
        $registrations = $this->registrationRepository->findRegularRegistrationsByEvent($eventUid);
        foreach ($registrations as $registration) {
            $user = $registration->getUser();
            if (!($user instanceof FrontendUser)) {
                continue;
            }
            try {
                $user->getValidEmail();
            } catch (\UnexpectedValueException $exception) {
                continue;
            }

            GeneralUtility::makeInstance(EmailBuilder::class)
                ->to($user)
                ->from($sender)
                ->replyTo($firstOrganizer)
                ->subject($this->replaceMarkers($subject, $event, $user))
                ->text($this->buildMessageBody($body, $event, $user))
                ->build()->send();
        }
    }

    /**
     * Returns a `MailRole` with the default email data from the TYPO3 configuration if possible.
     *
     * Otherwise, returns the first organizer of the given event.
     */
    private function determineEmailSenderForEvent(EventDateInterface $event): MailRole
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
     * Builds the message body (including the email footer).
     */
    protected function buildMessageBody(string $rawBody, EventDateInterface $event, FrontendUser $user): string
    {
        $bodyWithFooter = $this->replaceMarkers($rawBody, $event, $user);
        $footer = $event->getFirstOrganizer()->getEmailFooter();
        if ($footer !== '') {
            $bodyWithFooter .= "\n-- \n" . $footer;
        }

        return $bodyWithFooter;
    }

    /**
     * Replaces markers in $textWithMarkers.
     *
     * The following markers will get replaced:
     *
     * `%salutation`
     * `%userName`
     * `%eventTitle`
     * `%eventDate`
     */
    protected function replaceMarkers(string $textWithMarkers, EventDateInterface $event, FrontendUser $user): string
    {
        $markers = [
            '%salutation' => $this->salutationBuilder->getSalutation($user),
            '%userName' => $user->getName(),
            '%eventTitle' => $event->getDisplayTitle(),
            '%eventDate' => $this->renderEventDate($event),
        ];

        return str_replace(array_keys($markers), $markers, $textWithMarkers);
    }

    private function renderEventDate(EventDateInterface $event): string
    {
        $start = $event->getStart();
        if (!($start instanceof \DateTime)) {
            return '';
        }

        $end = $event->getEnd();
        if (!($end instanceof \DateTime) || $this->isSameDay($start, $end)) {
            return $this->formatDate($start);
        }

        $rangeWithPlaceholders = LocalizationUtility::translate('range', 'seminars');
        \assert(is_string($rangeWithPlaceholders));

        return sprintf($rangeWithPlaceholders, $this->formatDate($start), $this->formatDate($end));
    }

    private function isSameDay(\DateTime $start, \DateTime $end): bool
    {
        return $start->format('Y-m-d') === $end->format('Y-m-d');
    }

    private function formatDate(\DateTime $date): string
    {
        $format = LocalizationUtility::translate('dateFormat', 'seminars');
        \assert(\is_string($format));

        return $date->format($format);
    }

    private function formatTime(\DateTime $date): string
    {
        $hours = LocalizationUtility::translate('label_hours', 'seminars');
        \assert(\is_string($hours));

        return $date->format($this->getTimeFormat()) . ' ' . $hours;
    }

    private function getTimeFormat(): string
    {
        $format = LocalizationUtility::translate('timeFormat', 'seminars');
        \assert(\is_string($format));

        return $format;
    }

    /**
     * Sends an email to the regular attendees of the given event using the provided email subject and message body.
     */
    public function sendPlainTextEmailToRegularAttendees(
        EventDateInterface $event,
        string $subject,
        string $rawBody
    ): void {
        $organizer = $event->getFirstOrganizer();
        $sender = $this->determineEmailSenderForEvent($event);
        $eventUid = $event->getUid();
        \assert(\is_int($eventUid));

        foreach ($this->registrationRepository->findRegularRegistrationsByEvent($eventUid) as $registration) {
            $user = $registration->getUser();
            if (!($user instanceof FrontendUser) || $user->getEmail() === '') {
                continue;
            }

            $email = GeneralUtility::makeInstance(EmailBuilder::class)->from($sender)
                ->replyTo($organizer)
                ->subject($subject)
                ->to($user)
                ->text($this->appendEmailFooterIfProvided($rawBody, $organizer))
                ->build();

            $email->send();
        }

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $severity = ContextualFeedbackSeverity::OK;
        } else {
            $severity = AbstractMessage::OK;
        }

        $message = LocalizationUtility::translate('message_emailToAttendeesSent', 'seminars');
        \assert(\is_string($message));
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', $severity, true);
        $this->addFlashMessage($flashMessage);
    }

    private function addFlashMessage(FlashMessage $flashMessage): void
    {
        $defaultFlashMessageQueue = GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier('extbase.flashmessages.tx_seminars_web_seminarsevents');
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    private function appendEmailFooterIfProvided(string $rawBody, Organizer $sender): string
    {
        $messageFooter = $sender->hasEmailFooter() ? "\n-- \n" . $sender->getEmailFooter() : '';

        return $rawBody . $messageFooter;
    }

    /**
     * Creates an email introduction with the given event's title, date and time prepended with the given introduction
     * string.
     *
     * @param non-empty-string $introductionBegin
     *        the start of the introduction, must contain %s as place to fill the title of the event in
     */
    public function createIntroduction(string $introductionBegin, EventDateInterface $event): string
    {
        $result = \sprintf($introductionBegin, $event->getDisplayTitle());
        $start = $event->getStart();
        if (!($start instanceof \DateTime)) {
            return $result;
        }

        $eventDateLabel = LocalizationUtility::translate('email_eventDate', 'seminars');
        \assert(\is_string($eventDateLabel));
        $result .= ' ' . \sprintf($eventDateLabel, $this->renderEventDate($event));

        if ($event->getTimeSlots()->count() === 0) {
            $timeAtLabel = LocalizationUtility::translate('email_timeAt', 'seminars');
            \assert(\is_string($timeAtLabel));
            $time = $this->formatTime($start);
            $label = ' ' . $timeAtLabel;
            $result .= \sprintf($label, $time);
        }

        return $result;
    }
}
