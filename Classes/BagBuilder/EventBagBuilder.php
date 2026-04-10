<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BagBuilder;

use OliverKlee\Oelib\Interfaces\Time;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This builder class creates customized event bags.
 *
 * @extends AbstractBagBuilder<EventBag>
 */
class EventBagBuilder extends AbstractBagBuilder
{
    /**
     * @var class-string<EventBag> class name of the bag class that will be built
     */
    protected string $bagClassName = EventBag::class;

    /**
     * @var non-empty-string the table name of the bag to build
     */
    protected string $tableName = 'tx_seminars_seminars';

    /**
     * @var list<non-empty-string> list of the valid keys for time-frames
     */
    public const VALID_TIMES_FRAMES = [
        'past',
        'pastAndCurrent',
        'current',
        'currentAndUpcoming',
        'upcoming',
        'upcomingWithBeginDate',
        'deadlineNotOver',
        'all',
        'today',
    ];

    /**
     * @var array<string, list<non-empty-string>> a list of field names of m:n associations in which we can search,
     *      grouped by record type
     */
    private static array $searchFieldList = [
        'speakers' => ['title'],
        'places' => ['title', 'city'],
        'categories' => ['title'],
        'target_groups' => ['title'],
    ];

    /**
     * @var non-empty-string the character list to trim the search words for
     */
    private const TRIM_CHARACTER_LIST = " ,\t\n\r\0\x0b";

    /**
     * @var positive-int the minimum search word length
     */
    private const MINIMUM_SEARCH_WORD_LENGTH = 4;

    /**
     * Limits the bag to events from any of the categories with the UIDs
     * provided as the parameter $categoryUids.
     *
     * @param string $concatenatedCategoryUids comma-separated list of UIDs of the categories which the bag
     *        should be limited to, set to an empty string for no limitation
     */
    public function limitToCategories(string $concatenatedCategoryUids): void
    {
        if ($concatenatedCategoryUids === '') {
            unset($this->whereClauseParts['categories']);
            return;
        }

        $categoryUids = GeneralUtility::intExplode(',', $concatenatedCategoryUids, true);
        $queryBuilder = $this->getQueryBuilderForTable('tx_seminars_seminars_categories_mm');
        $categoryUidsParameter = $queryBuilder->createNamedParameter($categoryUids, Connection::PARAM_INT_ARRAY);
        $queryResult = $queryBuilder
            ->select('uid_local')
            ->from('tx_seminars_seminars_categories_mm')
            ->where($queryBuilder->expr()->in('uid_foreign', $categoryUidsParameter))
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();

        $directMatchUids = \array_column($result, 'uid_local');
        if (empty($directMatchUids)) {
            $this->whereClauseParts['categories'] = '(1 = 0)';
            return;
        }

        $uidMatcher = ' IN(' . \implode(',', $this->cleanIntegers($directMatchUids)) . ')';
        $this->whereClauseParts['categories'] =
            '(' .
            '(object_type <> ' . EventInterface::TYPE_EVENT_DATE . ' AND ' .
            'tx_seminars_seminars.uid' . $uidMatcher . ')' .
            ' OR ' .
            '(object_type = ' . EventInterface::TYPE_EVENT_DATE . ' AND ' .
            'tx_seminars_seminars.topic' . $uidMatcher . ')' .
            ')';
    }

    /**
     * Limits the bag to events at any of the places with the UIDs provided as
     * the parameter $placeUids.
     *
     * @param array<array-key, string|int> $uids place UIDs, set to an empty array for no limitation,
     *        need not be SQL-safe
     */
    public function limitToPlaces(array $uids = []): void
    {
        if (empty($uids)) {
            unset($this->whereClauseParts['places']);
            return;
        }

        $this->whereClauseParts['places'] = 'EXISTS (SELECT * FROM ' .
            'tx_seminars_seminars_place_mm WHERE ' .
            'tx_seminars_seminars_place_mm.uid_local = ' .
            'tx_seminars_seminars.uid AND ' .
            'tx_seminars_seminars_place_mm.uid_foreign IN(' . \implode(',', $this->cleanIntegers($uids)) . ')' .
            ')';
    }

    /**
     * Casts all values of the given array to integers.
     *
     * @param array<int, string|int> $array
     *
     * @return array<int, int>
     */
    private function cleanIntegers(array $array): array
    {
        return \array_map('\\intval', $array);
    }

    /**
     * Sets the bag to ignore canceled events.
     */
    public function ignoreCanceledEvents(): void
    {
        $this->whereClauseParts['hideCanceledEvents'] = 'cancelled <> ' . EventInterface::STATUS_CANCELED;
    }

    /**
     * Allows the bag to include canceled events again.
     */
    public function allowCanceledEvents(): void
    {
        unset($this->whereClauseParts['hideCanceledEvents']);
    }

    /**
     * Sets the time-frame for the events that will be found by this bag.
     *
     * @param value-of<self::VALID_TIMES_FRAMES> $timeFrameKey
     */
    public function setTimeFrame(string $timeFrameKey): void
    {
        if (!\in_array($timeFrameKey, self::VALID_TIMES_FRAMES, true)) {
            throw new \InvalidArgumentException('The time-frame key "' . $timeFrameKey . '" is not valid.', 1333292705);
        }

        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        // Works out from which time-frame we'll find event records.
        // We also need to deal with the case that an event has no end date set
        // (ie. it is open-ended).
        switch ($timeFrameKey) {
            case 'past':
                // As past events, shows the following:
                // 1. Generally, only events that have a begin date set, AND:
                // 2. If the event has an end date, does it lie in the past?, OR
                // 3. If the event has *no* end date, does the *begin* date lie
                //    in the past?
                $where = 'tx_seminars_seminars.begin_date <> 0 ' .
                    'AND ( (tx_seminars_seminars.end_date <> 0 ' .
                    'AND tx_seminars_seminars.end_date <= ' . $now .
                    ') OR (' .
                    'tx_seminars_seminars.end_date = 0 ' .
                    'AND tx_seminars_seminars.begin_date <= ' . $now .
                    ')' .
                    ')';
                break;
            case 'pastAndCurrent':
                // As past and current events, shows the following:
                // 1. Generally, only events that have a begin date set, AND
                // 2. the begin date lies in the past.
                // (So events without a begin date won't be listed here.)
                $where = 'tx_seminars_seminars.begin_date <> 0 ' .
                    'AND tx_seminars_seminars.begin_date <= ' . $now;
                break;
            case 'current':
                // As current events, shows the following:
                // 1. Events that have both a begin and end date, AND
                // 2. The begin date lies in the past, AND
                // 3. The end date lies in the future.
                $where = 'tx_seminars_seminars.begin_date <> 0 ' .
                    'AND tx_seminars_seminars.begin_date <= ' . $now . ' ' .
                    // This implies that end_date is != 0.
                    'AND tx_seminars_seminars.end_date > ' . $now;
                break;
            case 'currentAndUpcoming':
                // As current and upcoming events, shows the following:
                // 1. Events with an existing end date in the future, OR
                // 2. Events without an end date, but with an existing begin date
                //    in the future (open-ended events that have not started yet),
                //    OR
                // 3. Events that have no (begin) date set yet.
                $where = 'tx_seminars_seminars.end_date > ' . $now .
                    ' OR (' .
                    'tx_seminars_seminars.end_date = 0 ' .
                    'AND tx_seminars_seminars.begin_date > ' . $now .
                    ') OR ' .
                    'tx_seminars_seminars.begin_date = 0';
                break;
            case 'upcoming':
                // As upcoming events, shows the following:
                // 1. Events with an existing begin date in the future
                //    (events that have not started yet), OR
                // 3. Events that have no (begin) date set yet.
                $where = 'tx_seminars_seminars.begin_date > ' . $now .
                    ' OR tx_seminars_seminars.begin_date = 0';
                break;
            case 'upcomingWithBeginDate':
                $where = 'tx_seminars_seminars.begin_date > ' . $now;
                break;
            case 'deadlineNotOver':
                // As events for which the registration deadline is not over yet,
                // shows the following:
                // 1. Events that have a deadline set that lies in the future, OR
                // 2. Events that have *no* deadline set, but
                //    with an existing begin date in the future
                //    (events that have not started yet), OR
                // 3. Events that have no (begin) date set yet.
                $where = '(' .
                    'tx_seminars_seminars.deadline_registration <> 0 ' .
                    'AND tx_seminars_seminars.deadline_registration > ' . $now .
                    ') OR (' .
                    'tx_seminars_seminars.deadline_registration = 0 ' .
                    'AND (' .
                    'tx_seminars_seminars.begin_date > ' . $now .
                    ' OR tx_seminars_seminars.begin_date = 0' .
                    ')' .
                    ')';
                break;
            case 'today':
                $day = (int)\date('j', $now);
                $month = (int)\date('n', $now);
                $year = (int)\date('Y', $now);

                $todayBegin = \mktime(0, 0, 0, $month, $day, $year);
                $todayEnd = \mktime(23, 59, 59, $month, $day, $year);

                $where = '(' .
                    'tx_seminars_seminars.begin_date BETWEEN ' .
                    $todayBegin . ' AND ' . $todayEnd .
                    ') OR ( ' .
                    'tx_seminars_seminars.end_date BETWEEN ' .
                    $todayBegin . ' AND ' . $todayEnd .
                    ') OR ( ' .
                    'tx_seminars_seminars.begin_date < ' . $todayBegin .
                    ' AND tx_seminars_seminars.end_date > ' . $todayEnd .
                    ')';
                break;
            case 'all':
                // The fall-through is intentional.
            default:
                // To show all events, we don't need any additional parameters.
                $where = '';
        }

        if ($where !== '') {
            $this->whereClauseParts['timeFrame'] = '(' . $where . ')';
        }
    }

    /**
     * Limits the bag to events from any of the event types with the UIDs
     * provided as the parameter $eventTypeUids.
     *
     * @param array<array-key, string|int> $uids event type UIDs, set to empty array for no limitation,
     *        need not be SQL-safe
     */
    public function limitToEventTypes(array $uids = []): void
    {
        if (empty($uids)) {
            unset($this->whereClauseParts['eventTypes']);
            return;
        }

        $safeEventTypeUids = \implode(',', $this->cleanIntegers($uids));
        $this->whereClauseParts['eventTypes'] = '(
            (
                object_type IN(' .
            EventInterface::TYPE_SINGLE_EVENT . ',' . EventInterface::TYPE_EVENT_TOPIC .
            ') AND event_type IN(' . $safeEventTypeUids . ')
            ) OR (
                object_type = ' . EventInterface::TYPE_EVENT_DATE . ' AND EXISTS (
                    SELECT * FROM tx_seminars_seminars AS topic
                    WHERE topic.uid = tx_seminars_seminars.topic AND topic.event_type IN(' . $safeEventTypeUids . ')
                )
            )' .
            ')';
    }

    /**
     * Limits the bag to events in the cities given in the first parameter
     * $cities.
     *
     * @param array<array-key, string> $cities city names, set to an empty array for no limitation, need not be SQL-safe
     */
    public function limitToCities(array $cities = []): void
    {
        if (empty($cities)) {
            unset($this->whereClauseParts['cities']);
            return;
        }

        $this->whereClauseParts['cities'] = 'tx_seminars_seminars.uid IN(' .
            'SELECT tx_seminars_seminars.uid' .
            ' FROM tx_seminars_seminars' .
            ' LEFT JOIN tx_seminars_seminars_place_mm ON ' .
            'tx_seminars_seminars.uid=' .
            'tx_seminars_seminars_place_mm.uid_local' .
            ' LEFT JOIN tx_seminars_sites ON ' .
            'tx_seminars_seminars_place_mm.uid_foreign = ' .
            'tx_seminars_sites.uid' .
            ' WHERE tx_seminars_sites.city IN(' . $this->quoteAndImplodeForDatabaseQuery($cities) . ')' .
            ')';
    }

    /**
     * @param array<array-key, string> $array
     */
    private function quoteAndImplodeForDatabaseQuery(array $array): string
    {
        $connection = $this->getConnectionForTable('tx_seminars_sites');
        $quoted = [];

        foreach ($array as $value) {
            $quoted[] = $connection->quote($value);
        }

        return \implode(',', $quoted);
    }

    /**
     * Limits the bag to topic event records.
     */
    public function limitToTopicRecords(): void
    {
        $this->whereClauseParts['topic'] = 'tx_seminars_seminars' .
            '.object_type = ' . EventInterface::TYPE_EVENT_TOPIC;
    }

    /**
     * Removes the limitation for topic event records.
     */
    public function removeLimitToTopicRecords(): void
    {
        unset($this->whereClauseParts['topic']);
    }

    /**
     * Limits the bag to date and single records.
     */
    public function limitToDateAndSingleRecords(): void
    {
        $this->whereClauseParts['date_single'] = '(tx_seminars_seminars' .
            '.object_type = ' . EventInterface::TYPE_EVENT_DATE . ' OR ' .
            'tx_seminars_seminars.object_type = ' .
            EventInterface::TYPE_SINGLE_EVENT . ')';
    }

    /**
     * Removes the limitation for date and single records.
     */
    public function removeLimitToDateAndSingleRecords(): void
    {
        unset($this->whereClauseParts['date_single']);
    }

    /**
     * Limits the bag to events with the FE user UID given in the parameter
     * $feUserUid as event manager.
     *
     * @param int<0, max> $feUserUid the FE user UID of the event manager to limit for,
     *        set to 0 to remove the limitation
     */
    public function limitToEventManager(int $feUserUid): void
    {
        // @phpstan-ignore-next-line We are explicitly checking for a contract violation here.
        if ($feUserUid < 0) {
            throw new \InvalidArgumentException('The parameter $feUserUid must be >= 0.', 1333292729);
        }

        if ($feUserUid === 0) {
            $this->removeAdditionalTableName('tx_seminars_seminars_feusers_mm');
            unset($this->whereClauseParts['vip']);
            return;
        }

        $this->addAdditionalTableName('tx_seminars_seminars_feusers_mm');
        $this->whereClauseParts['vip'] = 'tx_seminars_seminars.uid = ' .
            'tx_seminars_seminars_feusers_mm.uid_local AND ' .
            'tx_seminars_seminars_feusers_mm.uid_foreign = ' . $feUserUid;
    }

    /**
     * Limits the bag to events on the day after the end date of the event given
     * event in the first parameter $event.
     *
     * @param LegacyEvent $event the event object with the end date to limit for, must have an end date
     */
    public function limitToEventsNextDay(LegacyEvent $event): void
    {
        if (!$event->hasEndDate()) {
            throw new \InvalidArgumentException(
                'The event object given in the first parameter $event must have an end date set.',
                1333292744,
            );
        }

        $endDate = $event->getEndDateAsTimestamp();
        $midnightBeforeEndDate = $endDate - ($endDate % Time::SECONDS_PER_DAY);
        $secondMidnightAfterEndDate = $midnightBeforeEndDate + 2 * Time::SECONDS_PER_DAY;

        $this->whereClauseParts['next_day'] = 'begin_date>=' . $endDate .
            ' AND begin_date<' . $secondMidnightAfterEndDate;
    }

    /**
     * Limits the bag to date event records of the same topic as the event
     * given in the first parameter $event.
     *
     * @param LegacyEvent $event the date or topic object to find other dates of the same topic for
     */
    public function limitToOtherDatesForTopic(LegacyEvent $event): void
    {
        if (!$event->isEventDate() && !$event->isEventTopic()) {
            throw new \InvalidArgumentException(
                'The first parameter $event must be either a date or a topic record.',
                1333292764,
            );
        }

        $this->whereClauseParts['other_dates'] = '(' .
            'tx_seminars_seminars.topic = ' . $event->getTopicOrSelfUid() .
            ' AND object_type = ' . EventInterface::TYPE_EVENT_DATE .
            ' AND uid <> ' . $event->getUid() .
            ')';
    }

    /**
     * Removes the limitation for other dates of this topic.
     */
    public function removeLimitToOtherDatesForTopic(): void
    {
        unset($this->whereClauseParts['other_dates']);
    }

    /**
     * Limits the bag based on the input search words (using OR of full-text search).
     *
     * @param string $searchWords the search words, separated by spaces or commas, may be empty, need not be SQL-safe
     */
    public function limitToFullTextSearch(string $searchWords): void
    {
        $searchWords = \trim($searchWords, self::TRIM_CHARACTER_LIST);

        if ($searchWords === '') {
            unset($this->whereClauseParts['search']);
            return;
        }

        $keywords = \preg_split('/[ ,]/', $searchWords);

        $allWhereParts = [];

        foreach ($keywords as $keyword) {
            $safeKeyword = $this->prepareSearchWord($keyword);

            // Only search for words with a certain length.
            // Skips the current iteration of the loop for empty search words.
            // We use `strlen` instead of `mb_strlen` because having a search word
            // consisting of just an umlaut is okay, and this avoids problems
            // on installations without mb_string enabled.
            if (\strlen($safeKeyword) < self::MINIMUM_SEARCH_WORD_LENGTH) {
                continue;
            }

            $safeKeyword = '"' . $safeKeyword . '"';

            $wherePartsForCurrentSearchWord = \array_merge(
                $this->getSearchWherePartIndependentFromEventRecordType($safeKeyword),
                $this->getSearchWherePartForEventTopics($safeKeyword),
                $this->getSearchWherePartForSpeakers($safeKeyword),
                $this->getSearchWherePartForPlaces($safeKeyword),
                $this->getSearchWherePartForEventTypes($safeKeyword),
                $this->getSearchWherePartForCategories($safeKeyword),
                $this->getSearchWherePartForTargetGroups($safeKeyword),
            );
            $allWhereParts[] = '(' . \implode(' OR ', $wherePartsForCurrentSearchWord) . ')';
        }

        if (empty($allWhereParts)) {
            unset($this->whereClauseParts['search']);
        } else {
            $this->whereClauseParts['search'] = \implode(' AND ', $allWhereParts);
        }
    }

    /**
     * Limits the bag to future events for which the cancelation deadline
     * reminder has not been sent yet.
     */
    public function limitToCancelationDeadlineReminderNotSent(): void
    {
        $this->whereClauseParts['cancelation_reminder_not_sent']
            = 'tx_seminars_seminars.cancelation_deadline_reminder_sent = 0';
    }

    /**
     * Limits the bag to future events for which the reminder that an event is
     * about to take place has not been sent yet.
     */
    public function limitToEventTakesPlaceReminderNotSent(): void
    {
        $this->whereClauseParts['event_takes_place_reminder_not_sent']
            = 'tx_seminars_seminars.event_takes_place_reminder_sent = 0';
    }

    /**
     * Limits the bag to events in status $status.
     *
     * @param EventInterface::STATUS_* $status
     */
    public function limitToStatus(int $status): void
    {
        $this->whereClauseParts['event_status'] = 'tx_seminars_seminars.cancelled = ' . $status;
    }

    /**
     * Limits the bag to events which are currently $days days before their
     * begin date.
     *
     * @param int $days days before the begin date, must be > 0
     */
    public function limitToDaysBeforeBeginDate(int $days): void
    {
        $nowPlusDays = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp')
            + $days * Time::SECONDS_PER_DAY;

        $this->whereClauseParts['days_before_begin_date'] = 'tx_seminars_seminars.begin_date < ' . $nowPlusDays;
    }

    /**
     * Generates and returns the WHERE clause parts for the search in categories
     * based on the search word given in the first parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must be SQL-safe
     *
     * @return array{0: non-empty-string} the WHERE clause parts for the search in categories
     */
    private function getSearchWherePartForCategories(string $quotedSearchWord): array
    {
        return $this->getSearchWherePartInMmRelationForTopicOrSingleEventRecord(
            $quotedSearchWord,
            'categories',
            'tx_seminars_categories',
            'tx_seminars_seminars_categories_mm',
        );
    }

    /**
     * Generates and returns the WHERE clause parts for the search in target groups
     * based on the search word given in the first parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must be SQL-safe
     *
     * @return array{0: non-empty-string} the WHERE clause parts for the search in categories
     */
    private function getSearchWherePartForTargetGroups(string $quotedSearchWord): array
    {
        return $this->getSearchWherePartInMmRelationForTopicOrSingleEventRecord(
            $quotedSearchWord,
            'target_groups',
            'tx_seminars_target_groups',
            'tx_seminars_seminars_target_groups_mm',
        );
    }

    /**
     * Generates and returns the WHERE clause parts for the search in event
     * types based on the search word given in the first parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must be SQL-safe
     *
     * @return array{0: non-empty-string} the WHERE clause parts for the search in event types
     */
    private function getSearchWherePartForEventTypes(string $quotedSearchWord): array
    {
        return [
            'EXISTS (' .
            'SELECT * FROM tx_seminars_event_types, tx_seminars_seminars s1, tx_seminars_seminars s2' .
            ' WHERE (MATCH (tx_seminars_event_types.title) AGAINST (' . $quotedSearchWord . ' IN BOOLEAN MODE)' .
            ' AND tx_seminars_event_types.uid = s1.event_type' .
            ' AND ((s1.uid = s2.topic AND s2.object_type = ' . EventInterface::TYPE_EVENT_DATE . ') ' .
            'OR (s1.uid = s2.uid AND s1.object_type <> ' . EventInterface::TYPE_EVENT_DATE . '))' .
            ' AND s2.uid = tx_seminars_seminars.uid)' .
            ')',
        ];
    }

    /**
     * Generates and returns the WHERE clause parts for the search in places
     * based on the search word given in the first parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word
     *
     * @return array<int, non-empty-string> the WHERE clause parts for the search in places
     */
    private function getSearchWherePartForPlaces(string $quotedSearchWord): array
    {
        return $this->getSearchWherePartForMmRelation(
            $quotedSearchWord,
            'places',
            'tx_seminars_sites',
            'tx_seminars_seminars_place_mm',
        );
    }

    /**
     * Generates and returns the WHERE clause parts for the search in event
     * topics based on the search word given in the first parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word
     *
     * @return array<int, non-empty-string> the WHERE clause parts for the search in event topics
     */
    private function getSearchWherePartForEventTopics(string $quotedSearchWord): array
    {
        $queryBuilder = $this->getQueryBuilderForTable('tx_seminars_seminars');
        $queryResult = $queryBuilder
            ->select('uid')
            ->from('tx_seminars_seminars')
            ->where('MATCH (title, subtitle, description) AGAINST (' . $quotedSearchWord . ' IN BOOLEAN MODE)')
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();

        $matchingUids = \array_column($result, 'uid');
        if (empty($matchingUids)) {
            return [];
        }

        $inUids = ' IN (' . \implode(',', $matchingUids) . ')';
        return [
            '(object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ' AND tx_seminars_seminars.uid' . $inUids . ')',
            '(tx_seminars_seminars.object_type = ' . EventInterface::TYPE_EVENT_DATE . ' AND ' .
            'tx_seminars_seminars.topic' . $inUids . ')',
        ];
    }

    /**
     * Generates and returns the WHERE clause parts for the search independent
     * of the event record type based on the search word given in the first
     * parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must be SQL-safe
     *
     * @return non-empty-array<int, non-empty-string> the WHERE clause parts for the search
     *         independent of the event record type
     */
    private function getSearchWherePartIndependentFromEventRecordType(string $quotedSearchWord): array
    {
        return [
            'MATCH (tx_seminars_seminars.accreditation_number) AGAINST (' . $quotedSearchWord . ' IN BOOLEAN MODE)',
        ];
    }

    /**
     * Generates and returns the WHERE clause parts for the search in speakers
     * based on the search word given in the first parameter $quotedSearchWord.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must be SQL-safe
     *
     * @return array<int, non-empty-string> the WHERE clause parts for the search in speakers
     */
    private function getSearchWherePartForSpeakers(string $quotedSearchWord): array
    {
        return $this->getSearchWherePartForMmRelation(
            $quotedSearchWord,
            'speakers',
            'tx_seminars_speakers',
            'tx_seminars_seminars_speakers_mm',
        );
    }

    /**
     * Generates and returns the WHERE clause part for the search in an m:n
     * relation between a date or single event record.
     *
     * Searches for $searchWord in $field in $foreignTable using the m:n table $mmTable.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must not be empty, must be SQL-safe
     * @param non-empty-string $searchFieldKey the key of the search field list,
     *        must be a valid key of `$this->searchFieldList`
     * @param non-empty-string $foreignTable the foreign table to search in
     * @param non-empty-string $mmTable the m:n relation table
     *
     * @return array{0: non-empty-string} the WHERE clause parts for the search in categories
     */
    private function getSearchWherePartInMmRelationForTopicOrSingleEventRecord(
        string $quotedSearchWord,
        string $searchFieldKey,
        string $foreignTable,
        string $mmTable
    ): array {
        $this->checkParametersForMmSearchFunctions($quotedSearchWord, $searchFieldKey, $foreignTable, $mmTable);

        $matchQueryPart = 'MATCH (' .
            $foreignTable . '.' . \implode(',' . $foreignTable . '.', self::$searchFieldList[$searchFieldKey]) .
            ') AGAINST (' . $quotedSearchWord . ' IN BOOLEAN MODE)';
        return [
            'EXISTS ' .
            '(SELECT * FROM ' . 'tx_seminars_seminars s1, ' . $mmTable . ', ' . $foreignTable .
            ' WHERE ((tx_seminars_seminars.object_type = ' .
            EventInterface::TYPE_EVENT_DATE . ' AND s1.object_type <> ' . EventInterface::TYPE_EVENT_DATE .
            ' AND tx_seminars_seminars.topic = s1.uid)' .
            ' OR (tx_seminars_seminars.object_type = ' . EventInterface::TYPE_SINGLE_EVENT .
            ' AND tx_seminars_seminars.uid = s1.uid))' .
            ' AND ' . $mmTable . '.uid_local = s1.uid' .
            ' AND ' . $mmTable . '.uid_foreign = ' . $foreignTable . '.uid' .
            ' AND ' . $matchQueryPart . ')',
        ];
    }

    /**
     * Generates and returns the WHERE clause part for the search in an m:n
     * relation between a date or single event record.
     *
     * Searches for $searchWord in $field in $foreignTable using the m:n table
     * $mmTable.
     *
     * @param non-empty-string $quotedSearchWord the current search word, must be SQL-safe
     * @param non-empty-string $searchFieldKey the key of the search field list,
     *        must be a valid key of `$this->searchFieldList`
     * @param non-empty-string $foreignTable the name of the foreign table to search in
     * @param non-empty-string $mmTable the m:n relation table
     *
     * @return array<int, non-empty-string> the WHERE clause parts for the search in categories
     */
    private function getSearchWherePartForMmRelation(
        string $quotedSearchWord,
        string $searchFieldKey,
        string $foreignTable,
        string $mmTable
    ): array {
        $this->checkParametersForMmSearchFunctions($quotedSearchWord, $searchFieldKey, $foreignTable, $mmTable);

        $matchQueryPart = \sprintf(
            'MATCH (%s) AGAINST (%s IN BOOLEAN MODE)',
            \implode(',', self::$searchFieldList[$searchFieldKey]),
            $quotedSearchWord,
        );

        $queryBuilder = $this->getQueryBuilderForTable($foreignTable);
        $queryResult = $queryBuilder
            ->select('uid')
            ->from($foreignTable)
            ->where($matchQueryPart)
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();

        $foreignUids = \array_column($result, 'uid');
        if (empty($foreignUids)) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilderForTable($mmTable);
        $foreignUidsParameter = $queryBuilder->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY);
        $queryResult = $queryBuilder
            ->select('uid_local')
            ->from($mmTable)
            ->where($queryBuilder->expr()->in('uid_foreign', $foreignUidsParameter))
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();
        $localUids = array_column($result, 'uid_local');

        if (empty($localUids)) {
            return [];
        }

        return ['tx_seminars_seminars.uid IN (' . \implode(',', $localUids) . ')'];
    }

    /**
     * SQL-escapes and trims a potential search word.
     *
     * @param string $searchWord single search word (may be prefixed or suffixed with spaces), may be empty
     *
     * @return string the trimmed and SQL-escaped $searchWord
     */
    private function prepareSearchWord(string $searchWord): string
    {
        return $this->getConnectionForTable($this->tableName)->quote(\trim($searchWord, self::TRIM_CHARACTER_LIST));
    }

    /**
     * Checks the parameters for the m:n search functions and throws exceptions
     * if at least one of the parameters is empty.
     *
     * @param non-empty-string $searchWord the current search word, must already be SQL-safe
     * @param non-empty-string $searchFieldKey the key of the search field list,
     *        must be a valid key of `self::$searchFieldList`
     * @param non-empty-string $foreignTable the foreign table to search in
     * @param non-empty-string $mmTable the m:n relation table
     */
    private function checkParametersForMmSearchFunctions(
        string $searchWord,
        string $searchFieldKey,
        string $foreignTable,
        string $mmTable
    ): void {
        if (\trim($searchWord, self::TRIM_CHARACTER_LIST . '\'%') === '') {
            throw new \InvalidArgumentException('The first parameter $searchWord must no be empty.', 1333292804);
        }
        // @phpstan-ignore-next-line We are explicitly checking for a contract violation here.
        if ($searchFieldKey === '') {
            throw new \InvalidArgumentException('The second parameter $searchFieldKey must not be empty.', 1333292809);
        }
        if (!\array_key_exists($searchFieldKey, self::$searchFieldList)) {
            throw new \InvalidArgumentException(
                'The second parameter $searchFieldKey must be a valid key of self::$searchFieldList.',
                1333292815,
            );
        }
        // @phpstan-ignore-next-line We are explicitly checking for a contract violation here.
        if ($foreignTable === '') {
            throw new \InvalidArgumentException('The third parameter $foreignTable must not be empty.', 1333292820);
        }
        // @phpstan-ignore-next-line We are explicitly checking for a contract violation here.
        if ($mmTable === '') {
            throw new \InvalidArgumentException('The fourth parameter $mmTable must not be empty.', 1333292829);
        }
    }

    /**
     * Limits the search results to topics which are required for the
     * given topic.
     *
     * @param positive-int $eventUid the UID of the topic event for which the requirements should be found
     */
    public function limitToRequiredEventTopics(int $eventUid): void
    {
        $this->whereClauseParts['requiredEventTopics'] =
            'tx_seminars_seminars_requirements_mm.uid_local = ' . $eventUid .
            ' AND tx_seminars_seminars_requirements_mm.uid_foreign = ' .
            'tx_seminars_seminars.uid';
        $this->addAdditionalTableName('tx_seminars_seminars_requirements_mm');
        $this->setOrderBy('tx_seminars_seminars_requirements_mm.sorting');
    }

    /**
     * Limits the search result to topics which depend on the given topic.
     *
     * @param positive-int $eventUid the UID of the topic event which the searched events depend on
     */
    public function limitToDependingEventTopics(int $eventUid): void
    {
        $this->whereClauseParts['dependingEventTopics'] =
            'tx_seminars_seminars_requirements_mm.uid_foreign = ' . $eventUid .
            ' AND tx_seminars_seminars_requirements_mm.uid_local = ' .
            'tx_seminars_seminars.uid';
        $this->addAdditionalTableName('tx_seminars_seminars_requirements_mm');
        $this->setOrderBy(
            'tx_seminars_seminars_requirements_mm.sorting_foreign ASC',
        );
    }

    /**
     * Limits the bag to events which start later than `$earliestBeginDate` or which are still running at
     * `$earliestBeginDate`.
     *
     * A `$earliestBeginDate` of 0 will remove the filter.
     *
     * @param int $earliestBeginDate the earliest begin date as UNIX time-stamp, 0 will remove the limit
     */
    public function limitToEarliestBeginOrEndDate(int $earliestBeginDate): void
    {
        if ($earliestBeginDate === 0) {
            unset($this->whereClauseParts['earliestBeginDate']);

            return;
        }

        $this->whereClauseParts['earliestBeginDate'] = '('
            . 'tx_seminars_seminars.begin_date = 0 OR '
            . '(tx_seminars_seminars.begin_date >= ' . $earliestBeginDate
            . ' OR (tx_seminars_seminars.begin_date <= ' . $earliestBeginDate
            . ' AND tx_seminars_seminars.end_date > ' . $earliestBeginDate . '))'
            . ')';
    }

    /**
     * Limits the bag to events which have a begin_date lower than the given
     * time-stamp, but greater than zero.
     *
     * A `$latestBeginDate` of 0 will remove the filter.
     *
     * @param int $latestBeginDate the latest begin date as UNIX time-stamp, 0 will remove the limit
     */
    public function limitToLatestBeginOrEndDate(int $latestBeginDate): void
    {
        if ($latestBeginDate === 0) {
            unset($this->whereClauseParts['latestBeginDate']);
            return;
        }

        $this->whereClauseParts['latestBeginDate'] =
            '(tx_seminars_seminars.begin_date <> 0 AND ' .
            'tx_seminars_seminars.begin_date <= ' . $latestBeginDate . ' OR ' .
            'tx_seminars_seminars.end_date <> 0 AND ' .
            'tx_seminars_seminars.end_date <= ' . $latestBeginDate . ')';
    }

    /**
     * Limits the bag to events which are not fully-booked yet (or have a queue).
     */
    public function limitToEventsWithVacancies(): void
    {
        $seats = '(SELECT COALESCE(SUM(seats),0) FROM tx_seminars_attendances ' .
            'WHERE seminar = tx_seminars_seminars.uid' .
            $this->pageRepository->enableFields('tx_seminars_attendances') . ')';
        $hasVacancies = '(attendees_max > (' . $seats . ' + offline_attendees))';

        $this->whereClauseParts['eventsWithVacancies'] =
            '(needs_registration = 0 OR (needs_registration = 1 AND ' .
            '(attendees_max = 0 OR ' .
            '(attendees_max > 0 AND ' . $hasVacancies . ')' .
            '))' .
            ')';
    }

    /**
     * Limits the bag to events with the given organizers.
     *
     * @param string $concatenatedOrganizerUids comma-separated list of organizer UIDs to limit the bag to, may be empty
     */
    public function limitToOrganizers(string $concatenatedOrganizerUids): void
    {
        if ($concatenatedOrganizerUids === '') {
            return;
        }

        $table = 'tx_seminars_seminars_organizers_mm';
        $organizerUids = GeneralUtility::intExplode(',', $concatenatedOrganizerUids, true);
        $queryBuilder = $this->getQueryBuilderForTable($table);
        $organizersParameter = $queryBuilder->createNamedParameter($organizerUids, Connection::PARAM_INT_ARRAY);
        $queryResult = $queryBuilder
            ->select('uid_local')
            ->from($table)
            ->where($queryBuilder->expr()->in('uid_foreign', $organizersParameter))
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();

        $eventUids = \implode(',', \array_column($result, 'uid_local'));
        if ($eventUids === '') {
            $this->whereClauseParts['eventsWithOrganizers'] = '(0 = 1)';
            return;
        }

        $this->whereClauseParts['eventsWithOrganizers'] =
            '((object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ') ' .
            'AND (tx_seminars_seminars.uid IN (' . $eventUids .
            ')) OR (' .
            '(object_type = ' . EventInterface::TYPE_EVENT_DATE . ') AND (' .
            'tx_seminars_seminars.topic IN (' . $eventUids . ')))' .
            ')';
    }

    /**
     * Limits the bag to events which have target groups with age limits within
     * the provided age.
     *
     * @param int<0, max> $age the age to limit the bag to
     */
    public function limitToAge(int $age): void
    {
        if ($age === 0) {
            return;
        }

        $table = 'tx_seminars_target_groups';
        $queryBuilder = $this->getQueryBuilderForTable($table);
        $queryResultWithTargetGroups = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->lte(
                    'minimum_age',
                    $queryBuilder->createNamedParameter($age, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'maximum_age',
                        $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->gte(
                        'maximum_age',
                        $queryBuilder->createNamedParameter($age, Connection::PARAM_INT),
                    ),
                ),
            )
            ->executeQuery();
        $resultWithTargetGroups = $queryResultWithTargetGroups->fetchAllAssociative();
        $matchingTargetGroups = \array_column($resultWithTargetGroups, 'uid');

        $table = 'tx_seminars_seminars';
        $queryBuilder = $this->getQueryBuilderForTable($table);
        $queryResultWithoutTargetGroups = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'object_type',
                        $queryBuilder->createNamedParameter(EventInterface::TYPE_SINGLE_EVENT, Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->eq(
                        'object_type',
                        $queryBuilder->createNamedParameter(EventInterface::TYPE_EVENT_TOPIC, Connection::PARAM_INT),
                    ),
                ),
                $queryBuilder->expr()->eq(
                    'target_groups',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
            )
            ->executeQuery();
        $resultWithoutTargetGroups = $queryResultWithoutTargetGroups->fetchAllAssociative();
        $eventsWithoutTargetGroup = \array_column($resultWithoutTargetGroups, 'uid');

        if ($matchingTargetGroups !== []) {
            $table = 'tx_seminars_seminars_target_groups_mm';
            $queryBuilder = $this->getQueryBuilderForTable($table);
            $targetGroupsParameter = $queryBuilder
                ->createNamedParameter($matchingTargetGroups, Connection::PARAM_INT_ARRAY);
            $queryResult = $queryBuilder
                ->select('uid_local')
                ->from($table)
                ->where($queryBuilder->expr()->in('uid_foreign', $targetGroupsParameter))
                ->groupBy('uid_local')
                ->executeQuery();
            $result = $queryResult->fetchAllAssociative();

            $eventsWithMatchingTargetGroup = \array_column($result, 'uid_local');
            $matchingEventsUids = \array_merge($eventsWithMatchingTargetGroup, $eventsWithoutTargetGroup);
        } else {
            $matchingEventsUids = $eventsWithoutTargetGroup;
        }

        if (empty($matchingEventsUids)) {
            $this->whereClauseParts['ageLimit'] = '(0 = 1)';
        } else {
            $matchingEventsUidList = \implode(',', $matchingEventsUids);
            $this->whereClauseParts['ageLimit'] =
                '((object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ' AND ' .
                'tx_seminars_seminars.uid IN (' . $matchingEventsUidList . ')) ' .
                'OR ' .
                '(object_type = ' . EventInterface::TYPE_EVENT_DATE . ' AND ' .
                'topic IN (' . $matchingEventsUidList . '))' .
                ')';
        }
    }

    /**
     * Limits the bag to events which have a price lower or equal to the given
     * maximum price.
     *
     * @param int<0, max> $maximumPrice the maximum price an event is allowed to cost
     */
    public function limitToMaximumPrice(int $maximumPrice): void
    {
        if ($maximumPrice === 0) {
            return;
        }

        $notZeroAndInRange = '(%1$s > 0 AND %1$s <= %2$u)';
        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

        $whereClause = '(object_type = ' . EventInterface::TYPE_EVENT_TOPIC . ' OR ' .
            'object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ') AND (' .
            '(deadline_early_bird < ' . $now . ' AND ' .
            '(price_regular <= ' . $maximumPrice . ' OR ' .
            \sprintf($notZeroAndInRange, 'price_special', $maximumPrice) . ')) ' .
            'OR (deadline_early_bird > ' . $now . ' AND ((' .
            '(price_regular_early = 0 AND price_regular <= ' .
            $maximumPrice . ') ' .
            'OR (price_special_early = 0 AND price_special > 0 ' .
            'AND price_special <= ' . $maximumPrice .
            ')' .
            ') OR (' .
            \sprintf($notZeroAndInRange, 'price_regular_early', $maximumPrice) .
            ' OR ' .
            \sprintf($notZeroAndInRange, 'price_special_early', $maximumPrice) .
            '))) ' .
            ')';

        $table = 'tx_seminars_seminars';
        $queryBuilder = $this->getQueryBuilderForTable($table);
        $queryResult = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where($whereClause)
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();
        $foundUids = \implode(',', \array_column($result, 'uid'));

        if ($foundUids === '') {
            $this->whereClauseParts['maximumPrice'] = '(0 = 1)';
        } else {
            $this->whereClauseParts['maximumPrice'] =
                '((object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ' ' .
                'AND tx_seminars_seminars.uid IN (' . $foundUids .
                ')) OR ' .
                '(object_type = ' . EventInterface::TYPE_EVENT_DATE . ' AND ' .
                'topic IN (' . $foundUids . ')))';
        }
    }

    /**
     * Limits the bag to events which have a price higher or equal to the given
     * minimum price.
     *
     * @param int<0, max> $minimumPrice the minimum price an event is allowed to cost
     */
    public function limitToMinimumPrice(int $minimumPrice): void
    {
        if ($minimumPrice === 0) {
            return;
        }

        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $whereClause = '(object_type = ' . EventInterface::TYPE_EVENT_TOPIC . ' OR ' .
            'object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ') AND (' .
            '(deadline_early_bird < ' . $now . ' ' .
            'AND (price_regular >= ' . $minimumPrice . ' ' .
            'OR price_special >= ' . $minimumPrice . ')' .
            ') OR (deadline_early_bird > ' . $now . ' ' .
            'AND ((' .
            '(price_regular_early = 0 ' .
            'AND price_regular >= ' . $minimumPrice . ') ' .
            'OR (price_special_early = 0 ' .
            'AND price_special >= ' . $minimumPrice . ')) ' .
            'OR (price_regular_early >= ' . $minimumPrice . ' ' .
            'OR price_special_early >= ' . $minimumPrice . ') ' .
            ')) ' .
            ') ';

        $table = 'tx_seminars_seminars';
        $queryBuilder = $this->getQueryBuilderForTable($table);
        $queryResult = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where($whereClause)
            ->executeQuery();
        $result = $queryResult->fetchAllAssociative();

        $foundUids = \implode(',', \array_column($result, 'uid'));

        if ($foundUids === '') {
            $this->whereClauseParts['maximumPrice'] = '(0 = 1)';
        } else {
            $this->whereClauseParts['maximumPrice'] =
                '((object_type = ' . EventInterface::TYPE_SINGLE_EVENT . ' ' .
                'AND tx_seminars_seminars.uid IN (' . $foundUids .
                ')) OR ' .
                '(object_type = ' . EventInterface::TYPE_EVENT_DATE . ' AND ' .
                'topic IN (' . $foundUids . ')))';
        }
    }
}
