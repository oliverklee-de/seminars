<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\EmailService;
use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller for sending emails to the attendees of an event.
 */
class EmailController extends ActionController
{
    private ModuleTemplateFactory $moduleTemplateFactory;

    private Permissions $permissions;

    private EmailService $emailService;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        Permissions $permissions,
        EmailService $emailService
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->permissions = $permissions;
        $this->emailService = $emailService;
    }

    /**
     * Action for the displaying the email form.
     *
     * @param positive-int $pageUid
     *
     * @IgnoreValidation("event")
     */
    public function composeAction(
        Event $event,
        int $pageUid,
        string $subject = '',
        string $body = ''
    ): ResponseInterface {
        $this->checkPermissions();

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $moduleTemplate->assign('event', $event);
            $moduleTemplate->assign('pageUid', $pageUid);
            $moduleTemplate->assign('subject', $subject);
            $moduleTemplate->assign('body', $body);
            $response = $moduleTemplate->renderResponse('BackEnd/Email/Compose');
        } else {
            $this->view->assign('event', $event);
            $this->view->assign('pageUid', $pageUid);
            $this->view->assign('subject', $subject);
            $this->view->assign('body', $body);
            $moduleTemplate->setContent($this->view->render());
            $response = $this->htmlResponse($moduleTemplate->renderContent());
        }

        return $response;
    }

    /**
     * Action for the sending the email.
     *
     * @IgnoreValidation("event")
     */
    public function sendAction(Event $event, string $subject, string $body): ResponseInterface
    {
        $this->checkPermissions();

        if ($event instanceof EventDateInterface) {
            $this->emailService->sendPlainTextEmailToRegularAttendees($event, $subject, $body);
        }

        return $this->redirect('overview', 'BackEnd\\Module');
    }

    /**
     * Checks the that the user has read permissions for events and registrations.
     *
     * As this will not happen under normal circumstances (as there will be no UI to get to the email controller without
     * having the necessary permissions), this method will throw an exception instead of creating a nice flash message.
     */
    private function checkPermissions(): void
    {
        if (!$this->permissions->hasReadAccessToEvents()) {
            throw new \RuntimeException('Missing read permissions for events.', 1671020157);
        }
        if (!$this->permissions->hasReadAccessToRegistrations()) {
            throw new \RuntimeException('Missing read permissions for registrations.', 1671020198);
        }
    }
}
