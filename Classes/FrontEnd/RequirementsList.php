<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\Service\SingleViewLinkBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is a view which creates the requirements lists for the front end.
 */
class RequirementsList extends AbstractView
{
    private ?LegacyEvent $event = null;

    private ?SingleViewLinkBuilder $linkBuilder = null;

    public function setEvent(LegacyEvent $event): void
    {
        $this->event = $event;
    }

    /**
     * Creates the list of required events.
     *
     * @return string HTML code of the list, will not be empty
     */
    public function render(): string
    {
        if (!$this->event instanceof LegacyEvent) {
            throw new \BadMethodCallException(
                'No event was set, please set an event before calling render.',
                1333293250,
            );
        }

        if (!$this->linkBuilder instanceof SingleViewLinkBuilder) {
            $configuration = $this->getConfigurationWithFlexForms();
            $this->setLinkBuilder(GeneralUtility::makeInstance(SingleViewLinkBuilder::class, $configuration));
        }

        $output = '';

        $eventMapper = GeneralUtility::makeInstance(MapperRegistry::class)->getByClassName(EventMapper::class);
        /** @var LegacyEvent $requirement */
        foreach ($this->getRequirements() as $requirement) {
            $requirementUid = $requirement->getUid();
            \assert($requirementUid > 0);
            $event = $eventMapper->find($requirementUid);

            $singleViewUrl = $this->linkBuilder->createRelativeUrlForEvent($event);
            $this->setMarker(
                'requirement_url',
                \htmlspecialchars($singleViewUrl, ENT_QUOTES | ENT_HTML5),
            );

            $this->setMarker(
                'requirement_title',
                \htmlspecialchars($event->getTitle(), ENT_QUOTES | ENT_HTML5),
            );
            $output .= $this->getSubpart('SINGLE_REQUIREMENT');
        }
        $this->setSubpart('SINGLE_REQUIREMENT', $output);

        return $this->getSubpart('FIELD_WRAPPER_REQUIREMENTS');
    }

    /**
     * Returns the requirements which should be displayed.
     *
     * @return EventBag the requirements still to be displayed, might be empty
     */
    private function getRequirements(): EventBag
    {
        return $this->event->getRequirements();
    }

    public function setLinkBuilder(SingleViewLinkBuilder $linkBuilder): void
    {
        $this->linkBuilder = $linkBuilder;
    }
}
