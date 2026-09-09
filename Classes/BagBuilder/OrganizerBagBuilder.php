<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BagBuilder;

use OliverKlee\Seminars\Bag\OrganizerBag;

/**
 * This builder class creates customized organizer bag objects.
 *
 * @extends AbstractBagBuilder<OrganizerBag>
 */
class OrganizerBagBuilder extends AbstractBagBuilder
{
    /**
     * @var class-string<OrganizerBag> class name of the bag class that will be built
     */
    protected string $bagClassName = OrganizerBag::class;

    /**
     * @var non-empty-string the table name of the bag to build
     */
    protected string $tableName = 'tx_seminars_organizers';

    /**
     * Limits the bag to contain only organizers of the event given in the
     * parameter $eventUid (must be a single or date event as topic events don't
     * have any organizers).
     *
     * @param positive-int $eventUid the event UID to limit the organizers for, must be > 0
     */
    public function limitToEvent(int $eventUid): void
    {
        $this->whereClauseParts['event'] = 'EXISTS ('
            . 'SELECT * FROM tx_seminars_seminars_organizers_mm'
            . ' WHERE uid_local = ' . $eventUid . ' AND uid_foreign = '
            . 'tx_seminars_organizers.uid)';

        $this->orderBy = '(SELECT sorting '
            . 'FROM tx_seminars_seminars_organizers_mm WHERE uid_local = '
            . $eventUid . ' AND uid_foreign = tx_seminars_organizers.uid)';
    }
}
