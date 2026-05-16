<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Seo;

use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Seo\Event\AfterSlugGeneratedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Builds the slug for event records.
 *
 * @phpstan-type DatabaseColumn string|int|float|bool|null
 * @phpstan-type DatabaseRow array<string, DatabaseColumn>
 */
class SlugGenerator implements SingletonInterface
{
    private const TABLE_NAME_EVENTS = 'tx_seminars_seminars';

    private EventDispatcherInterface $eventDispatcher;

    private ConnectionPool $connectionPool;

    public function __construct(EventDispatcherInterface $eventDispatcher, ConnectionPool $connectionPool)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->connectionPool = $connectionPool;
    }

    public function getPrefix(): string
    {
        return '';
    }

    /**
     * Generates a unique slug for the given record.
     *
     * @param array{
     *          record: array{
     *            uid?: numeric-string|int<0, max>,
     *            title?: string,
     *            object_type?: numeric-string|int<0, max>,
     *            topic?: numeric-string|int<0, max>
     *          }
     *        } $parameters
     */
    public function generateSlug(array $parameters): string
    {
        $record = $parameters['record'];
        $eventUid = (int)($record['uid'] ?? 0);
        $recordType = (int)($record['object_type'] ?? 0);
        $topicUid = (int)($record['topic'] ?? 0);
        if ($eventUid <= 0 || $recordType < 0 || $topicUid < 0) {
            return '';
        }

        $title = '';
        if ($recordType === EventInterface::TYPE_EVENT_DATE) {
            $result = $this
                ->getQueryBuilder()->select('title')->from(self::TABLE_NAME_EVENTS)
                ->where('uid = :uid')->setParameter('uid', $topicUid)
                ->executeQuery();
            $data = $result->fetchAssociative();
            if (\is_array($data)) {
                /** @var DatabaseRow $data */
                $title = (string)$data['title'];
            }
        } else {
            $title = $record['title'] ?? '';
        }

        $slugifiedTitle = (new SlugHelper(self::TABLE_NAME_EVENTS, 'slug', []))->sanitize($title);
        $slug = ($slugifiedTitle !== '') ? ($slugifiedTitle . '/' . $eventUid) : (string)$eventUid;
        $slugContext = new SlugContext($eventUid, $title, $slugifiedTitle);

        $event = new AfterSlugGeneratedEvent($slugContext, $slug);
        $this->eventDispatcher->dispatch($event);

        return $event->getSlug();
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME_EVENTS);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }
}
