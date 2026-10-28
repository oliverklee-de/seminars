<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Email;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class creates a salutation for emails.
 */
class SalutationBuilder
{
    private ConfigurationRegistry $configurationRegistry;

    public function __construct(ConfigurationRegistry $configurationRegistry)
    {
        $this->configurationRegistry = $configurationRegistry;
    }

    /**
     * Creates the salutation for the given user.
     *
     * The salutation is localized and contains the name of the user.
     *
     * @return non-empty-string the localized salutation with a trailing comma
     */
    public function getSalutation(FrontendUser $user): string
    {
        $salutationParts = [];

        $salutationMode = $this->configurationRegistry->getByNamespace('plugin.tx_seminars')->getAsString('salutation');
        switch ($salutationMode) {
            case 'informal':
                $label = LocalizationUtility::translate('email_hello_informal', 'seminars');
                \assert(\is_string($label));
                \assert($label !== '');
                $salutationParts['dear'] = $label;
                $firstName = $user->getFirstName();
                $salutationParts['name'] = ($firstName !== '') ? $firstName : $user->getName();
                break;
            default:
                $label = LocalizationUtility::translate('email_hello_formal_99', 'seminars');
                \assert(\is_string($label));
                \assert($label !== '');
                $salutationParts['dear'] = $label;
                $salutationParts['name'] = $user->getName();
        }

        return \implode(' ', $salutationParts) . ',';
    }

    /**
     * Creates an email introduction with the given event's title, date and time prepended with the given introduction
     * string.
     *
     * @param non-empty-string $introductionBegin
     *        the start of the introduction, must contain %s as place to fill the title of the event in
     */
    public function createIntroduction(string $introductionBegin, EventDateInterface $event): string
    {
        $result = \sprintf($introductionBegin, $event->getDisplayTitle());
        $start = $event->getStart();
        if (!($start instanceof \DateTime)) {
            return $result;
        }

        $eventDateLabel = LocalizationUtility::translate('email_eventDate', 'seminars');
        \assert(\is_string($eventDateLabel));
        $result .= ' ' . \sprintf($eventDateLabel, $this->renderEventDate($event));

        if ($event->getTimeSlots()->count() === 0) {
            $timeAtLabel = LocalizationUtility::translate('email_timeAt', 'seminars');
            \assert(\is_string($timeAtLabel));
            $time = $this->formatTime($start);
            $label = ' ' . $timeAtLabel;
            $result .= \sprintf($label, $time);
        }

        return $result;
    }

    private function renderEventDate(EventDateInterface $event): string
    {
        $start = $event->getStart();
        assert($start instanceof \DateTime);
        $end = $event->getEnd();
        if (!($end instanceof \DateTime) || $this->isSameDay($start, $end)) {
            return $this->formatDate($start);
        }

        $rangeWithPlaceholders = LocalizationUtility::translate('range', 'seminars');
        \assert(is_string($rangeWithPlaceholders));

        return sprintf($rangeWithPlaceholders, $this->formatDate($start), $this->formatDate($end));
    }

    private function isSameDay(\DateTime $start, \DateTime $end): bool
    {
        return $start->format('Y-m-d') === $end->format('Y-m-d');
    }

    private function formatDate(\DateTime $date): string
    {
        return $date->format($this->getDateFormat());
    }

    private function getDateFormat(): string
    {
        $format = LocalizationUtility::translate('dateFormat', 'seminars');
        \assert(\is_string($format));

        return $format;
    }

    private function formatTime(\DateTime $date): string
    {
        $hours = LocalizationUtility::translate('label_hours', 'seminars');
        \assert(\is_string($hours));

        return $date->format($this->getTimeFormat()) . ' ' . $hours;
    }

    private function getTimeFormat(): string
    {
        $format = LocalizationUtility::translate('timeFormat', 'seminars');
        \assert(\is_string($format));

        return $format;
    }
}
