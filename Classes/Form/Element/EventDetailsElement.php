<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Form\Element;

use TYPO3\CMS\Backend\Form\Element\GroupElement;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Renders the event details for a seminar.
 *
 * This element is used to display the details of a seminar event, including
 * the date, title, and UID of the event.
 *
 * Usage in TCA:
 *
 * <code>
 *   'fieldConf' => [
 *     'config' => [
 *       'type' => 'group',
 *       'renderType' => 'eventDetails',
 *       'allowed' => 'tx_seminars_seminars',
 *       'default' => 0,
 *       'size' => 1,
 *       'minitems' => 1,
 *       'maxitems' => 1,
 *     ],
 *   ],
 * </code>
 *
 * @internal
 */
class EventDetailsElement extends GroupElement
{
    private const TABLE_NAME = 'tx_seminars_seminars';
    private const ALL_EVENTS_OPENING_WRAPPER = '<div class="tx-seminars-event-details">';
    private const ALL_EVENTS_CLOSING_WRAPPER = '</div>';
    private const SINGLE_EVENT_OPENING_WRAPPER = '<p class="tx-seminars-event-details-event">';
    private const SINGLE_EVENT_CLOSING_WRAPPER = '</p>';

    public function render(): array
    {
        $result = parent::render();
        $fieldName = $this->data['fieldName'] ?? '';
        $allEventRawData = $this->data['databaseRow'][$fieldName] ?? null;
        if (!\is_array($allEventRawData)) {
            return $result;
        }

        $allRenderedEventData = [];
        foreach ($allEventRawData as $singleEventRawDataAndConfiguration) {
            if (!\is_array($singleEventRawDataAndConfiguration)) {
                continue;
            }
            $this->checkTableName($singleEventRawDataAndConfiguration);
            $singleEventRawData = $singleEventRawDataAndConfiguration['row'] ?? null;
            if (!\is_array($singleEventRawData)) {
                continue;
            }

            $allRenderedEventData[] = $this->renderSingleEvent($singleEventRawData);
        }

        if ($allRenderedEventData !== []) {
            $originalHtml = $result['html'] ?? '';
            $additionalHtml = $this->renderAllEventDataAsHtml($allRenderedEventData);
            $result['html'] = $additionalHtml . $originalHtml;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $singleEventRawDataAndConfiguration
     */
    private function checkTableName(array $singleEventRawDataAndConfiguration): void
    {
        $tableName = $singleEventRawDataAndConfiguration['table'] ?? '';
        \assert(\is_string($tableName));
        if ($tableName !== self::TABLE_NAME) {
            $message = 'EventDetailsElement can only be used for the "' . self::TABLE_NAME . '" table, '
                . 'not for the "' . $tableName . '" table.';
            throw new \RuntimeException($message, 1752769757);
        }
    }

    /**
     * @param array<array-key, mixed> $singleEventRawData
     */
    private function renderSingleEvent(array $singleEventRawData): string
    {
        $eventDataParts = [];
        $timestamp = $singleEventRawData['begin_date'] ?? 0;
        \assert(\is_int($timestamp));
        if ($timestamp > 0) {
            $eventDataParts[] = $this->formatTimestamp($timestamp);
        }
        $eventDataParts[] = \htmlspecialchars($singleEventRawData['title'] ?? '', ENT_QUOTES | ENT_HTML5);
        $eventDataParts[] = '[' . ($singleEventRawData['uid'] ?? 0) . ']';

        return '<p>' . \implode(' ', $eventDataParts) . '</p>';
    }

    private function formatTimestamp(int $timestamp): string
    {
        $dateFormat = $this->getDateFormat();

        return \htmlspecialchars(\date($dateFormat, $timestamp), ENT_QUOTES | ENT_HTML5);
    }

    private function getDateFormat(): string
    {
        return LocalizationUtility::translate('dateFormat', 'seminars') ?? '';
    }

    /**
     * @param non-empty-list<string> $allRenderedEventData
     */
    private function renderAllEventDataAsHtml(array $allRenderedEventData): string
    {
        $glue = self::SINGLE_EVENT_CLOSING_WRAPPER . "\n" . self::SINGLE_EVENT_OPENING_WRAPPER;

        return self::ALL_EVENTS_OPENING_WRAPPER . "\n" . self::SINGLE_EVENT_OPENING_WRAPPER
            . \implode($glue, $allRenderedEventData)
            . self::SINGLE_EVENT_CLOSING_WRAPPER . "\n" . self::ALL_EVENTS_CLOSING_WRAPPER;
    }
}
