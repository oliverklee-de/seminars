<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BagBuilder;

use OliverKlee\Seminars\Bag\RegistrationBag;
use OliverKlee\Seminars\Model\FrontEndUser;

/**
 * This builder class creates customized registration bag objects.
 *
 * @extends AbstractBagBuilder<RegistrationBag>
 */
class RegistrationBagBuilder extends AbstractBagBuilder
{
    /**
     * @var class-string<RegistrationBag> class name of the bag class that will be built
     */
    protected string $bagClassName = RegistrationBag::class;

    /**
     * @var non-empty-string the table name of the bag to build
     */
    protected string $tableName = 'tx_seminars_attendances';

    /**
     * @var string the sorting field
     */
    protected string $orderBy = 'crdate';

    /**
     * Limits the bag to the registrations of the events provided by the
     * parameter `$eventUids`.
     *
     * @param positive-int $eventUid the UID of the event to which the registration selection should be limited
     */
    public function limitToEvent(int $eventUid): void
    {
        $this->whereClauseParts['event'] = 'tx_seminars_attendances.seminar=' . $eventUid;
    }

    /**
     * Limits the bag to the registrations on the registration queue.
     */
    public function limitToOnQueue(): void
    {
        $this->whereClauseParts['queue'] = 'tx_seminars_attendances.registration_queue=1';
    }

    /**
     * Limits the bag to the regular registrations (which are not on the
     * registration queue).
     */
    public function limitToRegular(): void
    {
        $this->whereClauseParts['queue'] = 'tx_seminars_attendances.registration_queue=0';
    }

    /**
     * Removes the limitation for regular or on queue registrations.
     */
    public function removeQueueLimitation(): void
    {
        unset($this->whereClauseParts['queue']);
    }

    /**
     * Limits the bag to contain only registrations with seats equal or less
     * than the seats given in the parameter $seats.
     *
     * @param int<0, max> $seats the number of seats to filter for, set to 0 to remove the limitation, must be >= 0
     */
    public function limitToSeatsAtMost(int $seats = 0): void
    {
        if ($seats === 0) {
            unset($this->whereClauseParts['seats']);
            return;
        }

        $this->whereClauseParts['seats'] = 'tx_seminars_attendances.seats<=' . $seats;
    }

    /**
     * Limits the bag to registrations to the front-end user $user.
     *
     * These registration will either be those for which $user has signed up
     * himself, or for which they have been entered as "additional registered
     * persons".
     *
     * @param FrontEndUser|null $user the front-end user to limit the bag for,
     *        set to null to remove the limitation
     */
    public function limitToAttendee(?FrontEndUser $user = null): void
    {
        if ($user === null) {
            unset($this->whereClauseParts['attendee']);
            return;
        }

        $whereClause = 'tx_seminars_attendances.user = ' . $user->getUid();
        if ($user->getRegistration() !== null) {
            $whereClause .= ' OR tx_seminars_attendances.uid = ' .
                $user->getRegistration()->getUid();
        }

        $this->whereClauseParts['attendee'] = $whereClause;
    }

    /**
     * Sets the `ORDER BY` by statement for the bag to build and joins the
     * registration results with the corresponding events.
     *
     * @param string $orderBy the ORDER BY statement to set, may be empty
     */
    public function setOrderByEventColumn(string $orderBy): void
    {
        $this->addAdditionalTableName('tx_seminars_seminars');
        $this->whereClauseParts['orderByEvent'] = 'tx_seminars_attendances.seminar = tx_seminars_seminars.uid';
        $this->setOrderBy($orderBy);
    }

    /**
     * Limits the bag to registrations to which a non-deleted FE user record
     * exists.
     */
    public function limitToExistingUsers(): void
    {
        $this->whereClauseParts['existingUsers'] = 'EXISTS (
            SELECT * FROM fe_users WHERE fe_users.uid = tx_seminars_attendances.user' .
            $this->pageRepository->enableFields('fe_users') . ')';
    }
}
