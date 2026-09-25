<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\ViewHelpers;

use OliverKlee\Seminars\Model\Event;
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
     * Returns a localized string "will be announced" if there's no date set.
     *
     * Returns a date range if the timespan takes several days.
     *
     * @return string the timespan date
     */
    public function render(Event $event): string
    {
        if (!$event->hasBeginDate()) {
            return '';
        }

        $beginDate = $event->getBeginDateAsUnixTimeStamp();
        $endDate = $event->getEndDateAsUnixTimeStamp();

        $isOpenEnded = !$event->hasEndDate();
        if ($isOpenEnded || $this->isSameDay($beginDate, $endDate)) {
            return $this->getAsDateFormatYmd($beginDate);
        }

        return $this->getAsDateFormatYmd($beginDate) . '–' . $this->getAsDateFormatYmd($endDate);
    }

    /**
     * Returns whether the two given timestamps are on the same day.
     */
    protected function isSameDay(int $beginDate, int $endDate): bool
    {
        return $this->getAsDateFormatYmd($beginDate) === $this->getAsDateFormatYmd($endDate);
    }

    /**
     * Renders a UNIX timestamp in the localized date format.
     */
    protected function getAsDateFormatYmd(int $timestamp): string
    {
        $format = LocalizationUtility::translate('dateFormat', 'seminars');
        \assert(\is_string($format));

        return \date($format, $timestamp);
    }
}
