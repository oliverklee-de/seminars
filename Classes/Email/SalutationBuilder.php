<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Email;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\OldModel\LegacyEvent;
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
     *
     * @throws \InvalidArgumentException
     */
    public function createIntroduction(string $introductionBegin, LegacyEvent $event): string
    {
        $result = \sprintf($introductionBegin, $event->getTitle());
        if (!$event->hasDate()) {
            return $result;
        }

        $eventDateLabel = LocalizationUtility::translate('email_eventDate', 'seminars');
        \assert(\is_string($eventDateLabel));
        $result .= ' ' . \sprintf($eventDateLabel, $event->getDate());

        if (!$event->hasTimeslots()) {
            $timeAtLabel = LocalizationUtility::translate('email_timeAt', 'seminars');
            \assert(\is_string($timeAtLabel));
            $time = $this->formatTime($event->getBeginDateAsTimestamp());
            $label = ' ' . $timeAtLabel;
            $result .= \sprintf($label, $time);
        }

        return $result;
    }

    private function formatTime(int $timestamp): string
    {
        $hours = LocalizationUtility::translate('label_hours', 'seminars');
        \assert(\is_string($hours));

        return \date($this->getTimeFormat(), $timestamp) . ' ' . $hours;
    }

    private function getTimeFormat(): string
    {
        $format = LocalizationUtility::translate('timeFormat', 'seminars');
        \assert(\is_string($format));

        return $format;
    }
}
