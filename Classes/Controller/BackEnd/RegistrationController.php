<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Csv\CsvDownloader;
use OliverKlee\Seminars\Csv\CsvResponse;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller for the registration list in the BE module.
 */
class RegistrationController extends ActionController
{
    use PageUidTrait;

    private const CSV_FILENAME = 'registrations.csv';

    private ModuleTemplateFactory $moduleTemplateFactory;

    private RegistrationRepository $registrationRepository;

    private EventRepository $eventRepository;

    private LanguageService $languageService;

    private EventStatisticsCalculator $eventStatisticsCalculator;

    private Permissions $permissions;

    private PageRenderer $pageRenderer;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        RegistrationRepository $registrationRepository,
        EventRepository $eventRepository,
        EventStatisticsCalculator $eventStatisticsCalculator,
        Permissions $permissions,
        PageRenderer $pageRenderer
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->registrationRepository = $registrationRepository;
        $this->eventRepository = $eventRepository;
        $this->eventStatisticsCalculator = $eventStatisticsCalculator;
        $this->permissions = $permissions;
        $this->pageRenderer = $pageRenderer;

        $languageService = $GLOBALS['LANG'] ?? null;
        \assert($languageService instanceof LanguageService);
        $this->languageService = $languageService;
    }

    /**
     * @param int<1, max> $eventUid
     *
     * @throws \RuntimeException
     */
    public function showForEventAction(int $eventUid): ResponseInterface
    {
        $event = $this->eventRepository->findOneByUidForBackend($eventUid);
        if (!($event instanceof Event)) {
            throw new \RuntimeException('Event with UID ' . $eventUid . ' not found.', 1698859637);
        }

        $this->eventStatisticsCalculator->enrichWithStatistics($event);

        if ($event instanceof EventDateInterface) {
            $regularRegistrations = $this->registrationRepository->findRegularRegistrationsByEvent($eventUid);
            $this->registrationRepository->enrichWithRawData($regularRegistrations);
            $waitingListRegistrations = $this->registrationRepository->findWaitingListRegistrationsByEvent($eventUid);
            $this->registrationRepository->enrichWithRawData($waitingListRegistrations);
            $nonbindingReservations = $this->registrationRepository->findNonbindingReservationsByEvent($eventUid);
            $this->registrationRepository->enrichWithRawData($nonbindingReservations);
        } else {
            $regularRegistrations = null;
            $waitingListRegistrations = null;
            $nonbindingReservations = null;
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $this->pageRenderer->loadJavaScriptModule('@oliverklee/seminars/DeleteConfirmationModule.js');
            $moduleTemplate->assign('permissions', $this->permissions);
            $moduleTemplate->assign('pageUid', $this->getPageUid());
            $moduleTemplate->assign('event', $event);
            $moduleTemplate->assign('regularRegistrations', $regularRegistrations);
            $moduleTemplate->assign('waitingListRegistrations', $waitingListRegistrations);
            $moduleTemplate->assign('nonbindingReservations', $nonbindingReservations);
            $response = $moduleTemplate->renderResponse('BackEnd/Registration/ShowForEvent');
        } else {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Seminars/BackEnd/DeleteConfirmationAmdModule');
            $this->view->assign('permissions', $this->permissions);
            $this->view->assign('pageUid', $this->getPageUid());
            $this->view->assign('event', $event);
            $this->view->assign('regularRegistrations', $regularRegistrations);
            $this->view->assign('waitingListRegistrations', $waitingListRegistrations);
            $this->view->assign('nonbindingReservations', $nonbindingReservations);
            $moduleTemplate->setContent($this->view->render());
            $response = $this->htmlResponse($moduleTemplate->renderContent());
        }

        return $response;
    }

    /**
     * @param int<1, max> $eventUid
     */
    public function exportCsvForEventAction(int $eventUid): ResponseInterface
    {
        $_GET['eventUid'] = $eventUid;

        return $this->exportCsvAction();
    }

    /**
     * @param int<1, max> $pageUid
     */
    public function exportCsvForPageUidAction(int $pageUid): ResponseInterface
    {
        $_GET['pid'] = $pageUid;

        return $this->exportCsvAction();
    }

    private function exportCsvAction(): ResponseInterface
    {
        $csvContent = GeneralUtility::makeInstance(CsvDownloader::class)->main();

        return GeneralUtility::makeInstance(CsvResponse::class, $csvContent, self::CSV_FILENAME);
    }

    /**
     * @param positive-int $registrationUid
     * @param positive-int $eventUid
     */
    public function deleteAction(int $registrationUid, int $eventUid): ResponseInterface
    {
        $this->registrationRepository->deleteViaDataHandler($registrationUid);

        $message = $this->languageService
            ->sL('LLL:EXT:seminars/Resources/Private/Language/locallang.xlf:backEndModule.message.registrationDeleted');
        $this->addFlashMessage($message);

        return $this->redirect('showForEvent', 'BackEnd\\Registration', null, ['eventUid' => $eventUid]);
    }
}
