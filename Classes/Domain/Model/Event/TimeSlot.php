<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Venue;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

/**
 * This class represents one of multiple time slots of an event, e.g., the first day from 10:00-17:00.
 */
class TimeSlot extends AbstractEntity
{
    protected \DateTime $start;

    protected \DateTime $end;

    /**
     * @var Venue|null
     * @phpstan-var Venue|LazyLoadingProxy|null
     * @Lazy
     */
    protected $venue;

    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $room = '';

    public function getStart(): \DateTime
    {
        return $this->start;
    }

    public function setStart(\DateTime $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): \DateTime
    {
        return $this->end;
    }

    public function setEnd(\DateTime $end): void
    {
        $this->end = $end;
    }

    public function getVenue(): ?Venue
    {
        $venue = $this->venue;
        if ($venue instanceof LazyLoadingProxy) {
            $venue = $venue->_loadRealInstance();
            $venue = ($venue instanceof Venue) ? $venue : null;
            $this->venue = $venue;
        }

        return $venue;
    }

    public function setVenue(?Venue $venue): void
    {
        $this->venue = $venue;
    }

    public function getRoom(): string
    {
        return $this->room;
    }

    public function setRoom(string $room): void
    {
        $this->room = $room;
    }
}
