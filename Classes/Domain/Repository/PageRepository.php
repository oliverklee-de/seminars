<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Repository for finding page records by UID.
 */
class PageRepository implements SingletonInterface
{
    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * Recursively finds all pages within the given page, and returns them as a sorted list (including the provided
     * parent pages).
     *
     * @param array<positive-int> $pageUids
     * @param int<0, max> $recursion
     *
     * @return list<positive-int>
     *
     * @throws \InvalidArgumentException
     */
    public function findWithinParentPages(array $pageUids, int $recursion = 0): array
    {
        // @phpstan-ignore-next-line We are explicitly checking for contract violations here.
        if ($recursion < 0) {
            throw new \InvalidArgumentException(
                '$recursion must be >= 0, but actually is: ' . $recursion,
                1_608_389_744,
            );
        }

        $result = $this->cleanUids($pageUids);
        if ($result === [] || $recursion === 0) {
            return $result;
        }

        $result = \array_merge(
            $result,
            $this->findWithinParentPages($this->findDirectSubpages($result), $recursion - 1),
        );
        \sort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * Filters and int-casts the given UIDs and returns only positive integers, discarding the rest.
     *
     * @param array<int|numeric-string> $uids
     *
     * @return list<positive-int> sorted, filtered UIDs
     */
    private function cleanUids(array $uids): array
    {
        $cleanUids = [];
        foreach ($uids as $uid) {
            $intUid = (int)$uid;
            if ($intUid > 0) {
                $cleanUids[] = $intUid;
            }
        }

        \sort($cleanUids, SORT_NUMERIC);

        return $cleanUids;
    }

    /**
     * @param list<positive-int> $pageUids
     *
     * @return list<positive-int>
     */
    private function findDirectSubpages(array $pageUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $query = $queryBuilder->select('uid')->from('pages');
        $query->andWhere(
            $query->expr()->in('pid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY)),
        );

        $subpageUids = [];
        foreach ($query->executeQuery()->fetchAllAssociative() as $row) {
            /** @var positive-int $uid */
            $uid = (int)$row['uid'];
            $subpageUids[] = $uid;
        }

        return $subpageUids;
    }
}
