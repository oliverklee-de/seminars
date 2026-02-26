<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Repository\Registration;

use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Domain\Repository\AbstractRawDataCapableRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractRawDataCapableRepository<Registration>
 */
class RegistrationRepository extends AbstractRawDataCapableRepository
{
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @return non-empty-string
     */
    protected function getTableName(): string
    {
        return 'tx_seminars_attendances';
    }

    /**
     * Persists all added or updated models.
     */
    public function persistAll(): void
    {
        $this->persistenceManager->persistAll();
    }

    public function existsRegistrationForEventAndUser(EventInterface $event, int $userUid): bool
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('seminar', $event),
                $query->equals('user', $userUid),
            ),
        );

        return $query->count() > 0;
    }

    /**
     * @param int<0, max> $pageUid
     *
     * @return int<0, max>
     */
    public function countRegularRegistrationsByPageUid(int $pageUid): int
    {
        $connection = $this->connectionPool->getConnectionForTable($this->getTableName());

        $count = $connection->count(
            '*',
            $this->getTableName(),
            ['pid' => $pageUid, 'registration_queue' => Registration::STATUS_REGULAR],
        );
        \assert($count >= 0);

        return $count;
    }

    /**
     * Sums up the regular (i.e., non-waiting-list) seats of all registrations for the given event UID.
     *
     * Registrations with 0 seats will be ignored.
     *
     * @param positive-int $eventUid
     *
     * @return int<0, max>
     */
    public function countRegularSeatsByEvent(int $eventUid): int
    {
        return $this->countSeatsByEvent($eventUid, Registration::STATUS_REGULAR);
    }

    /**
     * Sums up the waiting-list seats of all registrations for the given event UID.
     *
     * Registrations with 0 seats will be ignored.
     *
     * @param positive-int $eventUid
     *
     * @return int<0, max>
     */
    public function countWaitingListSeatsByEvent(int $eventUid): int
    {
        return $this->countSeatsByEvent($eventUid, Registration::STATUS_WAITING_LIST);
    }

    /**
     * Sums up the seats of all registrations for the given event UID.
     *
     * Registrations with 0 seats will be ignored.
     *
     * @param positive-int $eventUid
     * @param Registration::STATUS_* $status
     *
     * @return int<0, max>
     */
    private function countSeatsByEvent(int $eventUid, int $status): int
    {
        $tableName = $this->getTableName();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $query = $queryBuilder
            ->addSelectLiteral($queryBuilder->expr()->sum('seats'))
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'seminar',
                    $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'registration_queue',
                    $queryBuilder->createNamedParameter($status, Connection::PARAM_INT),
                ),
            );
        $queryResult = $query->executeQuery();

        return (int)$queryResult->fetchOne();
    }

    /**
     * @param positive-int $eventUid
     *
     * @return array<Registration>
     */
    public function findRegularRegistrationsByEvent(int $eventUid): array
    {
        return $this->findByEventAndStatus($eventUid, Registration::STATUS_REGULAR);
    }

    /**
     * @param positive-int $eventUid
     *
     * @return array<Registration>
     */
    public function findWaitingListRegistrationsByEvent(int $eventUid): array
    {
        return $this->findByEventAndStatus($eventUid, Registration::STATUS_WAITING_LIST);
    }

    /**
     * @param positive-int $eventUid
     * @param Registration::STATUS_* $status
     *
     * @return array<Registration>
     */
    private function findByEventAndStatus(int $eventUid, int $status): array
    {
        $query = $this->createQuery();
        $query->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);

        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $query->setQuerySettings($querySettings);

        $query->matching(
            $query->logicalAnd(
                $query->equals('event', $eventUid),
                $query->equals('status', $status),
            ),
        );

        return $query->execute()->toArray();
    }

    /**
     * Deletes the event with the given UID.
     *
     * Note: As this method uses the `DataHandler`, it can only be used within a backend context.
     *
     * The `DataHandler` will also take care of checking the permissions of the logged-in BE user.
     *
     * @param positive-int $uid
     */
    public function deleteViaDataHandler(int $uid): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $tableName = $this->getTableName();
        $dataHandler->start([], [$tableName => [$uid => ['delete' => 1]]]);
        $dataHandler->process_cmdmap();
    }

    /**
     * Finds non-hidden (i.e., non-canceled) registrations for the given user UID regardless of the storage page.
     *
     * @param int<1, max> $userUid
     *
     * @return array<Registration>
     */
    public function findActiveRegistrationsByUser(int $userUid): array
    {
        $query = $this->createQuery();

        return $query
            ->matching($query->equals('user', $userUid))
            ->setOrderings(['uid' => QueryInterface::ORDER_DESCENDING])
            ->execute()->toArray();
    }
}
