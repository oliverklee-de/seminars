<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Venue;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * This class represents the timeslots
 */
class TimeSlot extends AbstractEntity
{
    protected ?\DateTime $start = null;

    protected ?\DateTime $end = null;

    protected ?Venue $venue = null;
    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $room = '';

    /**
     * @return \DateTime|null
     */
    public function getStart(): ?\DateTime
    {
        return $this->start;
    }

    /**
     * @param \DateTime|null $start
     */
    public function setStart(?\DateTime $start): void
    {
        $this->start = $start;
    }

    /**
     * @return \DateTime|null
     */
    public function getEnd(): ?\DateTime
    {
        return $this->end;
    }

    /**
     * @param \DateTime|null $end
     */
    public function setEnd(?\DateTime $end): void
    {
        $this->end = $end;
    }

    /**
     * @return Venue|null
     */
    public function getVenue(): ?Venue
    {
        return $this->venue;
    }

    /**
     * @param Venue|null $venue
     */
    public function setVenue(?Venue $venue): void
    {
        $this->venue = $venue;
    }

    /**
     * @return string
     */
    public function getRoom(): string
    {
        return $this->room;
    }

    /**
     * @param string $room
     */
    public function setRoom(string $room): void
    {
        $this->room = $room;
    }


}
