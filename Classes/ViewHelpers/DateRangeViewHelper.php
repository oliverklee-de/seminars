<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\ViewHelpers;

use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class represents a view helper for rendering date ranges.
 *
 * @internal
 */
class DateRangeViewHelper
{
    /**
     * Renders the time span in a human-readable way.
     *
     * Returns a date range if the timespan takes several days.
     */
    public function render(EventDateInterface $event): string
    {
        if (!($event->getStart() instanceof \DateTime)) {
            return '';
        }

        $start = $event->getStart();
        $end = $event->getEnd();

        $isOpenEnded = ($end === null);
        if ($isOpenEnded || $this->isSameDay($start, $end)) {
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
        $format = LocalizationUtility::translate('dateFormat', 'seminars');
        \assert(\is_string($format));

        return $date->format($format);
    }
}
