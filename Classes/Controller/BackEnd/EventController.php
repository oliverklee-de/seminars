<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller for the event list in the BE module.
 */
class EventController extends ActionController
{
    private ModuleTemplateFactory $moduleTemplateFactory;

    private EventRepository $eventRepository;

    private Permissions $permissions;

    private EventStatisticsCalculator $eventStatisticsCalculator;

    private PageRenderer $pageRenderer;

    private LanguageService $languageService;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        EventRepository $eventRepository,
        Permissions $permissions,
        EventStatisticsCalculator $eventStatisticsCalculator,
        PageRenderer $pageRenderer
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->eventRepository = $eventRepository;
        $this->permissions = $permissions;
        $this->eventStatisticsCalculator = $eventStatisticsCalculator;
        $this->pageRenderer = $pageRenderer;

        $languageService = $GLOBALS['LANG'] ?? null;
        \assert($languageService instanceof LanguageService);
        $this->languageService = $languageService;
    }

    private function redirectToOverviewAction(): ResponseInterface
    {
        return $this->redirect('overview', 'BackEnd\\Module');
    }

    /**
     * @param positive-int $eventUid
     */
    public function hideAction(int $eventUid): ResponseInterface
    {
        $this->eventRepository->hideViaDataHandler($eventUid);

        return $this->redirectToOverviewAction();
    }

    /**
     * @param positive-int $eventUid
     */
    public function unhideAction(int $eventUid): ResponseInterface
    {
        $this->eventRepository->unhideViaDataHandler($eventUid);

        return $this->redirectToOverviewAction();
    }

    /**
     * @param positive-int $eventUid
     */
    public function deleteAction(int $eventUid): ResponseInterface
    {
        $this->eventRepository->deleteViaDataHandler($eventUid);

        $message = $this->languageService
            ->sL('LLL:EXT:seminars/Resources/Private/Language/locallang.xlf:backEndModule.message.eventDeleted');
        $this->addFlashMessage($message);

        return $this->redirectToOverviewAction();
    }

    /**
     * @param int<0, max> $pageUid
     * @param string $searchTerm
     */
    public function searchAction(int $pageUid, string $searchTerm = ''): ResponseInterface
    {
        $this->view->assign('permissions', $this->permissions);
        $this->view->assign('pageUid', $pageUid);

        $events = $this->eventRepository->findBySearchTermInBackEndMode($pageUid, $searchTerm);
        $this->eventRepository->enrichWithRawData($events);
        foreach ($events as $event) {
            $this->eventStatisticsCalculator->enrichWithStatistics($event);
        }
        $this->view->assign('events', $events);

        $this->view->assign('searchTerm', \trim($searchTerm));

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $this->pageRenderer->loadJavaScriptModule('@oliverklee/seminars/DeleteConfirmationModule.js');
        } else {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Seminars/BackEnd/DeleteConfirmationAmdModule');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param positive-int $eventUid
     */
    public function duplicateAction(int $eventUid): ResponseInterface
    {
        $this->eventRepository->duplicateViaDataHandler($eventUid);

        return $this->redirectToOverviewAction();
    }
}
