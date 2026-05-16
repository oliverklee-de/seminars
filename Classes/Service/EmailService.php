<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Service;

use OliverKlee\Oelib\Email\SystemEmailBuilder;
use OliverKlee\Seminars\Email\EmailBuilder;
use OliverKlee\Seminars\Email\Salutation;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Registration;
use OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class takes care of sending emails.
 *
 * The following markers will get replaced in the email body:
 *
 * %salutation
 * %userName
 * %eventTitle
 * %eventDate
 */
class EmailService implements SingletonInterface
{
    private SystemEmailBuilder $systemEmailBuilder;

    protected Salutation $salutationBuilder;

    protected DateRangeViewHelper $dateRangeViewHelper;

    public function __construct(SystemEmailBuilder $systemEmailBuilder)
    {
        $this->systemEmailBuilder = $systemEmailBuilder;
        $this->salutationBuilder = GeneralUtility::makeInstance(Salutation::class);
        $this->dateRangeViewHelper = GeneralUtility::makeInstance(DateRangeViewHelper::class);
    }

    /**
     * Sends an email to of registered users of the given event.
     *
     * @param string $body can contain %salutation which will expand to a full salutation with the user's name
     */
    public function sendEmailToAttendees(Event $event, string $subject, string $body): void
    {
        $sender = $this->systemEmailBuilder->build();
        $firstOrganizer = $event->getFirstOrganizer();

        /** @var Registration $registration */
        foreach ($event->getRegistrations() as $registration) {
            $user = $registration->getFrontEndUser();
            if ($user === null || !$user->hasEmailAddress()) {
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
     * Builds the message body (including the email footer).
     */
    protected function buildMessageBody(string $rawBody, Event $event, FrontEndUser $user): string
    {
        $bodyWithFooter = $this->replaceMarkers($rawBody, $event, $user);
        $organizer = $event->getFirstOrganizer();
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
     * %salutation
     * %userName
     * %eventTitle
     * %eventDate
     */
    protected function replaceMarkers(string $textWithMarkers, Event $event, FrontEndUser $user): string
    {
        $markers = [
            '%salutation' => $this->salutationBuilder->getSalutation($user),
            '%userName' => $user->getName(),
            '%eventTitle' => $event->getTitle(),
            '%eventDate' => $this->dateRangeViewHelper->render($event, '-'),
        ];

        return str_replace(array_keys($markers), $markers, $textWithMarkers);
    }
}
