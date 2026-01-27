<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller;

use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Event\EventTopic;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Organizer;
use OliverKlee\Seminars\Domain\Repository\CategoryRepository;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\EventTypeRepository;
use OliverKlee\Seminars\Domain\Repository\FrontendUserRepository;
use OliverKlee\Seminars\Domain\Repository\OrganizerRepository;
use OliverKlee\Seminars\Domain\Repository\SpeakerRepository;
use OliverKlee\Seminars\Domain\Repository\VenueRepository;
use OliverKlee\Seminars\Seo\SlugGenerator;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;

/**
 * Plugin for editing single events in the FE.
 */
class FrontEndEditorController extends ActionController
{
    private EventRepository $eventRepository;

    private EventTypeRepository $eventTypeRepository;

    private OrganizerRepository $organizerRepository;

    private SpeakerRepository $speakerRepository;

    private VenueRepository $venueRepository;

    private CategoryRepository $categoryRepository;

    private FrontendUserRepository $userRepository;

    private SlugGenerator $slugGenerator;

    private EventStatisticsCalculator $eventStatisticsCalculator;

    private ErrorController $errorController;

    public function __construct(
        EventRepository $eventRepository,
        EventTypeRepository $eventTypeRepository,
        OrganizerRepository $organizerRepository,
        SpeakerRepository $speakerRepository,
        VenueRepository $venueRepository,
        CategoryRepository $categoryRepository,
        FrontendUserRepository $userRepository,
        SlugGenerator $slugGenerator,
        EventStatisticsCalculator $eventStatisticsCalculator,
        ErrorController $errorController
    ) {
        $this->eventRepository = $eventRepository;
        $this->eventTypeRepository = $eventTypeRepository;
        $this->organizerRepository = $organizerRepository;
        $this->speakerRepository = $speakerRepository;
        $this->venueRepository = $venueRepository;
        $this->categoryRepository = $categoryRepository;
        $this->userRepository = $userRepository;
        $this->slugGenerator = $slugGenerator;
        $this->eventStatisticsCalculator = $eventStatisticsCalculator;
        $this->errorController = $errorController;
    }

    /**
     * @return int<0, max>
     */
    private function getLoggedInUserUid(): int
    {
        $uid = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'id');
        \assert($uid >= 0);

        return $uid;
    }

    /**
     * @return int<0, max>
     */
    private function getDefaultOrganizerUid(): int
    {
        $userUid = $this->getLoggedInUserUid();
        if ($userUid <= 0) {
            return 0;
        }

        $user = $this->userRepository->findByUid($userUid);
        if (!($user instanceof FrontendUser)) {
            return 0;
        }

        return $user->getDefaultOrganizerUid();
    }

    public function indexAction(): ResponseInterface
    {
        $events = $this->eventRepository->findSingleEventsAndEventDatesByOwnerUid($this->getLoggedInUserUid());
        foreach ($events as $event) {
            $this->eventStatisticsCalculator->enrichWithStatistics($event);
        }
        $this->view->assign('events', $events);

        return $this->htmlResponse();
    }

    /**
     * @param SingleEvent|EventDate $event
     *
     * @throws PropagateResponseException
     */
    private function checkEventOwner($event): void
    {
        if ($event->getOwnerUid() !== $this->getLoggedInUserUid()) {
            $this->trigger403('You do not have permission to edit this event.');
        }
    }

    /**
     * @return never
     *
     * @throws PropagateResponseException
     */
    private function trigger403(string $message): void
    {
        throw new PropagateResponseException(
            $this->errorController->accessDeniedAction($this->request, $message),
            1769449755,
        );
    }

    /**
     * @IgnoreValidation("event")
     *
     * @throws PropagateResponseException
     */
    public function editSingleEventAction(SingleEvent $event): ResponseInterface
    {
        $this->checkEventOwner($event);

        $this->view->assign('event', $event);
        $this->assignAuxiliaryRecordsForSingleEventToView();
        $this->view->assign('defaultOrganizerUid', $this->getDefaultOrganizerUid());

        return $this->htmlResponse();
    }

    private function assignAuxiliaryRecordsForSingleEventToView(): void
    {
        $this->view->assign('eventTypes', $this->eventTypeRepository->findAllPlusNullEventType());
        $this->view->assign('organizers', $this->organizerRepository->findAll());
        $this->view->assign('speakers', $this->speakerRepository->findAll());
        $this->view->assign('venues', $this->venueRepository->findAll());
        $this->view->assign('categories', $this->categoryRepository->findAll());
    }

    /**
     * @throws PropagateResponseException
     */
    public function updateSingleEventAction(SingleEvent $event): ResponseInterface
    {
        $this->checkEventOwner($event);
        $this->updateAndSlaveSlugForSingleEvent($event);

        return $this->redirect('index');
    }

    public function newSingleEventAction(): ResponseInterface
    {
        $eventToCreate = GeneralUtility::makeInstance(SingleEvent::class);
        $this->view->assign('event', $eventToCreate);
        $this->assignAuxiliaryRecordsForSingleEventToView();
        $this->view->assign('defaultOrganizerUid', $this->getDefaultOrganizerUid());

        return $this->htmlResponse();
    }

    public function createSingleEventAction(SingleEvent $event): ResponseInterface
    {
        $event->setOwnerUid($this->getLoggedInUserUid());
        $defaultOrganizerUid = $this->getDefaultOrganizerUid();
        if ($defaultOrganizerUid > 0) {
            $organizer = $this->organizerRepository->findByUid($defaultOrganizerUid);
            if ($organizer instanceof Organizer) {
                $event->setSingleOrganizer($organizer);
            }
        }

        $folderSettings = $this->settings['folderForCreatedEvents'] ?? null;
        $folderUid = \is_string($folderSettings) ? (int)$folderSettings : 0;
        $event->setPid($folderUid);

        // We first need to persist the event to get a UID for it, so we can generate a slug.
        $this->eventRepository->add($event);
        $this->eventRepository->persistAll();
        $this->updateAndSlaveSlugForSingleEvent($event);

        return $this->redirect('index');
    }

    private function updateAndSlaveSlugForSingleEvent(SingleEvent $event): void
    {
        $uid = $event->getUid();
        \assert(\is_int($uid) && $uid > 0);
        $recordData = [
            'uid' => $uid,
            'object_type' => EventInterface::TYPE_SINGLE_EVENT,
            'title' => $event->getInternalTitle(),
        ];
        $event->setSlug($this->slugGenerator->generateSlug(['record' => $recordData]));

        $this->eventRepository->update($event);
        $this->eventRepository->persistAll();
    }

    /**
     * @IgnoreValidation("event")
     *
     * @throws PropagateResponseException
     */
    public function editEventDateAction(EventDate $event): ResponseInterface
    {
        $this->checkEventOwner($event);

        $this->view->assign('event', $event);
        $this->assignAuxiliaryRecordsForEventDateToView();
        $this->view->assign('defaultOrganizerUid', $this->getDefaultOrganizerUid());

        return $this->htmlResponse();
    }

    private function assignAuxiliaryRecordsForEventDateToView(): void
    {
        $this->view->assign('topics', $this->eventRepository->findAllTopicsPlusNullTopic());
        $this->view->assign('organizers', $this->organizerRepository->findAll());
        $this->view->assign('speakers', $this->speakerRepository->findAll());
        $this->view->assign('venues', $this->venueRepository->findAll());
    }

    /**
     * @throws PropagateResponseException
     */
    public function updateEventDateAction(EventDate $event): ResponseInterface
    {
        $this->checkEventOwner($event);
        $this->updateAndSlaveSlugForEventDate($event);

        return $this->redirect('index');
    }

    public function newEventDateAction(): ResponseInterface
    {
        $eventToCreate = GeneralUtility::makeInstance(EventDate::class);
        $this->view->assign('event', $eventToCreate);
        $this->assignAuxiliaryRecordsForEventDateToView();
        $this->view->assign('defaultOrganizerUid', $this->getDefaultOrganizerUid());

        return $this->htmlResponse();
    }

    public function createEventDateAction(EventDate $event): ResponseInterface
    {
        $event->setOwnerUid($this->getLoggedInUserUid());
        $defaultOrganizerUid = $this->getDefaultOrganizerUid();
        if ($defaultOrganizerUid > 0) {
            $organizer = $this->organizerRepository->findByUid($defaultOrganizerUid);
            if ($organizer instanceof Organizer) {
                $event->setSingleOrganizer($organizer);
            }
        }

        $folderSettings = $this->settings['folderForCreatedEvents'] ?? null;
        $folderUid = \is_string($folderSettings) ? (int)$folderSettings : 0;
        $event->setPid($folderUid);

        // We first need to persist the event to get a UID for it, so we can generate a slug.
        $this->eventRepository->add($event);
        $this->eventRepository->persistAll();
        $this->updateAndSlaveSlugForEventDate($event);

        return $this->redirect('index');
    }

    private function updateAndSlaveSlugForEventDate(EventDate $event): void
    {
        $uid = $event->getUid();
        \assert(\is_int($uid) && $uid > 0);
        $topic = $event->getTopic();
        $topicUid = $topic instanceof EventTopic ? $topic->getUid() : 0;
        \assert(\is_int($topicUid) && $topicUid >= 0);
        $recordData = [
            'uid' => $uid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'topic' => $topicUid,
            'title' => $event->getInternalTitle(),
        ];
        $event->setSlug($this->slugGenerator->generateSlug(['record' => $recordData]));

        $this->eventRepository->update($event);
        $this->eventRepository->persistAll();
    }
}
