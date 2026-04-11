<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Service;

use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\FrontendUserRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Takes care of enriching and processing a registration after an attendee has registered for an event.
 *
 * This is the recommended way to process a registration:
 * 1. `enrichWithMetadata`
 * 2. `calculateTotalPrice`
 * 3. `createTitle`
 * 4. `createAdditionalPersons` (optional)
 * 5. `persist`
 * 6. `sendEmails`
 */
class RegistrationProcessor implements SingletonInterface
{
    private RegistrationRepository $registrationRepository;

    private EventRepository $eventRepository;

    private FrontendUserRepository $frontendUserRepository;

    private RegistrationGuard $registrationGuard;

    private RegistrationManager $registrationManager;

    public function __construct(
        RegistrationRepository $registrationRepository,
        EventRepository $eventRepository,
        FrontendUserRepository $frontendUserRepository,
        RegistrationGuard $registrationGuard,
        RegistrationManager $registrationManager
    ) {
        $this->registrationRepository = $registrationRepository;
        $this->eventRepository = $eventRepository;
        $this->frontendUserRepository = $frontendUserRepository;
        $this->registrationGuard = $registrationGuard;
        $this->registrationManager = $registrationManager;
    }

    /**
     * Enriches the provided registration with the event, the user association, a PID, and the waiting list flag
     * (if applicable).
     *
     * Call this method before persisting the registration.
     *
     * @param array{registrationRecordsStorageFolder?: numeric-string} $settings
     */
    public function enrichWithMetadata(Registration $registration, Event $event, array $settings): void
    {
        \assert($event instanceof EventDateInterface);
        $registration->setEvent($event);

        $userUid = $this->registrationGuard->getFrontEndUserUidFromSession();
        if (!\is_int($userUid)) {
            throw new \RuntimeException('No user UID found in the session.', 1668865776);
        }
        $user = $this->frontendUserRepository->findByUid($userUid);
        if (!$user instanceof FrontendUser) {
            throw new \RuntimeException('User with UID ' . $userUid . ' not found.', 1668865839);
        }
        $registration->setUser($user);

        $folderUid = (int)($settings['registrationRecordsStorageFolder'] ?? 0);
        $registration->setPid($folderUid);

        if ($event->hasWaitingList() && $this->registrationGuard->getVacancies($event) === 0) {
            $registration->moveToWaitingList();
        }
    }

    public function calculateTotalPrice(Registration $registration): void
    {
        $event = $this->getEventFromRegistration($registration);
        $priceCode = $registration->getPriceCode();
        $price = $event->getAllPrices()[$priceCode] ?? null;
        if ($price instanceof Price) {
            $totalPrice = $price->getAmount() * $registration->getSeats();
            $registration->setTotalPrice($totalPrice);
        }
    }

    /**
     * @throws \RuntimeException if the given registration has no associated event
     */
    private function getEventFromRegistration(Registration $registration): Event
    {
        $event = $registration->getEvent();
        if (!$event instanceof Event) {
            throw new \RuntimeException('The registration has no associated event.', 1669023165);
        }

        return $event;
    }

    /**
     * Sets the title for the registration using the user's full name, the event title and date.
     */
    public function createTitle(Registration $registration): void
    {
        $user = $registration->getUser();
        if (!$user instanceof FrontendUser) {
            throw new \RuntimeException('The registration has no associated user.', 1669023125);
        }
        $event = $this->getEventFromRegistration($registration);

        $dateFormat = LocalizationUtility::translate('dateFormat', 'seminars');
        $startDate = $event instanceof EventDateInterface ? $event->getStart() : null;
        $formattedDate = $startDate instanceof \DateTimeInterface ? $startDate->format($dateFormat) : '';

        $title = LocalizationUtility::translate(
            'registrationTitleFormat',
            'seminars',
            [$event->getDisplayTitle(), $user->getDisplayName(), $formattedDate],
        );

        $registration->setTitle($title);
    }

    /**
     * Creates and attaches additional attendees as FE users.
     *
     * The data for the attendees comes from `Registration::jsonEncodedAdditionAttendees`, and the users will be stored
     * in the given storage folder.
     *
     * If no storage folder UID is given, no users will be created.
     *
     * @param int<0, max> $storageFolderUid
     */
    public function createAdditionalPersons(Registration $registration, int $storageFolderUid): void
    {
        if ($storageFolderUid <= 0) {
            return;
        }

        $allUserData = \json_decode($registration->getJsonEncodedAdditionAttendees(), true);
        if (!\is_array($allUserData)) {
            return;
        }
        foreach ($allUserData as $singleUserData) {
            if (!\is_array($singleUserData)) {
                continue;
            }

            $person = GeneralUtility::makeInstance(FrontendUser::class);
            $name = (string)($singleUserData['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $person->setPid($storageFolderUid);
            $person->setName($name);
            $email = (string)($singleUserData['email'] ?? '');
            $person->setEmail($email);
            $person->setUsername($this->generateRandomUserName());
            $registration->addAdditionalPerson($person);
        }
    }

    /**
     * @return non-empty-string
     */
    private function generateRandomUserName(): string
    {
        return 'additional-attendee-' . \bin2hex(\random_bytes(16));
    }

    /**
     * Persists a registration and updates the `Event.registrations` counter cache.
     *
     * Call `enrichWithMetadata` first before calling this method.
     */
    public function persist(Registration $registration): void
    {
        $event = $this->getEventFromRegistration($registration);

        $this->registrationRepository->add($registration);
        $this->registrationRepository->persistAll();

        $this->eventRepository->updateRegistrationCounterCache($event);
    }

    /**
     * Sends the confirmation and notification emails for a registration. Call `persist` first.
     */
    public function sendEmails(Registration $registration): void
    {
        $registrationUid = $registration->getUid();
        if (!\is_int($registrationUid) || $registrationUid <= 0) {
            throw new \RuntimeException('The registration has not been persisted yet.', 1668939288);
        }

        $legacyRegistration = GeneralUtility::makeInstance(LegacyRegistration::class, $registrationUid);

        $this->registrationManager->sendEmailsForNewRegistration($legacyRegistration);
    }
}
