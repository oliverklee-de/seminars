<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Mapper;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\AbstractDataMapper;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Model\Event;
use TYPO3\CMS\Core\Database\Connection;

/**
 * This class represents a mapper for events.
 *
 * @extends AbstractDataMapper<Event>
 */
class EventMapper extends AbstractDataMapper
{
    protected $tableName = 'tx_seminars_seminars';

    protected $modelClassName = Event::class;

    protected $relations = [
        'topic' => EventMapper::class,
        'categories' => CategoryMapper::class,
        'event_type' => EventTypeMapper::class,
        'organizers' => OrganizerMapper::class,
        'owner_feuser' => FrontEndUserMapper::class,
        // @deprecated #1324 will be removed in seminars 6.0
        'registrations' => RegistrationMapper::class,
    ];

    /**
     * Finds events that have the status "planned" and that have the automatic status change enabled.
     *
     * @return Collection<Event>
     */
    public function findForAutomaticStatusChange(): Collection
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->getTableName());
        $queryResult = $queryBuilder
            ->select('*')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'cancelled',
                        $queryBuilder->createNamedParameter(EventInterface::STATUS_PLANNED, Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->eq(
                        'automatic_confirmation_cancelation',
                        $queryBuilder->createNamedParameter(1, Connection::PARAM_INT),
                    ),
                ),
            )
            ->orderBy('uid')
            ->executeQuery();
        $rows = $queryResult->fetchAllAssociative();

        return $this->getListOfModels($rows);
    }

    /**
     * Finds events that have registrations that have been created after when the last registration digest email
     * has been sent. This will also find events that have registrations, but never got a registration email sent
     * so far.
     *
     * This method will only find complete events and dates, but no topics.
     *
     * @return Collection<Event>
     */
    public function findForRegistrationDigestEmail(): Collection
    {
        $whereClause = 'object_type <> ' . EventInterface::TYPE_EVENT_TOPIC .
            ' AND hidden = 0 AND deleted = 0 ' .
            ' AND EXISTS (' .
            'SELECT * FROM tx_seminars_attendances ' .
            'WHERE tx_seminars_attendances.deleted = 0 ' .
            ' AND tx_seminars_attendances.seminar = tx_seminars_seminars.uid' .
            ' AND tx_seminars_attendances.crdate > tx_seminars_seminars.date_of_last_registration_digest' .
            ')';

        $sql = 'SELECT * FROM ' . $this->getTableName() . ' WHERE ' . $whereClause . ' ORDER BY begin_date ASC';

        $connection = $this->getConnectionForTable($this->getTableName());
        $statement = $connection->prepare($sql);
        $rows = $statement->executeQuery()->fetchAllAssociative();

        return $this->getListOfModels($rows);
    }
}
