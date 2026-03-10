<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use TYPO3\CMS\Extbase\Annotation\Validate;

/**
 * This trait provides methods that are useful for all event classes (`SingleEvent`, `EventDate` and `EventTopic`).
 *
 * @phpstan-require-extends Event
 * @phpstan-require-implements EventInterface
 */
trait EventTrait
{
    protected bool $hidden = false;

    /**
     * The title of this event as visible in the backend.
     * In the frontend, the title might be different, e.g., event dates will use the title of their
     * corresponding topic.
     *
     * @Validate("StringLength", options={"maximum": 255})
     * @Validate("NotEmpty")
     */
    protected string $internalTitle = '';

    /**
     * the UID of the FE user who has created the event
     *
     * @phpstan-var int<0, max>
     */
    protected int $ownerUid = 0;

    /**
     * @Validate("StringLength", options={"maximum": 262})
     */
    protected string $slug = '';

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getInternalTitle(): string
    {
        return $this->internalTitle;
    }

    /**
     * @param non-empty-string $name
     */
    public function setInternalTitle(string $name): void
    {
        $this->internalTitle = $name;
    }

    /**
     * @return int<0, max>
     */
    public function getOwnerUid(): int
    {
        return $this->ownerUid;
    }

    /**
     * @param int<0, max> $ownerUid
     */
    public function setOwnerUid(int $ownerUid): void
    {
        $this->ownerUid = $ownerUid;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * This is a stub method that gets overwritten by the `seminars_premium` extension.
     */
    public function hasDownloadableCertificate(): bool
    {
        return false;
    }
}
