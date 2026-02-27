<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\RawDataInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @template TEntityClass of DomainObjectInterface
 * @extends Repository<TEntityClass>
 */
abstract class AbstractRawDataCapableRepository extends Repository
{
    protected ConnectionPool $connectionPool;

    public function injectConnectionPool(ConnectionPool $connectionPool): void
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return non-empty-string
     */
    abstract protected function getTableName(): string;

    /**
     * Sets the raw data for the provided models.
     *
     * This is useful e.g., for creating icons in the backend.
     *
     * @param array<string|int, TEntityClass> $models
     *
     * @internal
     */
    public function enrichWithRawData(array $models): void
    {
        if ($models === []) {
            return;
        }

        /** @var array<positive-int, TEntityClass> $modelsByUid */
        $modelsByUid = [];
        foreach ($models as $model) {
            $modelsByUid[$model->getUid()] = $model;
        }

        $tableName = $this->getTableName();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter(\array_keys($modelsByUid), Connection::PARAM_INT_ARRAY),
                ),
            );
        $queryResult = $query->executeQuery();
        $rows = $queryResult->fetchAllAssociative();

        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $model = $modelsByUid[$uid] ?? null;
            if ($model instanceof RawDataInterface) {
                $model->setRawData($row);
            }
        }
    }
}
