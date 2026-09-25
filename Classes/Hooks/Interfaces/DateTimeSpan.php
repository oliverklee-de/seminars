<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Hooks\Interfaces;

use OliverKlee\Seminars\OldModel\AbstractTimeSpan;

/**
 * Use this interface for hooks concerning the date or the time span.
 */
interface DateTimeSpan extends Hook
{
    /**
     * Modifies the date span string.
     *
     * This allows modifying the assembly of start and end date to the date span.
     * E.g., for Hungarian: '01.-03.01.2019' -> '2019.01.01.-03.'.
     *
     * The date format for the date parts are configured in TypoScript (`dateFormatYMD` etc.).
     * The event dates are also retrievable:
     * `$beginDateTime = $dateTimeSpan->getBeginDateAsTimestamp();`
     * `$endDateTime = $dateTimeSpan->getEndDateAsTimestamp();`
     *
     * @param string $dateSpan the date span produced by `AbstractTimeSpan::getDate()`
     * @param AbstractTimeSpan $dateTimeSpan the date provider
     *
     * @return string the modified date span to use
     */
    public function modifyDateSpan(string $dateSpan, AbstractTimeSpan $dateTimeSpan): string;

    /**
     * Modifies the time span string.
     *
     * This allows modifying the assembly of start and end time to the time span.
     * E.g., for Hungarian: '9:00-10:30' -> '9:00tol 10:30ban'.
     *
     * The time format for the time parts is configured in TypoScript (`timeFormat`).
     * The event times are also retrievable:
     * `$beginDateTime = $dateTimeSpan->getBeginDateAsTimestamp();`
     * `$endDateTime = $dateTimeSpan->getEndDateAsTimestamp();`
     *
     * @param string $timeSpan the time span produced by `AbstractTimeSpan::getTime()`
     * @param AbstractTimeSpan $dateTimeSpan the date provider
     *
     * @return string the modified time span to use
     */
    public function modifyTimeSpan(string $timeSpan, AbstractTimeSpan $dateTimeSpan): string;
}
