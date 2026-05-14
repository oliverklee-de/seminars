<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\SchedulerTasks;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Email\GeneralEmailRole;
use OliverKlee\Oelib\Interfaces\Configuration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Email\EmailBuilder;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * This class takes care of sending the daily registration digest email for events.
 */
class RegistrationDigest implements SingletonInterface
{
    private Configuration $configuration;

    private EventMapper $eventMapper;

    public function __construct()
    {
        $this->configuration = ConfigurationRegistry::getInstance()
            ->getByNamespace('plugin.tx_seminars.registrationDigestEmail');
        $this->eventMapper = GeneralUtility::makeInstance(MapperRegistry::class)->getByClassName(EventMapper::class);
    }

    /**
     * Executes this service and sends out a digest email if this is enabled in the configuration and if there
     * is anything to send.
     */
    public function execute(): void
    {
        if (!$this->configuration->getAsBoolean('enable')) {
            return;
        }
        $events = $this->eventMapper->findForRegistrationDigestEmail();
        if ($events->isEmpty()) {
            return;
        }

        $email = $this->buildEmail($events);
        $email->send();

        $this->updateDateOfLastDigest($events);
    }

    /**
     * @param Collection<Event> $events
     */
    private function buildEmail(Collection $events): MailMessage
    {
        $configuration = $this->configuration;
        $from = new GeneralEmailRole($configuration->getAsString('fromEmail'), $configuration->getAsString('fromName'));
        $to = new GeneralEmailRole($configuration->getAsString('toEmail'), $configuration->getAsString('toName'));
        $emailBuilder = GeneralUtility::makeInstance(EmailBuilder::class);
        $emailBuilder
            ->from($from)
            ->to($to)
            ->subject(LocalizationUtility::translate('registrationDigestEmail_Subject', 'seminars'));

        /** @var non-empty-string $plaintextTemplatePath */
        $plaintextTemplatePath = $this->configuration->getAsString('plaintextTemplate');
        $plaintextBody = $this->createContent($plaintextTemplatePath, $events);
        $emailBuilder->text($plaintextBody);

        /** @var non-empty-string $htmlTemplatePath */
        $htmlTemplatePath = $this->configuration->getAsString('htmlTemplate');
        $htmlBody = $this->createContent($htmlTemplatePath, $events);
        $emailBuilder->html($htmlBody);

        return $emailBuilder->build();
    }

    /**
     * @param non-empty-string $templatePath in the EXT:... syntax
     * @param Collection<Event> $events
     */
    private function createContent(string $templatePath, Collection $events): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templatePath));
        $view->assign('events', $events);

        // @phpstan-ignore-next-line `TYPO3\CMS\Fluid\View\StandaloneView::render()` returns `?string`, not `string`.
        return (string)$view->render();
    }

    /**
     * @param Collection<Event> $events
     */
    private function updateDateOfLastDigest(Collection $events): void
    {
        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        /** @var Event $event */
        foreach ($events as $event) {
            $event->setDateOfLastRegistrationDigestEmailAsUnixTimeStamp($now);
            $this->eventMapper->save($event);
        }
    }
}
