<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Registration;

use OliverKlee\Seminars\Domain\Model\AccommodationOption;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\FoodOption;
use OliverKlee\Seminars\Domain\Model\RawDataInterface;
use OliverKlee\Seminars\Domain\Model\RawDataTrait;
use OliverKlee\Seminars\Domain\Model\RegistrationCheckbox;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\ORM\Transient;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * This class represents a registration (or a waiting list entry or nonbinding reservation) for an event.
 */
class Registration extends AbstractEntity implements RawDataInterface
{
    use RawDataTrait;
    use AttendeesTrait;
    use BillingAddressTrait;
    use PaymentTrait;

    public const STATUS_REGULAR = 0;
    public const STATUS_WAITING_LIST = 1;
    public const STATUS_NONBINDING_RESERVATION = 2;

    public const ATTENDANCE_MODE_NOT_SET = 0;
    public const ATTENDANCE_MODE_ON_SITE = 1;
    public const ATTENDANCE_MODE_ONLINE = 2;
    public const ATTENDANCE_MODE_HYBRID = 3;

    /**
     * @var list<self::ATTENDANCE_MODE_*>
     */
    private const PARTIALLY_ON_SITE_ATTENDANCE_MODES = [
        self::ATTENDANCE_MODE_ON_SITE,
        self::ATTENDANCE_MODE_HYBRID,
    ];

    /**
     * @var list<self::ATTENDANCE_MODE_*>
     */
    private const PARTIALLY_ONLINE_ATTENDANCE_MODES = [
        self::ATTENDANCE_MODE_ONLINE,
        self::ATTENDANCE_MODE_HYBRID,
    ];

    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $title = '';

    /**
     * @var Event|null
     * @phpstan-var EventDateInterface|LazyLoadingProxy|null
     * @Lazy
     */
    protected $event;

    /**
     * @var self::ATTENDANCE_MODE_*
     */
    protected int $attendanceMode = self::ATTENDANCE_MODE_NOT_SET;

    /**
     * @var self::STATUS_*
     */
    protected int $status = self::STATUS_REGULAR;

    /**
     * @Validate("StringLength", options={"maximum": 16383})
     */
    protected string $interests = '';

    /**
     * @Validate("StringLength", options={"maximum": 16383})
     */
    protected string $expectations = '';

    /**
     * @Validate("StringLength", options={"maximum": 16383})
     */
    protected string $backgroundKnowledge = '';

    /**
     * @Validate("StringLength", options={"maximum": 16383})
     */
    protected string $comments = '';

    /**
     * @Validate("StringLength", options={"maximum": 16383})
     */
    protected string $knownFrom = '';

    /**
     * @var ObjectStorage<AccommodationOption>
     * @Lazy
     */
    protected $accommodationOptions;

    /**
     * @var ObjectStorage<FoodOption>
     * @Lazy
     */
    protected ObjectStorage $foodOptions;

    /**
     * @var ObjectStorage<RegistrationCheckbox>
     * @Lazy
     */
    protected ObjectStorage $registrationCheckboxes;

    /**
     * @Transient
     * @Validate(validator="Boolean", options={"is": "1"})
     */
    protected bool $consentedToTermsAndConditions = false;

    /**
     * @Transient
     * @Validate(validator="Boolean", options={"is": "1"})
     */
    protected bool $consentedToAdditionalTerms = false;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->additionalPersons = new ObjectStorage();
        $this->accommodationOptions = new ObjectStorage();
        $this->foodOptions = new ObjectStorage();
        $this->registrationCheckboxes = new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $name): void
    {
        $this->title = $name;
    }

    public function getEvent(): ?EventDateInterface
    {
        $event = $this->event;
        if ($event instanceof LazyLoadingProxy) {
            $event = $event->_loadRealInstance();
            $this->event = ($event instanceof EventDateInterface) ? $event : null;
        }

        return $event;
    }

    public function setEvent(EventDateInterface $event): void
    {
        $this->event = $event;
    }

    /**
     * @return self::ATTENDANCE_MODE_*
     */
    public function getAttendanceMode(): int
    {
        return $this->attendanceMode;
    }

    public function isAtLeastPartiallyOnSite(): bool
    {
        return \in_array($this->getAttendanceMode(), self::PARTIALLY_ON_SITE_ATTENDANCE_MODES, true);
    }

    public function isAtLeastPartiallyOnline(): bool
    {
        return \in_array($this->getAttendanceMode(), self::PARTIALLY_ONLINE_ATTENDANCE_MODES, true);
    }

    /**
     * @param self::ATTENDANCE_MODE_* $attendanceMode
     */
    public function setAttendanceMode(int $attendanceMode): void
    {
        $this->attendanceMode = $attendanceMode;
    }

    public function isRegularRegistration(): bool
    {
        return $this->getStatus() === self::STATUS_REGULAR;
    }

    public function isOnWaitingList(): bool
    {
        return $this->getStatus() === self::STATUS_WAITING_LIST;
    }

    public function isNonbindingReservation(): bool
    {
        return $this->getStatus() === self::STATUS_NONBINDING_RESERVATION;
    }

    public function convertToRegularRegistration(): void
    {
        $this->setStatus(self::STATUS_REGULAR);
    }

    public function moveToWaitingList(): void
    {
        $this->setStatus(self::STATUS_WAITING_LIST);
    }

    /**
     * @return self::STATUS_*
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param self::STATUS_* $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getInterests(): string
    {
        return $this->interests;
    }

    public function setInterests(string $interests): void
    {
        $this->interests = $interests;
    }

    public function getExpectations(): string
    {
        return $this->expectations;
    }

    public function setExpectations(string $expectations): void
    {
        $this->expectations = $expectations;
    }

    public function getBackgroundKnowledge(): string
    {
        return $this->backgroundKnowledge;
    }

    public function setBackgroundKnowledge(string $backgroundKnowledge): void
    {
        $this->backgroundKnowledge = $backgroundKnowledge;
    }

    public function getComments(): string
    {
        return $this->comments;
    }

    public function setComments(string $comments): void
    {
        $this->comments = $comments;
    }

    public function getKnownFrom(): string
    {
        return $this->knownFrom;
    }

    public function setKnownFrom(string $knownFrom): void
    {
        $this->knownFrom = $knownFrom;
    }

    /**
     * @return ObjectStorage<AccommodationOption>
     */
    public function getAccommodationOptions(): ObjectStorage
    {
        return $this->accommodationOptions;
    }

    /**
     * @param ObjectStorage<AccommodationOption> $accommodationOptions
     */
    public function setAccommodationOptions(ObjectStorage $accommodationOptions): void
    {
        $this->accommodationOptions = $accommodationOptions;
    }

    /**
     * This method can be used to work around that iterating over the accommodation options on the registration
     * confirmation page only provides the first selected one.
     *
     * @return list<string>
     */
    public function getAccommodationOptionTitles(): array
    {
        $titles = [];

        foreach ($this->getAccommodationOptions() as $option) {
            $titles[] = $option->getTitle();
        }

        return $titles;
    }

    /**
     * @return ObjectStorage<FoodOption>
     */
    public function getFoodOptions(): ObjectStorage
    {
        return $this->foodOptions;
    }

    /**
     * @param ObjectStorage<FoodOption> $foodOptions
     */
    public function setFoodOptions(ObjectStorage $foodOptions): void
    {
        $this->foodOptions = $foodOptions;
    }

    /**
     * This method can be used to work around that iterating over the food options on the registration
     * confirmation page only provides the first selected one.
     *
     * @return list<string>
     */
    public function getFoodOptionTitles(): array
    {
        $titles = [];

        foreach ($this->getFoodOptions() as $option) {
            $titles[] = $option->getTitle();
        }

        return $titles;
    }

    /**
     * @return ObjectStorage<RegistrationCheckbox>
     */
    public function getRegistrationCheckboxes(): ObjectStorage
    {
        return $this->registrationCheckboxes;
    }

    /**
     * @param ObjectStorage<RegistrationCheckbox> $registrationCheckboxes
     */
    public function setRegistrationCheckboxes(ObjectStorage $registrationCheckboxes): void
    {
        $this->registrationCheckboxes = $registrationCheckboxes;
    }

    /**
     * This method can be used to work around that iterating over the registration checkboxes on the registration
     * confirmation page only provides the first selected one.
     *
     * @return list<string>
     */
    public function getRegistrationCheckboxTitles(): array
    {
        $titles = [];

        foreach ($this->getRegistrationCheckboxes() as $checkbox) {
            $titles[] = $checkbox->getTitle();
        }

        return $titles;
    }

    public function hasConsentedToTermsAndConditions(): bool
    {
        return $this->consentedToTermsAndConditions;
    }

    public function setConsentedToTermsAndConditions(bool $consent): void
    {
        $this->consentedToTermsAndConditions = $consent;
    }

    public function hasConsentedToAdditionalTerms(): bool
    {
        return $this->consentedToAdditionalTerms;
    }

    public function setConsentedToAdditionalTerms(bool $consent): void
    {
        $this->consentedToAdditionalTerms = $consent;
    }

    /**
     * This is a stub method that gets overwritten by the `seminars_premium` extension.
     */
    public function hasDownloadableCertificate(): bool
    {
        return false;
    }
}
