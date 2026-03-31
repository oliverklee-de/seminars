<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Model;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;

/**
 * This class represents an event.
 */
class Event extends AbstractTimeSpan
{
    /**
     * Returns whether this event is a valid event date (i.e., a date with an associated topic).
     *
     * @return bool TRUE if this event is an event date, FALSE otherwise
     */
    public function isEventDate(): bool
    {
        return $this->getAsNonNegativeInteger('object_type') === EventInterface::TYPE_EVENT_DATE
            && $this->getAsModel('topic') !== null;
    }

    /**
     * This method may only be called for date records.
     *
     * @throws \BadMethodCallException if this event is no (valid) date
     */
    public function getTopic(): Event
    {
        if (!$this->isEventDate()) {
            throw new \BadMethodCallException('This function may only be called for date records.', 1333296324);
        }
        /** @var Event $topic */
        $topic = $this->getAsModel('topic');

        return $topic;
    }

    /**
     * @return string our title, will be empty if this event has no title
     */
    public function getTitle(): string
    {
        return $this->isEventDate() ? $this->getTopic()->getTitle() : $this->getRawTitle();
    }

    /**
     * Returns our direct title, i.e. for date records the date's title, not
     * the topic's title.
     *
     * For single events and dates, this function will return the same as
     * getTitle.
     *
     * @return string our title, will be empty if this event has no title
     */
    public function getRawTitle(): string
    {
        return $this->getAsString('title');
    }

    /**
     * @return Collection<Category>
     */
    public function getCategories(): Collection
    {
        if ($this->isEventDate()) {
            return $this->getTopic()->getCategories();
        }

        /** @var Collection<Category> $categories */
        $categories = $this->getAsCollection('categories');

        return $categories;
    }

    /**
     * @return string our teaser, might be empty
     */
    public function getTeaser(): string
    {
        return $this->isEventDate()
            ? $this->getTopic()->getTeaser()
            : $this->getAsString('teaser');
    }

    public function hasDescription(): bool
    {
        return $this->isEventDate()
            ? $this->getTopic()->hasDescription()
            : $this->hasString('description');
    }

    public function getEventType(): ?EventType
    {
        /** @var EventType|null $type */
        $type = $this->isEventDate() ? $this->getTopic()->getEventType() : $this->getAsModel('event_type');

        return $type;
    }

    /**
     * @return int<0, max> our registration deadline as UNIX time-stamp, will be 0 if this event has no registration
     *                     deadline
     */
    public function getRegistrationDeadlineAsUnixTimeStamp(): int
    {
        return $this->getAsNonNegativeInteger('deadline_registration');
    }

    public function hasRegistrationDeadline(): bool
    {
        return $this->hasInteger('deadline_registration');
    }

    /**
     * @return string our separate details page, will be empty if this event has no separate details page
     */
    public function getDetailsPage(): string
    {
        return $this->getAsString('details_page');
    }

    public function hasDetailsPage(): bool
    {
        return $this->hasString('details_page');
    }

    /**
     * Gets a separate single view page UID (or full URL) for this event,
     * combined from the event itself, the event type and the categories.
     *
     * Note: This function does not take the TS setup configuration or flexform
     * settings into account.
     *
     * @return string the single view page UID/URL, will be an empty string if none has been set
     */
    public function getCombinedSingleViewPage(): string
    {
        $result = '';

        if ($this->hasDetailsPage()) {
            $result = $this->getDetailsPage();
        } elseif ($this->hasSingleViewPageUidFromEventType()) {
            $result = (string)$this->getSingleViewPageUidFromEventType();
        } elseif ($this->hasSingleViewPageUidFromCategories()) {
            $result = (string)$this->getSingleViewPageUidFromCategories();
        }

        return $result;
    }

    public function hasCombinedSingleViewPage(): bool
    {
        return $this->getCombinedSingleViewPage() !== '';
    }

    /**
     * @return int the single view page UID from the event type, will be > 0 if
     *         this event has an event type and a that type has a single view
     *         page UID, will be 0 otherwise
     */
    protected function getSingleViewPageUidFromEventType(): int
    {
        if (!$this->hasSingleViewPageUidFromEventType()) {
            return 0;
        }

        $eventType = $this->getEventType();

        return $eventType instanceof EventType ? $eventType->getSingleViewPageUid() : 0;
    }

    protected function hasSingleViewPageUidFromEventType(): bool
    {
        $eventType = $this->getEventType();

        return $eventType instanceof EventType && $eventType->hasSingleViewPageUid();
    }

    /**
     * This function returns the first found UID from the event categories.
     *
     * @return int the single view page UID from the categories, will be > 0 if
     *         this event has at least one category with a single view page
     *         UID, will be 0 otherwise
     */
    protected function getSingleViewPageUidFromCategories(): int
    {
        $result = 0;

        /** @var Category $category */
        foreach ($this->getCategories() as $category) {
            if ($category->hasSingleViewPageUid()) {
                $result = $category->getSingleViewPageUid();
                break;
            }
        }

        return $result;
    }

    protected function hasSingleViewPageUidFromCategories(): bool
    {
        return $this->getSingleViewPageUidFromCategories() > 0;
    }

    /**
     * @return Collection<Organizer>
     */
    public function getOrganizers(): Collection
    {
        /** @var Collection<Organizer> $organizers */
        $organizers = $this->getAsCollection('organizers');

        return $organizers;
    }

    /**
     * @throws \UnexpectedValueException if there are no organizers
     */
    public function getFirstOrganizer(): Organizer
    {
        $organizer = $this->getOrganizers()->first();
        if ($organizer === null) {
            throw new \UnexpectedValueException('This event does not have any organizers.', 1724277806);
        }

        return $organizer;
    }

    /**
     * @return int<0, max> our minimum attendees, will be 0 if this event has no
     *                     minimum attendees
     */
    public function getMinimumAttendees(): int
    {
        return $this->getAsNonNegativeInteger('attendees_min');
    }

    /**
     * @return int<0, max> our maximum attendees, will be 0 if this event has no
     *                     maximum attendees and allows unlimited number of attendees
     */
    public function getMaximumAttendees(): int
    {
        return $this->getAsNonNegativeInteger('attendees_max');
    }

    /**
     * @return EventInterface::STATUS_*
     */
    public function getStatus(): int
    {
        return $this->getAsInteger('cancelled');
    }

    /**
     * @param EventInterface::STATUS_* $status
     */
    public function setStatus(int $status): void
    {
        $this->setAsInteger('cancelled', $status);
    }

    /**
     * Checks whether this event still has the "planned" status.
     */
    public function isPlanned(): bool
    {
        return $this->getStatus() === EventInterface::STATUS_PLANNED;
    }

    public function isCanceled(): bool
    {
        return $this->getStatus() === EventInterface::STATUS_CANCELED;
    }

    public function isConfirmed(): bool
    {
        return $this->getStatus() === EventInterface::STATUS_CONFIRMED;
    }

    /**
     * Marks this event as canceled.
     *
     * If this event already is canceled, this method is a no-op.
     */
    public function cancel(): void
    {
        $this->setStatus(EventInterface::STATUS_CANCELED);
    }

    /**
     * Marks this event as confirmed.
     *
     * If this event already is confirmed, this method is a no-op.
     */
    public function confirm(): void
    {
        $this->setStatus(EventInterface::STATUS_CONFIRMED);
    }

    public function getOwner(): ?FrontEndUser
    {
        /** @var FrontEndUser|null $owner */
        $owner = $this->getAsModel('owner_feuser');

        return $owner;
    }

    /**
     * @return int<0, max> the number of offline registrations for this event,
     *                     will be 0 if this event has no offline registrations
     */
    public function getOfflineRegistrations(): int
    {
        return $this->getAsNonNegativeInteger('offline_attendees');
    }

    /**
     * Checks whether automatic confirmation/cancelation for this event is enabled.
     */
    public function shouldAutomaticallyConfirmOrCancel(): bool
    {
        return $this->getAsBoolean('automatic_confirmation_cancelation');
    }

    /**
     * @return Collection<Registration> the registrations for this event (both regular and
     *                       on the waiting list), will be empty if this event
     *                       has no registrations
     *
     * @deprecated #1324 will be removed in seminars 6.0
     */
    public function getRegistrations(): Collection
    {
        /** @var Collection<Registration> $registrations */
        $registrations = $this->getAsCollection('registrations');

        return $registrations;
    }

    /**
     * @param Collection<Registration> $registrations the registrations for this event
     *        (both regular and on the waiting list), may be empty
     *
     * @deprecated #1324 will be removed in seminars 6.0
     */
    public function setRegistrations(Collection $registrations): void
    {
        $this->set('registrations', $registrations);
    }

    /**
     * Gets the regular registrations for this event, i.e., the registrations
     * that are not on the waiting list.
     *
     * @return Collection<Registration> the regular registrations for this event, will be
     *                       will be empty if this event no regular
     *                       registrations
     *
     * @deprecated #1324 will be removed in seminars 6.0
     */
    public function getRegularRegistrations(): Collection
    {
        /** @var Collection<Registration> $regularRegistrations */
        $regularRegistrations = new Collection();

        /** @var Registration $registration */
        foreach ($this->getRegistrations() as $registration) {
            if (!$registration->isOnRegistrationQueue()) {
                $regularRegistrations->add($registration);
            }
        }

        return $regularRegistrations;
    }

    /**
     * @return Collection<Registration>
     *
     * @deprecated will be removed in version 6.0 in #3422
     */
    public function getRegistrationsAfterLastDigest(): Collection
    {
        /** @var Collection<Registration> $newerRegistrations */
        $newerRegistrations = new Collection();
        $dateOfLastDigest = $this->getDateOfLastRegistrationDigestEmailAsUnixTimeStamp();

        /** @var Registration $registration */
        foreach ($this->getRegistrations() as $registration) {
            if ($registration->getCreationDateAsUnixTimeStamp() > $dateOfLastDigest) {
                $newerRegistrations->add($registration);
            }
        }

        return $newerRegistrations;
    }

    /**
     * Returns the number of regularly registered seats for this event.
     *
     * This functions counts the number of registered seats from regular
     * registrations (but not from queue registrations) and the number of
     * offline registrations.
     *
     * @return int the number of registered seats for this event, will be >= 0
     *
     * @deprecated will be removed in seminars 6.0 #3441
     */
    public function getRegisteredSeats(): int
    {
        $registeredSeats = $this->getOfflineRegistrations();

        /** @var Registration $registration */
        foreach ($this->getRegularRegistrations() as $registration) {
            $registeredSeats += $registration->getSeats();
        }

        return $registeredSeats;
    }

    /**
     * Checks whether this event has enough regular registrations to take place.
     *
     * If this event has zero as the minimum number of registrations, this
     * function will always return TRUE.
     *
     * @deprecated will be removed in seminars 6.0 #3441
     */
    public function hasEnoughRegistrations(): bool
    {
        return $this->getRegisteredSeats() >= $this->getMinimumAttendees();
    }

    /**
     * Returns the names of registered attendees (including additional attendees and queue registrations),
     * but only those that have registered after the last registration digest email.
     *
     * @return list<string> attendee names: ['Jane Doe', 'John Doe']
     *
     * @deprecated will be removed in version 6.0 in #3422
     */
    public function getAttendeeNamesAfterLastDigest(): array
    {
        return $this->extractNamesFromRegistrations($this->getRegistrationsAfterLastDigest());
    }

    /**
     * @param Collection<Registration> $registrations
     *
     * @return list<string> attendee names: ['Jane Doe', 'John Doe']
     */
    private function extractNamesFromRegistrations(Collection $registrations): array
    {
        $names = [];

        /** @var Registration $registration */
        foreach ($registrations as $registration) {
            if ($registration->hasRegisteredThemselves()) {
                $names[] = $registration->getFrontEndUser()->getName();
            }

            $hasAdditionalPersons = false;
            /** @var FrontEndUser $person */
            foreach ($registration->getAdditionalPersons() as $person) {
                $names[] = $person->getName();
                $hasAdditionalPersons = true;
            }

            if (!$hasAdditionalPersons) {
                foreach (explode("\r\n", $registration->getAttendeesNames()) as $name) {
                    $trimmedName = trim($name);
                    if ($trimmedName !== '') {
                        $names[] = $trimmedName;
                    }
                }
            }
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return int<0, max> the date as UNIX time-stamp, will be 0 if this no digest has been sent yet
     */
    public function getDateOfLastRegistrationDigestEmailAsUnixTimeStamp(): int
    {
        return $this->getAsNonNegativeInteger('date_of_last_registration_digest');
    }

    /**
     * @param int $date the date as UNIX time-stamp, must be >= 0
     *
     * @throws \InvalidArgumentException
     */
    public function setDateOfLastRegistrationDigestEmailAsUnixTimeStamp(int $date): void
    {
        if ($date < 0) {
            throw new \InvalidArgumentException('$date must be >= 0, but was: ' . $date, 1508946114880);
        }

        $this->setAsInteger('date_of_last_registration_digest', $date);
    }
}
