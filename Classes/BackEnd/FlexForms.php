<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BackEnd;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is needed to dynamically create the list of selectable database
 * columns for the pi1 flex forms.
 *
 * @internal
 */
class FlexForms
{
    private ConnectionPool $connectionPool;

    public function __construct()
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * Returns the configuration for the flex forms field
     * "showFeUserFieldsInRegistrationsList" with the selectable database columns.
     *
     * @param array<string, mixed> $configuration the flex forms configuration
     *
     * @return array<string, mixed> the modified flex forms configuration including the selectable database columns
     */
    public function getShowFeUserFieldsInRegistrationsList(array $configuration): array
    {
        foreach ($this->getColumnsOfTable('fe_users') as $column) {
            $configuration['items'][] = [0 => $column, 1 => $column];
        }

        return $configuration;
    }

    /**
     * Returns the configuration for the flex forms field
     * "showRegistrationFieldsInRegistrationList" with the selectable database columns.
     *
     * @param array<string, mixed> $configuration the flex forms configuration
     *
     * @return array<string, mixed> the modified flex forms configuration including the selectable database columns
     */
    public function getShowRegistrationFieldsInRegistrationList(array $configuration): array
    {
        foreach ($this->getColumnsOfTable('tx_seminars_attendances') as $column) {
            $configuration['items'][] = [0 => $column, 1 => $column];
        }

        return $configuration;
    }

    /**
     * Returns the column names of the given table.
     *
     * @param string $table the table name to get the columns for, must not be empty
     *
     * @return list<string> the column names of the given table name, will not be empty
     *
     * @throws \InvalidArgumentException
     */
    private function getColumnsOfTable(string $table): array
    {
        if ($table === '') {
            throw new \InvalidArgumentException('$table must not be empty.', 1333291708);
        }

        $statement = $this->connectionPool
            ->getConnectionForTable($table)
            ->query('SHOW FULL COLUMNS FROM `' . $table . '`');
        $columns = [];
        foreach ($statement->fetchAll() as $row) {
            $field = $row['Field'];
            \assert(\is_string($field));
            $columns[] = $field;
        }

        return $columns;
    }
}
