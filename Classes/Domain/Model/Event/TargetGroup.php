<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * This class represents a target group, like "Beginner", "Pro" etc.
 */
class TargetGroup extends AbstractEntity
{
    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
