<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\AccommodationOption;
use OliverKlee\Seminars\Domain\Model\FoodOption;
use OliverKlee\Seminars\Domain\Model\Organizer;
use OliverKlee\Seminars\Domain\Model\RegistrationCheckbox;
use OliverKlee\Seminars\Domain\Model\Speaker;
use OliverKlee\Seminars\Domain\Model\Venue;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\ORM\Transient;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * This trait provides methods that are useful for `EventTopic`s, and usually also `SingleEvent`s.
 *
 * @phpstan-require-extends Event
 * @phpstan-require-implements EventDateInterface
 */
trait EventDateTrait
{
    protected ?\DateTime $start = null;

    protected ?\DateTime $end = null;

    protected ?\DateTime $registrationStart = null;

    protected ?\DateTime $earlyBirdDeadline = null;

    protected ?\DateTime $registrationDeadline = null;

    protected ?\DateTime $downloadStartDate = null;

    protected ?\DateTime $billingStart = null;

    protected bool $registrationRequired = false;

    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $room = '';

    /**
     * This property is expected to be set from the outside by the `RegistationGuard`.
     *
     * @Transient
     */
    protected ?bool $registrationPossibleByDate = null;

    protected bool $waitingList = false;

    /**
     * @phpstan-var int<0, max>
     * @Validate("NumberRange", options={"minimum": 0})
     */
    protected int $minimumNumberOfRegistrations = 0;

    /**
     * @phpstan-var int<0, max>
     * @Validate("NumberRange", options={"minimum": 0})
     */
    protected int $maximumNumberOfRegistrations = 0;

    /**
     * @var ObjectStorage<Venue>
     * @Lazy
     */
    protected ObjectStorage $venues;

    /**
     * @var ObjectStorage<Speaker>
     * @Lazy
     */
    protected ObjectStorage $speakers;

    /**
     * @var ObjectStorage<Organizer>
     * @Lazy
     */
    protected ObjectStorage $organizers;

    /**
     * @phpstan-var int<0, 9999>
     * @Validate("NumberRange", options={"minimum": 0, "maximum": 9999})
     */
    protected int $numberOfOfflineRegistrations = 0;

    /**
     * @var EventInterface::STATUS_*
     * @Validate("NumberRange", options={"minimum": 0, "maximum": 2})
     */
    protected int $status = EventInterface::STATUS_PLANNED;

    /**
     * @var ObjectStorage<AccommodationOption>
     * @Lazy
     */
    protected ObjectStorage $accommodationOptions;

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
     *
     * @internal
     */
    protected ?EventStatistics $statistics = null;

    /**
     * @var EventDateInterface::EVENT_FORMAT_*
     * @Validate("NumberRange", options={"minimum": 0, "maximum": 2})
     */
    protected int $eventFormat = EventDateInterface::EVENT_FORMAT_ON_SITE;

    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $webinarUrl = '';

    /**
     * @Validate("StringLength", options={"maximum": 2048})
     */
    protected string $additionalEmailText = '';

    /**
     * @var list<EventDateInterface::EVENT_FORMAT_*>
     */
    private static array $partiallyOnSiteEventFormats = [
        EventDateInterface::EVENT_FORMAT_ON_SITE,
        EventDateInterface::EVENT_FORMAT_HYBRID,
    ];

    /**
     * @var list<EventDateInterface::EVENT_FORMAT_*>
     */
    private static array $partiallyOnlineEventFormats = [
        EventDateInterface::EVENT_FORMAT_HYBRID,
        EventDateInterface::EVENT_FORMAT_ONLINE,
    ];

    /**
     * @var ObjectStorage<FileReference>
     * @Lazy
     */
    protected ObjectStorage $downloadsForAttendees;

    /**
     * @var ObjectStorage<TimeSlot>
     * @Lazy
     */
    protected ObjectStorage $timeSlots;

    private function initializeEventDate(): void
    {
        $this->venues = new ObjectStorage();
        $this->speakers = new ObjectStorage();
        $this->organizers = new ObjectStorage();
        $this->accommodationOptions = new ObjectStorage();
        $this->foodOptions = new ObjectStorage();
        $this->registrationCheckboxes = new ObjectStorage();
        $this->downloadsForAttendees = new ObjectStorage();
        $this->timeSlots = new ObjectStorage();
    }

    public function getStart(): ?\DateTime
    {
        return $this->start;
    }

    public function setStart(?\DateTime $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): ?\DateTime
    {
        return $this->end;
    }

    public function setEnd(?\DateTime $end): void
    {
        $this->end = $end;
    }

    public function isMultiDay(): bool
    {
        $start = $this->getStart();
        $end = $this->getEnd();
        if (!($start instanceof \DateTimeInterface) || !($end instanceof \DateTimeInterface)) {
            return false;
        }

        $startDay = $start->format('Y-m-d');
        $endDay = $end->format('Y-m-d');

        return $startDay !== $endDay;
    }

    public function getRegistrationStart(): ?\DateTime
    {
        return $this->registrationStart;
    }

    public function setRegistrationStart(?\DateTime $registrationStart): void
    {
        $this->registrationStart = $registrationStart;
    }

    public function getEarlyBirdDeadline(): ?\DateTime
    {
        return $this->earlyBirdDeadline;
    }

    public function setEarlyBirdDeadline(?\DateTime $earlyBirdDeadline): void
    {
        $this->earlyBirdDeadline = $earlyBirdDeadline;
    }

    public function getRegistrationDeadline(): ?\DateTime
    {
        return $this->registrationDeadline;
    }

    public function setRegistrationDeadline(?\DateTime $registrationDeadline): void
    {
        $this->registrationDeadline = $registrationDeadline;
    }

    public function getEffectiveRegistrationDeadline(): ?\DateTime
    {
        if (!$this->isRegistrationRequired()) {
            return null;
        }

        $registrationDeadline = $this->getRegistrationDeadline();

        return ($registrationDeadline instanceof \DateTime) ? $registrationDeadline : $this->getStart();
    }

    public function getDownloadStartDate(): ?\DateTime
    {
        return $this->downloadStartDate;
    }

    public function setDownloadStartDate(\DateTime $startDate): void
    {
        $this->downloadStartDate = $startDate;
    }

    public function isDownloadPossibleByDate(): bool
    {
        $downloadStartDate = $this->getDownloadStartDate();
        if (!($downloadStartDate instanceof \DateTimeInterface)) {
            return true;
        }

        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
        \assert($now instanceof \DateTimeInterface);

        return $now >= $downloadStartDate;
    }

    public function isRegistrationRequired(): bool
    {
        return $this->registrationRequired;
    }

    public function setRegistrationRequired(bool $registrationRequired): void
    {
        $this->registrationRequired = $registrationRequired;
    }

    /**
     * @throws \BadMethodCallException if registrationPossibleByDate has not been set yet
     */
    public function isRegistrationPossibleByDate(): bool
    {
        $possible = $this->registrationPossibleByDate;
        if (!\is_bool($possible)) {
            throw new \BadMethodCallException('registrationPossibleByDate has not been set yet.', 1736269500);
        }

        return $possible;
    }

    public function setRegistrationPossibleByDate(bool $possible): void
    {
        $this->registrationPossibleByDate = $possible;
    }

    public function hasWaitingList(): bool
    {
        return $this->waitingList;
    }

    public function setWaitingList(bool $waitingList): void
    {
        $this->waitingList = $waitingList;
    }

    /**
     * @return int<0, max>
     */
    public function getMinimumNumberOfRegistrations(): int
    {
        return $this->minimumNumberOfRegistrations;
    }

    /**
     * @param int<0, max> $minimumNumberOfRegistrations
     */
    public function setMinimumNumberOfRegistrations(int $minimumNumberOfRegistrations): void
    {
        $this->minimumNumberOfRegistrations = $minimumNumberOfRegistrations;
    }

    /**
     * @return int<0, max>
     */
    public function getMaximumNumberOfRegistrations(): int
    {
        return $this->maximumNumberOfRegistrations;
    }

    /**
     * @param int<0, max> $maximumNumberOfRegistrations
     */
    public function setMaximumNumberOfRegistrations(int $maximumNumberOfRegistrations): void
    {
        $this->maximumNumberOfRegistrations = $maximumNumberOfRegistrations;
    }

    public function hasUnlimitedSeats(): bool
    {
        return $this->isRegistrationRequired() && $this->getMaximumNumberOfRegistrations() === 0;
    }

    /**
     * @return ObjectStorage<Venue>
     */
    public function getVenues(): ObjectStorage
    {
        return $this->venues;
    }

    /**
     * @param ObjectStorage<Venue> $venues
     */
    public function setVenues(ObjectStorage $venues): void
    {
        $this->venues = $venues;
    }

    public function hasExactlyOneVenue(): bool
    {
        return \count($this->getVenues()) === 1;
    }

    /**
     * @throws \RuntimeException if there are no venues
     */
    public function getFirstVenue(): Venue
    {
        $venues = $this->getVenues()->getArray();
        $firstVenue = $venues[0] ?? null;
        if (!$firstVenue instanceof Venue) {
            throw new \RuntimeException('This event does not have any venues.', 1726226635);
        }

        return $firstVenue;
    }

    /**
     * Returns a sorted, unique list of city names of all venues of this event.
     *
     * @return array<int, string>
     */
    public function getCityNames(): array
    {
        $cityNames = [];
        foreach ($this->getVenues() as $venue) {
            $cityNames[] = $venue->getCity();
        }

        $uniqueCityNames = \array_unique($cityNames);
        \sort($uniqueCityNames, \SORT_STRING);

        return $uniqueCityNames;
    }

    /**
     * @return ObjectStorage<Speaker>
     */
    public function getSpeakers(): ObjectStorage
    {
        return $this->speakers;
    }

    /**
     * @param ObjectStorage<Speaker> $speakers
     */
    public function setSpeakers(ObjectStorage $speakers): void
    {
        $this->speakers = $speakers;
    }

    /**
     * @return ObjectStorage<Organizer>
     */
    public function getOrganizers(): ObjectStorage
    {
        return $this->organizers;
    }

    /**
     * @param ObjectStorage<Organizer> $organizers
     */
    public function setOrganizers(ObjectStorage $organizers): void
    {
        $this->organizers = $organizers;
    }

    /**
     * @throws \UnexpectedValueException if there are no organizers
     */
    public function getFirstOrganizer(): Organizer
    {
        $organizers = $this->getOrganizers();
        if (\count($organizers) === 0) {
            throw new \UnexpectedValueException('This event does not have any organizers.', 1724277439);
        }

        $organizers->rewind();
        return $organizers->current();
    }

    /**
     * Sets the given organizer as the only organizer of this event, removing all other organizers.
     */
    public function setSingleOrganizer(Organizer $organizer): void
    {
        /** @var ObjectStorage<Organizer> $organizers */
        $organizers = new ObjectStorage();
        $organizers->attach($organizer);
        $this->setOrganizers($organizers);
    }

    /**
     * @return int<0, 9999>
     */
    public function getNumberOfOfflineRegistrations(): int
    {
        return $this->numberOfOfflineRegistrations;
    }

    /**
     * @param int<0, 9999> $numberOfOfflineRegistrations
     */
    public function setNumberOfOfflineRegistrations(int $numberOfOfflineRegistrations): void
    {
        $this->numberOfOfflineRegistrations = $numberOfOfflineRegistrations;
    }

    /**
     * @return EventInterface::STATUS_*
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param EventInterface::STATUS_* $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function isCanceled(): bool
    {
        return $this->status === EventInterface::STATUS_CANCELED;
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
     * @internal
     */
    public function getStatistics(): ?EventStatistics
    {
        return $this->statistics;
    }

    /**
     * @internal
     */
    public function setStatistics(EventStatistics $statistics): void
    {
        $this->statistics = $statistics;
    }

    /**
     * @return EventDateInterface::EVENT_FORMAT_*
     */
    public function getEventFormat(): int
    {
        return $this->eventFormat;
    }

    /**
     * @param EventDateInterface::EVENT_FORMAT_* $eventFormat
     */
    public function setEventFormat(int $eventFormat): void
    {
        $this->eventFormat = $eventFormat;
    }

    public function isAtLeastPartiallyOnSite(): bool
    {
        return \in_array($this->getEventFormat(), self::$partiallyOnSiteEventFormats, true);
    }

    public function isAtLeastPartiallyOnline(): bool
    {
        return \in_array($this->getEventFormat(), self::$partiallyOnlineEventFormats, true);
    }

    public function isHybrid(): bool
    {
        return $this->getEventFormat() === EventDateInterface::EVENT_FORMAT_HYBRID;
    }

    public function getWebinarUrl(): string
    {
        return $this->webinarUrl;
    }

    public function setWebinarUrl(string $webinarUrl): void
    {
        $this->webinarUrl = $webinarUrl;
    }

    /**
     * Checks if this event is at least partially online and has a non-empty webinar URL.
     */
    public function hasUsableWebinarUrl(): bool
    {
        return $this->isAtLeastPartiallyOnline() && $this->getWebinarUrl() !== '';
    }

    public function getAdditionalEmailText(): string
    {
        return $this->additionalEmailText;
    }

    public function setAdditionalEmailText(string $additionalEmailText): void
    {
        $this->additionalEmailText = $additionalEmailText;
    }

    public function getRoom(): string
    {
        return $this->room;
    }

    public function setRoom(string $room): void
    {
        $this->room = $room;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getDownloadsForAttendees(): ObjectStorage
    {
        return $this->downloadsForAttendees;
    }

    /**
     * @param ObjectStorage<FileReference> $files
     */
    public function setDownloadsForAttendees(ObjectStorage $files): void
    {
        $this->downloadsForAttendees = $files;
    }

    public function getBillingStart(): ?\DateTime
    {
        return $this->billingStart;
    }

    public function setBillingStart(\DateTime $billingStart): void
    {
        $this->billingStart = $billingStart;
    }

    /**
     * @return ObjectStorage<TimeSlot>
     */
    public function getTimeSlots(): ObjectStorage
    {
        return $this->timeSlots;
    }

    /**
     * @param ObjectStorage<TimeSlot> $timeSlots
     */
    public function setTimeSlots(ObjectStorage $timeSlots): void
    {
        $this->timeSlots = $timeSlots;
    }
}
