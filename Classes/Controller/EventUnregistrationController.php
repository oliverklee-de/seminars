<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller;

use OliverKlee\Seminars\Configuration\LegacyConfiguration;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Service\RegistrationManager;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Plugin for registering for events.
 */
class EventUnregistrationController extends ActionController
{
    private RegistrationManager $registrationManager;

    private Context $context;

    public function __construct(RegistrationManager $registrationManager, Context $context)
    {
        $this->registrationManager = $registrationManager;
        $this->context = $context;
    }

    /**
     * Checks whether the logged-in user is allowed to cancel the given registration.
     *
     * @IgnoreValidation("registration")
     */
    public function checkPrerequisitesAction(?Registration $registration = null): ResponseInterface
    {
        if (!$registration instanceof Registration) {
            return $this->forwardToDenyAction('registrationMissing');
        }

        if (!$this->belongsToLoggedInUser($registration)) {
            // To avoid information disclosure, we do not tell the user about registrations that are not theirs.
            return $this->forwardToDenyAction('registrationMissing');
        }

        if (!$this->isUnregistrationPossible($registration)) {
            // To avoid information disclosure, we do not tell the user about registrations that are not theirs.
            return $this->forwardToDenyAction('noUnregistrationPossible');
        }

        return $this->redirect('confirm', null, null, ['registration' => $registration]);
    }

    private function belongsToLoggedInUser(Registration $registration): bool
    {
        $loggedInUserUid = $this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        \assert(\is_int($loggedInUserUid));
        \assert($loggedInUserUid >= 0);

        return $registration->belongsToUser($loggedInUserUid);
    }

    private function isUnregistrationPossible(Registration $registration): bool
    {
        $legacyRegistration = GeneralUtility::makeInstance(LegacyRegistration::class, (int)$registration->getUid());
        $legacyEvent = $legacyRegistration->getSeminarObject();

        return $legacyEvent->isUnregistrationPossible();
    }

    /**
     * This is a convenience method to simplify multiple calls.
     *
     * @param non-empty-string $warningMessageKey the key of the message to display,
     *        will automatically get prefixed with `plugin.eventUnregistration.error.`
     */
    private function forwardToDenyAction(string $warningMessageKey): ResponseInterface
    {
        return (new ForwardResponse('deny'))->withArguments(['warningMessageKey' => $warningMessageKey]);
    }

    public function denyAction(string $warningMessageKey): ResponseInterface
    {
        $this->view->assign('warningMessageKey', $warningMessageKey);

        return $this->htmlResponse();
    }

    /**
     * Displays the unregistration form.
     *
     * @IgnoreValidation("registration")
     */
    public function confirmAction(Registration $registration): ResponseInterface
    {
        $this->view->assign('registration', $registration);

        return $this->htmlResponse();
    }

    /**
     * Removes the provided registration and forwards to the thank-you page.
     *
     * @IgnoreValidation("registration")
     */
    public function unregisterAction(Registration $registration): ResponseInterface
    {
        $uid = $registration->getUid();
        if (\is_int($uid) && $uid > 0) {
            $configuration = GeneralUtility::makeInstance(LegacyConfiguration::class);
            $this->registrationManager->removeRegistration($uid, $configuration);
        }

        return $this->redirect('thankYou', null, null, ['event' => $registration->getEvent()]);
    }

    /**
     * @IgnoreValidation("event")
     */
    public function thankYouAction(Event $event): ResponseInterface
    {
        $this->view->assign('event', $event);

        return $this->htmlResponse();
    }
}
