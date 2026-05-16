<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BackEnd;

use OliverKlee\Oelib\Email\SystemEmailBuilder;
use OliverKlee\Oelib\Exception\NotFoundException;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Organizer;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Email\EmailBuilder;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Service for sending emails to the attendees of an event from the seminars back-end module.
 *
 * @internal
 */
class EmailService implements SingletonInterface
{
    private RegistrationRepository $registrationRepository;

    private SystemEmailBuilder $systemEmailBuilder;

    public function __construct(RegistrationRepository $registrationRepository, SystemEmailBuilder $systemEmailBuilder)
    {
        $this->registrationRepository = $registrationRepository;
        $this->systemEmailBuilder = $systemEmailBuilder;
    }

    /**
     * Sends an email to the regular attendees of the event with the given UID using the provided email subject
     * and message body.
     *
     * @param Event&EventDateInterface $event
     *
     * @throws NotFoundException if event could not be instantiated
     */
    public function sendPlainTextEmailToRegularAttendees($event, string $subject, string $rawBody): void
    {
        $organizer = $event->getFirstOrganizer();
        $sender = $this->systemEmailBuilder->build();
        $eventUid = $event->getUid();
        \assert(\is_int($eventUid) && $eventUid > 0);

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
