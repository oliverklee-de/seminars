<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\OldModel;

use OliverKlee\Seminars\Configuration\Traits\SharedPluginConfiguration;
use OliverKlee\Seminars\Localization\TranslateTrait;
use OliverKlee\Seminars\ViewHelpers\RichTextViewHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class represents an object that is created from a DB record or can be written to a DB record.
 *
 * It will hold the corresponding data and can commit that data to the DB.
 *
 * @internal
 */
abstract class AbstractModel
{
    use SharedPluginConfiguration;
    use TranslateTrait;

    protected static string $tableName;

    /**
     * @var array<string, float|int|string|bool|null> the values from/for the DB
     */
    protected array $recordData = [];

    protected bool $isPersisted = false;

    /**
     * @param int<0, max> $uid the UID of the record to retrieve from the DB
     */
    public function __construct(int $uid = 0, bool $allowHidden = false)
    {
        if ($uid > 0) {
            $data = self::fetchDataByUid($uid, $allowHidden);
            if (\is_array($data)) {
                $this->setData($data);
            }
        }
    }

    /**
     * Creates a new instance that has the given data.
     *
     * @param array<string, float|int|string|bool|null> $data
     *
     * @return static
     */
    public static function fromData(array $data): AbstractModel
    {
        $model = GeneralUtility::makeInstance(static::class);
        $model->setData($data);

        return $model;
    }

    /**
     * Instantiates a model from the database. If there is no record with the given UID, this method will return null.
     *
     * @param int<0, max> $uid the UID of the record to retrieve from the DB
     *
     * @return static|null
     */
    public static function fromUid(int $uid, bool $allowHidden = false): ?AbstractModel
    {
        if ($uid <= 0) {
            return null;
        }

        $data = self::fetchDataByUid($uid, $allowHidden);

        return \is_array($data) ? self::fromData($data) : null;
    }

    /**
     * @return array<string, string|int|bool|null>|false
     */
    protected static function fetchDataByUid(int $uid, bool $allowHidden)
    {
        $query = self::getQueryBuilderForOwnTable();
        if ($allowHidden) {
            $restrictions = $query->getRestrictions();
            $restrictions->removeByType(HiddenRestriction::class);
            $restrictions->removeByType(StartTimeRestriction::class);
            $restrictions->removeByType(EndTimeRestriction::class);
        }
        $query->select('*')->from(static::$tableName);
        $query->andWhere($query->expr()->eq('uid', $query->createNamedParameter($uid)));

        /** @var array<string, string|int|bool|null>|false $data */
        $data = $query->executeQuery()->fetchAssociative();
        return $data;
    }

    /**
     * Sets the record data from an DB query result represented as an
     * associative array and stores it in $this->recordData.
     * The column names will be used as array keys.
     * The column names must *not* be prefixed with the table name.
     *
     * If at least one element is taken, this function sets $this->isInDb to true.
     *
     * Example:
     * `$dbResultRow['name'] => $this->recordData['name']`
     *
     * @param array<string, float|int|string|bool|null> $data associative array of a DB query result
     */
    protected function setData(array $data): void
    {
        $this->recordData = $data;
        if (!empty($data['uid'])) {
            $this->isPersisted = true;
        }
    }

    /**
     * Checks whether this object has been properly initialized and thus is basically usable.
     *
     * @return bool true if the object has been initialized, false otherwise
     */
    public function isOk(): bool
    {
        return $this->recordData !== [];
    }

    /**
     * Gets a trimmed string element of the record data array.
     * If the array has not been initialized properly, an empty string is
     * returned instead.
     *
     * @param non-empty-string $key
     *
     * @return string the corresponding element from the record data array
     */
    public function getRecordPropertyString(string $key): string
    {
        return $this->hasKey($key) ? \trim((string)$this->recordData[$key]) : '';
    }

    /**
     * Gets a decimal element of the record data array.
     * If the array has not been initialized properly, '0.00' is returned
     * instead.
     *
     * @param non-empty-string $key
     *
     * @return string the corresponding element from the record data array
     */
    public function getRecordPropertyDecimal(string $key): string
    {
        return $this->hasKey($key) ? \trim((string)$this->recordData[$key]) : '0.00';
    }

    /**
     * Checks a string element of the record data array for existence and
     * non-emptiness.
     *
     * @param non-empty-string $key
     *
     * @return bool true if the corresponding string exists and is non-empty
     */
    public function hasRecordPropertyString(string $key): bool
    {
        return $this->getRecordPropertyString($key) !== '';
    }

    /**
     * Checks an integer element of the record data array for existence and non-zeroness.
     *
     * @param non-empty-string $key
     *
     * @return bool true if the corresponding value exists and is non-zero
     */
    public function hasRecordPropertyInteger(string $key): bool
    {
        return $this->getRecordPropertyInteger($key) !== 0;
    }

    /**
     * Checks a decimal element of the record data array for existence and value != 0.00.
     *
     * @param non-empty-string $key
     *
     * @return bool true if the corresponding field exists and its value is not zero (with decimals)
     */
    public function hasRecordPropertyDecimal(string $key): bool
    {
        $emptyValues = ['', '0.00', '0.0', '0'];

        return !\in_array($this->getRecordPropertyDecimal($key), $emptyValues, true);
    }

    /**
     * Gets an int element of the record data array.
     * If the array has not been initialized properly, 0 is returned instead.
     *
     * @param non-empty-string $key
     *
     * @return int the corresponding element from the record data array
     */
    public function getRecordPropertyInteger(string $key): int
    {
        return $this->hasKey($key) ? (int)$this->recordData[$key] : 0;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertNonEmptyKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('$key must not be empty.', 1574548978);
        }
    }

    /**
     * Sets an int element of the record data array.
     *
     * @param non-empty-string $key
     *
     * @throws \InvalidArgumentException
     */
    protected function setRecordPropertyInteger(string $key, int $value): void
    {
        $this->assertNonEmptyKey($key);

        $this->recordData[$key] = $value;
    }

    /**
     * Sets a string element of the record data array (and trims it).
     *
     * @param non-empty-string $key
     */
    protected function setRecordPropertyString(string $key, string $value): void
    {
        $this->assertNonEmptyKey($key);

        $this->recordData[$key] = \trim($value);
    }

    /**
     * Sets a boolean element of the record data array.
     *
     * @param non-empty-string $key
     * @param mixed $value
     */
    protected function setRecordPropertyBoolean(string $key, $value): void
    {
        $this->assertNonEmptyKey($key);

        $this->recordData[$key] = (int)(bool)$value;
    }

    /**
     * Gets an element of the record data array, converted to a boolean.
     * If the array has not been initialized properly, false is returned.
     *
     * @param non-empty-string $key
     */
    public function getRecordPropertyBoolean(string $key): bool
    {
        return $this->hasKey($key) ? (bool)$this->recordData[$key] : false;
    }

    /**
     * Checks whether $this->recordData is initialized at all and whether a given key exists.
     *
     * @param non-empty-string $key
     *
     * @return bool true if `$this->recordData` has been initialized and the array key exists, false otherwise
     *
     * @throws \InvalidArgumentException
     */
    private function hasKey(string $key): bool
    {
        $this->assertNonEmptyKey($key);

        return isset($this->recordData[$key]);
    }

    /**
     * Writes this record to the DB.
     *
     * The UID of the parent page must be set in $this->recordData['pid'].
     * (otherwise the record will be created in the root page).
     *
     * This method is to be preferred over `saveToDatabase`.
     *
     * @return bool true if everything went OK, false otherwise
     */
    public function commitToDatabase(): bool
    {
        if (!$this->isOk()) {
            return false;
        }

        $now = (int)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $this->setRecordPropertyInteger('tstamp', $now);

        $connection = self::getConnectionForOwnTable();
        if (!$this->isPersisted || !$this->hasUid()) {
            $this->setRecordPropertyInteger('crdate', $now);
            $connection->insert(static::$tableName, $this->recordData);
            $lastInsertId = (int)$connection->lastInsertId(static::$tableName);
            if ($lastInsertId > 0) {
                $this->setUid($lastInsertId);
            }
        } else {
            $connection->update(static::$tableName, $this->recordData, ['uid' => $this->getUid()]);
        }

        $this->isPersisted = true;

        return true;
    }

    /**
     * Adds m:n records that are referenced by this record.
     *
     * Before this function may be called, $this->recordData['uid'] must be set correctly.
     *
     * @param non-empty-string $mmTable the name of the m:n table, having the fields uid_local, uid_foreign and sorting
     * @param array<int> $references UIDs of records from the foreign table to which we should create references
     *
     * @return int<0, max> the number of created m:n records
     *
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function createMmRecords(string $mmTable, array $references): int
    {
        if (!$this->hasUid()) {
            throw new \BadMethodCallException('createMmRecords may only be called on objects with a UID.', 1333292371);
        }

        $dataTemplate = ['uid_local' => $this->getUid()];

        $connection = self::getConnectionForTable($mmTable);
        $recordCount = 0;
        foreach ($references as $foreignUid) {
            if ($foreignUid <= 0) {
                continue;
            }

            $data = \array_merge(
                $dataTemplate,
                ['uid_foreign' => $foreignUid, 'sorting' => $recordCount + 1],
            );
            $connection->insert($mmTable, $data);
            $recordCount++;
        }

        return $recordCount;
    }

    /**
     * @return int<0, max> our UID (or 0 if there is an error)
     */
    public function getUid(): int
    {
        $uid = $this->getRecordPropertyInteger('uid');
        \assert($uid >= 0);

        return $uid;
    }

    /**
     * @param int<0, max> $uid
     */
    protected function setUid(int $uid): void
    {
        $this->setRecordPropertyInteger('uid', $uid);
    }

    public function hasUid(): bool
    {
        return $this->hasRecordPropertyInteger('uid');
    }

    /**
     * @return string our title (or '' if there is an error)
     */
    public function getTitle(): string
    {
        return $this->getRecordPropertyString('title');
    }

    /**
     * Sets the title element of the record data array.
     *
     * @param string $title the value that will be written into the title element
     */
    public function setTitle(string $title): void
    {
        $this->setRecordPropertyString('title', $title);
    }

    /**
     * Returns this record's page UID.
     *
     * @return int<0, max> the page UID for this record
     */
    public function getPageUid(): int
    {
        $pid = $this->getRecordPropertyInteger('pid');
        \assert($pid >= 0);

        return $pid;
    }

    /**
     * Gets a list of the titles of records referenced by this record.
     *
     * @param non-empty-string $foreignTable the name of the foreign table (must not be empty), having the uid and
     *     title fields
     * @param non-empty-string $mmTable the name of the m:m table, having the uid_local, uid_foreign and sorting fields
     *
     * @return list<string> the titles of the referenced records
     */
    protected function getMmRecordTitles(string $foreignTable, string $mmTable): array
    {
        return $this->getMmRecordTitlesByUid($foreignTable, $mmTable, $this->getUid());
    }

    /**
     * Gets a list of the titles of records referenced by this record.
     *
     * @param non-empty-string $foreignTable the name of the foreign table (must not be empty), having the uid and
     *     title fields
     * @param non-empty-string $mmTable the name of the m:m table, having the uid_local, uid_foreign and sorting fields
     * @param int<0, max> $uid
     *
     * @return list<string> the titles of the referenced records
     */
    protected function getMmRecordTitlesByUid(string $foreignTable, string $mmTable, int $uid): array
    {
        $titles = [];
        foreach ($this->getMmRecordsByUid($foreignTable, $mmTable, $uid) as $row) {
            $titles[] = (string)$row['title'];
        }

        return $titles;
    }

    /**
     * Gets records referenced by this record.
     *
     * @param non-empty-string $foreignTable the name of the foreign table (must not be empty), having the uid and
     *     title fields
     * @param non-empty-string $mmTable the name of the m:m table, having the uid_local, uid_foreign and sorting fields
     *
     * @return array<array<string, bool|int|string|null>>
     */
    protected function getMmRecords(string $foreignTable, string $mmTable): array
    {
        return $this->getMmRecordsByUid($foreignTable, $mmTable, $this->getUid());
    }

    /**
     * Gets records referenced by the record with the given UID.
     *
     * @param non-empty-string $foreignTable the name of the foreign table (must not be empty), having the uid and
     *     title fields
     * @param non-empty-string $mmTable the name of the m:m table, having the uid_local, uid_foreign and sorting fields
     * @param int<0, max> $uid
     *
     * @return array<array<string, string|int|bool|null>>
     */
    protected function getMmRecordsByUid(string $foreignTable, string $mmTable, int $uid): array
    {
        $query = self::getQueryBuilderForTable($foreignTable);
        $queryResult = $query
            ->select($foreignTable . '.*')
            ->from($foreignTable)
            ->join(
                $foreignTable,
                $mmTable,
                'mm',
                $query->expr()->eq('mm.uid_foreign', $query->quoteIdentifier($foreignTable . '.uid')),
            )
            ->where($query->expr()->eq('mm.uid_local', $query->createNamedParameter($uid)))
            ->orderBy('mm.sorting')
            ->executeQuery();

        return $queryResult->fetchAllAssociative();
    }

    protected static function getQueryBuilderForOwnTable(): QueryBuilder
    {
        return self::getConnectionPool()->getQueryBuilderForTable(static::$tableName);
    }

    protected static function getQueryBuilderForTable(string $table): QueryBuilder
    {
        return self::getConnectionPool()->getQueryBuilderForTable($table);
    }

    protected static function getConnectionForOwnTable(): Connection
    {
        return self::getConnectionForTable(static::$tableName);
    }

    protected static function getConnectionForTable(string $table): Connection
    {
        return self::getConnectionPool()->getConnectionForTable($table);
    }

    private static function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    protected function getFileRepository(): FileRepository
    {
        return GeneralUtility::makeInstance(FileRepository::class);
    }

    protected function renderAsRichText(string $rawData): string
    {
        return GeneralUtility::makeInstance(RichTextViewHelper::class)->render($rawData);
    }

    protected function getDateFormat(): string
    {
        $format = LocalizationUtility::translate('dateFormat', 'seminars');
        \assert(\is_string($format));

        return $format;
    }

    protected function getTimeFormat(): string
    {
        $format = LocalizationUtility::translate('timeFormat', 'seminars');
        \assert(\is_string($format));

        return $format;
    }
}
