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
use OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper;
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

    private DateRangeViewHelper $dateRangeViewHelper;

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
        $this->dateRangeViewHelper = GeneralUtility::makeInstance(DateRangeViewHelper::class);
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

        $sender = $this->determineEmailSenderForEvent($legacyEvent);
        $firstOrganizer = $legacyEvent->getFirstOrganizer();
        $registrations = $this->registrationRepository->findRegularRegistrationsByEvent($legacyEvent->getUid());
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
                ->subject($this->replaceMarkers($subject, $event, $legacyEvent, $user))
                ->text($this->buildMessageBody($body, $event, $legacyEvent, $user))
                ->build()->send();
        }
    }

    /**
     * Returns a `MailRole` with the default email data from the TYPO3 configuration if possible.
     *
     * Otherwise, returns the first organizer of the given event.
     *
     * @param Event|EventDateInterface $event
     */
    private function determineEmailSenderForEvent($event): MailRole
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
    protected function buildMessageBody(
        string $rawBody,
        EventDateInterface $event,
        Event $legacyEvent,
        FrontendUser $user
    ): string {
        $bodyWithFooter = $this->replaceMarkers($rawBody, $event, $legacyEvent, $user);
        $organizer = $legacyEvent->getFirstOrganizer();
        if ($organizer->hasEmailFooter()) {
            $bodyWithFooter .= "\n-- \n" . $organizer->getEmailFooter();
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
    protected function replaceMarkers(
        string $textWithMarkers,
        EventDateInterface $event,
        Event $legacyEvent,
        FrontendUser $user
    ): string {
        $markers = [
            '%salutation' => $this->salutationBuilder->getSalutation($user),
            '%userName' => $user->getName(),
            '%eventTitle' => $legacyEvent->getTitle(),
            '%eventDate' => $this->dateRangeViewHelper->render($event),
        ];

        return str_replace(array_keys($markers), $markers, $textWithMarkers);
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
}
