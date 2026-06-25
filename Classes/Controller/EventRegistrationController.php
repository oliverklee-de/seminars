<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller;

use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Service\OneTimeAccountConnector;
use OliverKlee\Seminars\Service\PriceFinder;
use OliverKlee\Seminars\Service\RegistrationGuard;
use OliverKlee\Seminars\Service\RegistrationProcessor;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Plugin for registering for events.
 */
class EventRegistrationController extends ActionController
{
    protected const MAXIMUM_BOOKABLE_SEATS = 10;

    protected RegistrationGuard $registrationGuard;

    protected RegistrationProcessor $registrationProcessor;

    protected OneTimeAccountConnector $oneTimeAccountConnector;

    protected PriceFinder $priceFinder;

    public function __construct(
        RegistrationGuard $registrationGuard,
        RegistrationProcessor $registrationProcessor,
        OneTimeAccountConnector $oneTimeAccountConnector,
        PriceFinder $priceFinder
    ) {
        $this->registrationGuard = $registrationGuard;
        $this->registrationProcessor = $registrationProcessor;
        $this->oneTimeAccountConnector = $oneTimeAccountConnector;
        $this->priceFinder = $priceFinder;
    }

    /**
     * Checks that the user can register for the provided event, and redirects or forwards to the corresponding next
     * action.
     *
     * @IgnoreValidation("event")
     */
    public function checkPrerequisitesAction(?Event $event = null): ResponseInterface
    {
        if (!$event instanceof Event) {
            return $this->redirectToPageForNoEvent();
        }
        if (!$this->registrationGuard->isRegistrationPossibleAtAnyTimeAtAll($event)) {
            return $this->forwardToDenyAction('noRegistrationPossibleAtAll');
        }
        \assert($event instanceof SingleEvent || $event instanceof EventDate);
        if (!$this->registrationGuard->isRegistrationPossibleByDate($event)) {
            return $this->forwardToDenyAction('noRegistrationPossibleAtTheMoment');
        }
        if (!$this->registrationGuard->existsFrontEndUserUidInSession($this->request)) {
            return $this->redirectToLoginPage($event);
        }
        $userUid = $this->registrationGuard->getFrontEndUserUidFromSession($this->request);
        if (!$this->registrationGuard->isFreeFromRegistrationConflicts($event, $userUid)) {
            return $this->forwardToDenyAction('alreadyRegistered');
        }
        $vacancies = $this->registrationGuard->getVacancies($event);
        if ($vacancies === 0 && !$event->hasWaitingList()) {
            return $this->forwardToDenyAction('fullyBooked');
        }

        return $this->redirect('new', null, null, ['event' => $event]);
    }

    private function redirectToPageForNoEvent(): ResponseInterface
    {
        $pageUid = (int)($this->settings['pageForMissingEvent'] ?? 0);
        return $this->redirect(null, null, null, [], $pageUid);
    }

    /**
     * This is a convenience method to simplify multiple calls.
     *
     * @param non-empty-string $warningMessageKey the key of the message to display,
     *        will automatically get prefixed with `plugin.eventRegistration.error.`
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

    private function redirectToLoginPage(Event $event): ResponseInterface
    {
        $uriBuilder = $this->uriBuilder->reset();

        // To shorten the URL by removing redundant arguments, we are not using `$uriBuilder->uriFor()` here.
        $redirectUrl = $uriBuilder
            ->setCreateAbsoluteUri(true)
            ->setArguments(['tx_seminars_eventregistration[event]' => $event->getUid()])
            ->buildFrontendUri();

        $loginPageUid = (int)($this->settings['loginPage'] ?? 0);
        $loginPageUrlWithRedirect = $uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->setTargetPageUid($loginPageUid)
            ->setArguments(['redirect_url' => $redirectUrl])
            ->buildFrontendUri();

        return $this->redirectToUri($loginPageUrlWithRedirect);
    }

    /**
     * Displays the event registration form.
     *
     * @IgnoreValidation("event")
     * @IgnoreValidation("registration")
     */
    public function newAction(Event $event, ?Registration $registration = null): ResponseInterface
    {
        $this->registrationGuard->assertBookableEventType($event);
        \assert($event instanceof EventDateInterface);

        $applicablePrices = $this->priceFinder->findApplicablePrices($event);
        if ($registration instanceof Registration) {
            $newRegistration = $registration;
        } else {
            $newRegistration = GeneralUtility::makeInstance(Registration::class);
            $newRegistration->setRegisteredThemselves((bool)($this->settings['registerThemselvesDefault'] ?? true));
            $firstPrice = \array_values($applicablePrices)[0] ?? null;
            $firstPriceCode = $firstPrice instanceof Price ? $firstPrice->getPriceCode() : Price::PRICE_STANDARD;
            $newRegistration->setPriceCode($firstPriceCode);
        }
        $this->registrationProcessor->enrichWithMetadata($newRegistration, $event, $this->settings, $this->request);

        $maximumBookableSeats = (int)($this->settings['maximumBookableSeats'] ?? self::MAXIMUM_BOOKABLE_SEATS);
        $vacancies = $this->registrationGuard->getVacancies($event);
        if (\is_int($vacancies)) {
            if ($vacancies > 0) {
                $maximumBookableSeats = \min($maximumBookableSeats, $vacancies);
            } else {
                $maximumBookableSeats = $event->hasWaitingList() ? $maximumBookableSeats : 0;
            }
        }

        $this->view->assignMultiple(
            [
                'event' => $event,
                'registration' => $newRegistration,
                'maximumBookableSeats' => $maximumBookableSeats,
                'applicablePrices' => $applicablePrices,
            ],
        );

        return $this->htmlResponse();
    }

    /**
     * Displays the confirmation page of the event registration form.
     *
     * @IgnoreValidation("event")
     */
    public function confirmAction(Event $event, Registration $registration): ResponseInterface
    {
        $this->registrationGuard->assertBookableEventType($event);
        \assert($event instanceof EventDateInterface);

        $this->registrationProcessor->enrichWithMetadata($registration, $event, $this->settings, $this->request);
        $this->registrationProcessor->calculateTotalPrice($registration);

        $this->view->assignMultiple(
            [
                'event' => $event,
                'registration' => $registration,
                'applicablePrices' => $this->priceFinder->findApplicablePrices($event),
            ],
        );

        return $this->htmlResponse();
    }

    /**
     * Creates the registration and redirects to the thank-you action.
     *
     * @IgnoreValidation("event")
     */
    public function createAction(Event $event, Registration $registration): ResponseInterface
    {
        $this->registrationGuard->assertBookableEventType($event);
        \assert($event instanceof EventDateInterface);

        $this->registrationProcessor->enrichWithMetadata($registration, $event, $this->settings, $this->request);
        $this->registrationProcessor->calculateTotalPrice($registration);
        $this->registrationProcessor->createTitle($registration);
        $userStorageFolderUid = (int)($this->settings['additionalPersonsStorageFolder'] ?? 0);
        $this->registrationProcessor->createAdditionalPersons($registration, $userStorageFolderUid);
        $this->registrationProcessor->persist($registration);
        $this->registrationProcessor->sendEmails($registration);

        $this->oneTimeAccountConnector->destroyOneTimeSession($this->request);

        return $this->redirect('thankYou', null, null, ['event' => $event, 'registration' => $registration]);
    }

    /**
     * Displays the thank-you page.
     *
     * @IgnoreValidation("event")
     * @IgnoreValidation("registration")
     */
    public function thankYouAction(Event $event, Registration $registration): ResponseInterface
    {
        $this->view->assignMultiple(
            [
                'event' => $event,
                'registration' => $registration,
            ],
        );

        return $this->htmlResponse();
    }
}
