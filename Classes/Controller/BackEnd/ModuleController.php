<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller for the event list in the BE module.
 */
class ModuleController extends ActionController
{
    use PageUidTrait;

    private ModuleTemplateFactory $moduleTemplateFactory;

    private EventRepository $eventRepository;

    private RegistrationRepository $registrationRepository;

    private EventStatisticsCalculator $eventStatisticsCalculator;

    private Permissions $permissions;

    private PageRenderer $pageRenderer;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        EventRepository $eventRepository,
        RegistrationRepository $registrationRepository,
        EventStatisticsCalculator $eventStatisticsCalculator,
        Permissions $permissions,
        PageRenderer $pageRenderer
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->eventRepository = $eventRepository;
        $this->registrationRepository = $registrationRepository;
        $this->eventStatisticsCalculator = $eventStatisticsCalculator;
        $this->permissions = $permissions;
        $this->pageRenderer = $pageRenderer;
    }

    public function overviewAction(): ResponseInterface
    {
        $pageUid = $this->getPageUid();

        $events = $this->eventRepository->findByPageUidInBackEndMode($pageUid);
        $this->eventRepository->enrichWithRawData($events);
        foreach ($events as $event) {
            $this->eventStatisticsCalculator->enrichWithStatistics($event);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $this->pageRenderer->loadJavaScriptModule('@oliverklee/seminars/DeleteConfirmationModule.js');
            $moduleTemplate->assign('permissions', $this->permissions);
            $moduleTemplate->assign('pageUid', $pageUid);
            $moduleTemplate->assign('events', $events);
            $moduleTemplate->assign(
                'numberOfRegistrations',
                $this->registrationRepository->countRegularRegistrationsByPageUid($pageUid),
            );
            $response = $moduleTemplate->renderResponse('BackEnd/Module/Overview');
        } else {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Seminars/BackEnd/DeleteConfirmationAmdModule');
            $this->view->assign('permissions', $this->permissions);
            $this->view->assign('pageUid', $pageUid);
            $this->view->assign('events', $events);
            $this->view->assign(
                'numberOfRegistrations',
                $this->registrationRepository->countRegularRegistrationsByPageUid($pageUid),
            );
            $moduleTemplate->setContent($this->view->render());
            $response = $this->htmlResponse($moduleTemplate->renderContent());
        }

        return $response;
    }
}
