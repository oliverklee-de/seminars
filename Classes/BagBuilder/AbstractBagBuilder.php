<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BagBuilder;

use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\Domain\Repository\PageRepository as SeminarsPageRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This builder class creates customized bag objects.
 *
 * @template M of AbstractBag
 *
 * @internal
 */
abstract class AbstractBagBuilder
{
    /**
     * @var class-string<M> class name of the bag class that will be built
     */
    protected string $bagClassName;

    /**
     * @var non-empty-string
     */
    protected string $tableName;

    /**
     * @var array<string, non-empty-string> associative array with the WHERE clause parts
     *      (will be concatenated with "` AND `" later)
     */
    protected array $whereClauseParts = [];

    /**
     * @var string the sorting field
     */
    protected string $orderBy = 'uid';

    /**
     * @var string the field by which the DB query result should be grouped
     */
    protected string $groupBy = '';

    /**
     * @var string the number of records to retrieve; leave empty to set no limit
     */
    protected string $limit = '';

    /**
     * @var array<string, non-empty-string> additional table names for the query
     */
    protected array $additionalTableNames = [];

    protected bool $ignoreTimingOfRecords = false;

    protected bool $showHiddenRecords = false;

    protected PageRepository $pageRepository;

    /**
     * The constructor. Checks that $this->tableName is not empty.
     */
    public function __construct()
    {
        $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
    }

    /**
     * Creates and returns the customized bag.
     *
     * @return M customized, newly-created bag
     */
    public function build(): AbstractBag
    {
        /** @var M $bag */
        $bag = GeneralUtility::makeInstance(
            $this->bagClassName,
            $this->getWhereClause(),
            \implode(',', $this->additionalTableNames),
            $this->groupBy,
            $this->orderBy,
            $this->limit,
            ($this->showHiddenRecords ? 1 : -1),
            $this->ignoreTimingOfRecords,
        );
        return $bag;
    }

    /**
     * Configures the bag to work like a BE list: It will use the default
     * sorting in the BE, and hidden records will be shown.
     */
    public function setBackEndMode(): void
    {
        $this->showHiddenRecords();
        $this->ignoreTimingOfRecords = true;
    }

    /**
     * Sets the PIDs of the system folders that contain the records.
     *
     * @param string $sourcePagePids
     *        comma-separated list of PIDs of the system folders with the records;
     *        need not be safeguarded against SQL injection
     * @param int<0, max> $recursionDepth
     *        recursion depth, must be >= 0
     */
    public function setSourcePages(string $sourcePagePids, int $recursionDepth = 0): void
    {
        if (!\preg_match('/^([\\d+,] *)*\\d+$/', $sourcePagePids)) {
            unset($this->whereClauseParts['pages']);
            return;
        }

        // Transform the incoming comma separated uids into an array
        // Todo: Remove this after we pass an array from outside
        $uids = GeneralUtility::intExplode(',', $sourcePagePids);

        $recursivePidList = GeneralUtility::makeInstance(SeminarsPageRepository::class)
            ->findWithinParentPages($uids, $recursionDepth);

        $this->whereClauseParts['pages'] = $this->tableName . '.pid IN (' . \implode(',', $recursivePidList) . ')';
    }

    /**
     * Checks whether some source pages have already been set.
     */
    public function hasSourcePages(): bool
    {
        return isset($this->whereClauseParts['pages']);
    }

    /**
     * Returns the WHERE clause for the bag to create.
     *
     * The WHERE clause will be complete except for the enableFields additions.
     *
     * If the bag does not have any limitations imposed upon, the return value will be a tautology.
     *
     * @return non-empty-string complete WHERE clause for the bag to create, will not be empty
     */
    public function getWhereClause(): string
    {
        if ($this->whereClauseParts === []) {
            return '1=1';
        }

        return \implode(' AND ', $this->whereClauseParts);
    }

    /**
     * Returns a WHERE clause part for the bag to create.
     *
     * If the bag does not have such limitation imposed upon, the return value will be empty.
     *
     * @param non-empty-string $key the limitation key to return, must not be empty
     *
     * @return string WHERE clause part for the bag to create, may be empty
     */
    public function getWhereClausePart(string $key): string
    {
        return $this->whereClauseParts[$key] ?? '';
    }

    /**
     * Sets a WHERE clause part (limitation) for the bag to create.
     *
     * @param non-empty-string $key the limitation key to return, must not be empty
     * @param string $value the WHERE clause part of a limitation, empty value removes the limitation
     */
    public function setWhereClausePart(string $key, string $value): void
    {
        if ($value === '') {
            unset($this->whereClauseParts[$key]);
            return;
        }

        $this->whereClauseParts[$key] = $value;
    }

    /**
     * Adds the table name given in the parameter $additionalTableName to
     * $this->additionalTableNames.
     *
     * @param non-empty-string $additionalTableName the table name to add to the additional table names array
     */
    public function addAdditionalTableName(string $additionalTableName): void
    {
        $this->additionalTableNames[$additionalTableName] = $additionalTableName;
    }

    /**
     * Removes the table name given in the parameter $additionalTableName from
     * $this->additionalTableNames.
     *
     * @param non-empty-string $additionalTableName the table name to remove from the additional table names array
     */
    public function removeAdditionalTableName(string $additionalTableName): void
    {
        if (!isset($this->additionalTableNames[$additionalTableName])) {
            throw new \InvalidArgumentException(
                'The given additional table name does not exist in the list of additional table names.',
                1333292582,
            );
        }

        unset($this->additionalTableNames[$additionalTableName]);
    }

    public function setOrderBy(string $orderBy): void
    {
        $this->orderBy = $orderBy;
    }

    /**
     * Sets the LIMIT statement of the bag to build.
     *
     * Examples for the parameter:
     * - "0, 10" to limit the bag to 10 records, starting from the first record
     * - "10, 10" to limit the bag to 10 records, starting from the 11th record
     * - "10" to limit the bag to the first 10 records
     *
     * @param string $limit the LIMIT statement to set, may be empty
     */
    public function setLimit(string $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * Configures the bag to also contain hidden records.
     */
    public function showHiddenRecords(): void
    {
        $this->showHiddenRecords = true;
    }

    protected function getQueryBuilderForTable(string $table): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table);
    }

    protected function getConnectionForTable(string $table): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable($table);
    }

    private function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
