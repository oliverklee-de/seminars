<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures;

use OliverKlee\Seminars\OldModel\LegacyEvent;

/**
 * This is mere a class used for unit tests. Don't use it for any other purpose.
 */
final class TestingLegacyEvent extends LegacyEvent
{
    /**
     * Sets the event's unregistration deadline.
     *
     * @param int $unregistrationDeadline unregistration deadline as UNIX timestamp
     */
    public function setUnregistrationDeadline(int $unregistrationDeadline): void
    {
        $this->setRecordPropertyInteger(
            'deadline_unregistration',
            $unregistrationDeadline,
        );
    }

    /**
     * Sets the event's begin date.
     *
     * @param int $beginDate begin date as UNIX timestamp (has to be >= 0, 0 will unset the begin date)
     */
    public function setBeginDate(int $beginDate): void
    {
        $this->setRecordPropertyInteger('begin_date', $beginDate);
    }

    /**
     * Sets the event's end date.
     *
     * @param int $endDate end date as UNIX timestamp (has to be >= 0, 0 will unset the end date)
     */
    public function setEndDate(int $endDate): void
    {
        $this->setRecordPropertyInteger('end_date', $endDate);
    }

    /**
     * Sets the event's maximum number of attendances.
     *
     * @param int $attendancesMax maximum attendances number
     */
    public function setAttendancesMax(int $attendancesMax): void
    {
        $this->setRecordPropertyInteger('attendees_max', $attendancesMax);
    }

    /**
     * Sets whether the event has a registration queue.
     *
     * @param bool $hasRegistrationQueue whether the event should have a registration queue
     */
    public function setRegistrationQueue(bool $hasRegistrationQueue): void
    {
        $this->setRecordPropertyBoolean('queue_size', $hasRegistrationQueue);
    }

    /**
     * Sets the number of attendances.
     *
     * @param int<0, max> $number the number of attendances
     */
    public function setNumberOfAttendances(int $number): void
    {
        $this->numberOfAttendances = $number;
        $this->statisticsHaveBeenCalculated = true;
    }

    /**
     * Sets the number of target groups for this record.
     *
     * TODO: This function needs to be removed once the testing framework can update the counter for the number of
     * target groups.
     *
     * @param int<0, max> $targetGroups the number of target groups that are associated with this event
     */
    public function setNumberOfTargetGroups(int $targetGroups): void
    {
        $this->setRecordPropertyInteger('target_groups', $targetGroups);
    }

    /**
     * Sets the number of payment methods for this record.
     *
     * TODO: This function needs to be removed once the data type of the
     * payment methods field was changed to an unsigned integer and we may use
     * the function createRelationAndUpdateCounter() of the testing framework.
     *
     * @param int<0, max> $paymentMethods the number of payment methods that are associated with this event
     */
    public function setNumberOfPaymentMethods(int $paymentMethods): void
    {
        $this->setRecordPropertyInteger('payment_methods', $paymentMethods);
    }

    /**
     * Sets the number of organizing partners for this record.
     *
     * TODO: This function needs to be removed once the testing framework
     * can update the counter for the number of organizing partners.
     *
     * @param int<0, max> $numberOfOrganizingPartners
     *        the number of organizing partners that are associated with this event
     */
    public function setNumberOfOrganizingPartners(int $numberOfOrganizingPartners): void
    {
        $this->setRecordPropertyInteger(
            'organizing_partners',
            $numberOfOrganizingPartners,
        );
    }

    /**
     * Sets the number of categories for this record.
     *
     * TODO: This function needs to be removed once the testing framework can update the counter for the number of
     * categories.
     *
     * @param int<0, max> $number the number of categories that are associated with this event
     */
    public function setNumberOfCategories(int $number): void
    {
        $this->setRecordPropertyInteger('categories', $number);
    }

    /**
     * Sets the number of organizers for this record.
     *
     * TODO: This function needs to be removed once the testing framework
     * can update the counter for the number of organizers.
     *
     * @param int<0, max> $number the number of organizers that are associated with this event
     */
    public function setNumberOfOrganizers(int $number): void
    {
        $this->setRecordPropertyInteger('organizers', $number);
    }

    /**
     * Sets the number of speakers for this record.
     *
     * TODO: This function needs to be removed once the testing framework
     * can update the counter for the number of speakers.
     *
     * @param int<0, max> $number the number of speakers that are associated with this event
     */
    public function setNumberOfSpeakers(int $number): void
    {
        $this->setRecordPropertyInteger('speakers', $number);
    }

    /**
     * Sets the number of partners for this record.
     *
     * TODO: This function needs to be removed once the testing framework
     * can update the counter for the number of partners.
     *
     * @param int<0, max> $number the number of partners that are associated with this event
     */
    public function setNumberOfPartners(int $number): void
    {
        $this->setRecordPropertyInteger('partners', $number);
    }

    /**
     * Sets the number of tutors for this record.
     *
     * TODO: This function needs to be removed once the testing framework
     * can update the counter for the number of tutors.
     *
     * @param int<0, max> $number the number of tutors that are associated with this event
     */
    public function setNumberOfTutors(int $number): void
    {
        $this->setRecordPropertyInteger('tutors', $number);
    }

    /**
     * Sets the number of leaders for this record.
     *
     * TODO: This function needs to be removed once the testing framework
     * can update the counter for the number of leaders.
     *
     * @param int<0, max> $number the number of leaders that are associated with this event
     */
    public function setNumberOfLeaders(int $number): void
    {
        $this->setRecordPropertyInteger('leaders', $number);
    }

    /**
     * Sets the "hidden" flag of this record (concerning the visibility in
     * TYPO3).
     *
     * @param bool $hidden whether this record should be marked as hidden
     */
    public function setHidden(bool $hidden): void
    {
        $this->setRecordPropertyBoolean('hidden', $hidden);
    }

    /**
     * Sets the UID of the owner FE user.
     *
     * @param int $ownerUid the UID of the owner FE user, must be >= 0
     */
    public function setOwnerUid(int $ownerUid): void
    {
        $this->setRecordPropertyInteger('owner_feuser', $ownerUid);
    }

    /**
     * Sets the file name of the image.
     *
     * @param string $fileName the name of the image, must not be empty
     */
    public function setImage(string $fileName): void
    {
        $this->setRecordPropertyString('image', $fileName);
    }

    /**
     * Sets whether multiple registrations are allowed.
     *
     * @param bool $allowMultipleRegistrations whether multiple registrations should be allowed
     */
    public function setAllowsMultipleRegistrations(bool $allowMultipleRegistrations): void
    {
        $this->setRecordPropertyBoolean(
            'allows_multiple_registrations',
            $allowMultipleRegistrations,
        );
    }

    /**
     * Sets this event's license expiry.
     *
     * @param int $expiry the license expiry as a timestamp, may be 0
     */
    public function setExpiry(int $expiry): void
    {
        $this->setRecordPropertyInteger('expiry', $expiry);
    }

    /**
     * Gets our place (or places) with address and links as HTML, not RTE'ed yet,
     * separated by LF.
     *
     * Returns a localized string "will be announced" if the seminar has no
     * places set.
     *
     * @return string our places description (or '' if there is an error)
     */
    public function getPlaceWithDetailsRaw(): string
    {
        return parent::getPlaceWithDetailsRaw();
    }

    /**
     * Sets the number of lodgings for this record.
     *
     * @param int<0, max> $lodgings the number of lodgings that are associated with this event
     */
    public function setNumberOfLodgings(int $lodgings): void
    {
        $this->setRecordPropertyInteger('lodgings', $lodgings);
    }

    /**
     * Gets our speaker (or speakers), as HTML with details and URLs, but not
     * RTE'ed yet.
     * Returns an empty string if this event doesn't have any speakers.
     *
     * As speakers can be related to this event as speakers, partners, tutors or
     * leaders, the type relation can be specified. The default is "speakers".
     *
     * @param 'speakers'|'tutors'|'leaders'|'partners' $speakerType
     *
     * @return string our speakers (or '' if there is an error)
     */
    public function getSpeakersWithDescriptionRaw(string $speakerType = 'speakers'): string
    {
        return parent::getSpeakersWithDescriptionRaw($speakerType);
    }

    /**
     * Gets our allowed payment methods, just as plain text separated by LF,
     * without the detailed description.
     * Returns an empty string if this seminar doesn't have any payment methods.
     *
     * @return string our payment methods as plain text (or '' if there is an error)
     */
    public function getPaymentMethodsPlainShort(): string
    {
        return parent::getPaymentMethodsPlainShort();
    }

    /**
     * Gets our organizer's names (and URLs), separated by LF.
     *
     * @return string names and homepages of our organizers or an
     *                empty string if there are no organizers
     */
    public function getOrganizersRaw(): string
    {
        return parent::getOrganizersRaw();
    }

    /**
     * Sets whether registration is needed.
     *
     * @param bool $needsRegistration whether registration is needed
     */
    public function setNeedsRegistration(bool $needsRegistration): void
    {
        $this->setRecordPropertyBoolean('needs_registration', $needsRegistration);
    }

    /**
     * Sets the registration deadline.
     *
     * @param int $registrationDeadline the registration deadline as timestamp, set to 0 to unset the registration
     *     deadline
     */
    public function setRegistrationDeadline(int $registrationDeadline): void
    {
        $this->setRecordPropertyInteger(
            'deadline_registration',
            $registrationDeadline,
        );
    }

    /**
     * Sets the seminar to have unlimitedVacancies by setting needs_registration
     * to 1 and attendees_max to 0.
     */
    public function setUnlimitedVacancies(): void
    {
        $this->setNeedsRegistration(true);
        $this->setAttendancesMax(0);
    }

    /**
     * Sets the registration begin date.
     *
     * @param int $registrationBeginDate the registration begin date as time-stamp, set to 0 to
     *                unset the registration begin date
     */
    public function setRegistrationBeginDate(int $registrationBeginDate): void
    {
        $this->setRecordPropertyInteger(
            'begin_date_registration',
            $registrationBeginDate,
        );
    }

    /**
     * Sets the number of offline registrations.
     *
     * @param int<0, max> $offlineRegistrations the number of offline registrations for this event
     */
    public function setOfflineRegistrationNumber(int $offlineRegistrations): void
    {
        $this->setRecordPropertyInteger('offline_attendees', $offlineRegistrations);
    }

    /**
     * Checks a integer element of the record data array for existence and
     * non-emptiness. If we are a date record, it'll be retrieved from the
     * corresponding topic record.
     *
     * @param non-empty-string $key
     *
     * @return bool TRUE if the corresponding integer exists and is non-empty
     */
    public function hasTopicInteger(string $key): bool
    {
        return parent::hasTopicInteger($key);
    }

    /**
     * Gets an int element of the record data array.
     * If the array has not been initialized properly, 0 is returned instead.
     * If we are a date record, it'll be retrieved from the corresponding
     * topic record.
     *
     * @param non-empty-string $key
     *
     * @return int the corresponding element from the record data array
     */
    public function getTopicInteger(string $key): int
    {
        return parent::getTopicInteger($key);
    }

    /**
     * Sets an int element of the record data array.
     *
     * @param non-empty-string $key
     * @param int $value the value that will be written into the element
     */
    public function setRecordPropertyInteger(string $key, int $value): void
    {
        parent::setRecordPropertyInteger($key, $value);
    }

    /**
     * Sets a string element of the record data array (and trims it).
     *
     * @param non-empty-string $key
     * @param string $value the value that will be written into the element
     */
    public function setRecordPropertyString(string $key, string $value): void
    {
        parent::setRecordPropertyString($key, $value);
    }
}
