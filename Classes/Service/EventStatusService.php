<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Service;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class takes care of managing the status of events.
 */
class EventStatusService implements SingletonInterface
{
    private EventMapper $eventMapper;

    private Context $context;

    public function __construct()
    {
        $this->eventMapper = GeneralUtility::makeInstance(MapperRegistry::class)->getByClassName(EventMapper::class);
        $this->context = GeneralUtility::makeInstance(Context::class);
    }

    /**
     * Automatically updates the status of the given event and saves it.
     *
     * If the event is not in the PLANNED status anymore or the automatic status for this event is disabled,
     * this method is a no-op.
     *
     * @return bool true if the status of the given has been changed, false otherwise
     */
    public function updateStatusAndSave(Event $event): bool
    {
        if (!$event->shouldAutomaticallyConfirmOrCancel() || !$event->isPlanned()) {
            return false;
        }

        $eventWasUpdated = false;
        $now = $this->context->getPropertyFromAspect('date', 'timestamp');
        \assert(\is_int($now));
        \assert($now > 0);
        if ($event->hasEnoughRegistrations()) {
            $this->confirmAndSave($event);
            $eventWasUpdated = true;
        } elseif ($event->hasRegistrationDeadline() && ($event->getRegistrationDeadlineAsUnixTimeStamp() < $now)) {
            $this->cancelAndSave($event);
            $eventWasUpdated = true;
        }

        return $eventWasUpdated;
    }

    public function cancelAndSave(Event $event): void
    {
        $event->cancel();
        $this->eventMapper->save($event);
    }

    public function confirmAndSave(Event $event): void
    {
        $event->confirm();
        $this->eventMapper->save($event);
    }
}
