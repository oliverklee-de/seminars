<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Repository\Event;

use OliverKlee\Seminars\Domain\Model\Event\TimeSlot;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<TimeSlot>
 */
class TimeSlotRepository extends Repository
{
}
