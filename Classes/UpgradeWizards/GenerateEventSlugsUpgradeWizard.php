<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\UpgradeWizards;

use OliverKlee\Seminars\Seo\SlugGenerator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Generates slugs for events.
 *
 * @internal
 */
class GenerateEventSlugsUpgradeWizard implements UpgradeWizardInterface, RepeatableInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const TABLE_NAME_EVENTS = 'tx_seminars_seminars';

    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    public function getIdentifier(): string
    {
        return 'seminars_generateEventSlugs';
    }

    public function getTitle(): string
    {
        return 'Generates slugs for all events that do not have a slug yet.';
    }

    public function getDescription(): string
    {
        return 'Automatically generates the slugs for all events using their titles and UIDs.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->getQueryBuilder();

        $query = $queryBuilder
            ->count('*')
            ->from(self::TABLE_NAME_EVENTS)
            ->where($queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter('', Connection::PARAM_STR)))
            ->orWhere($queryBuilder->expr()->isNull('slug'));

        $queryResult = $query->executeQuery();
        $count = (int)$queryResult->fetchOne();

        return $count > 0;
    }

    public function executeUpdate(): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        $query = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME_EVENTS)
            ->where($queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter('', Connection::PARAM_STR)))
            ->orWhere($queryBuilder->expr()->isNull('slug'))
            ->orderBy('uid');
        $queryResult = $query->executeQuery();

        $slugGenerator = GeneralUtility::makeInstance(SlugGenerator::class);
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME_EVENTS);
        /** @var array<string, string> $row */
        foreach ($queryResult->fetchAllAssociative() as $row) {
            /** @var array{uid: int<0, max>, title: string, object_type: int<0, max>, topic: int<0, max>} $row */
            $slug = $slugGenerator->generateSlug(['record' => $row]);
            $connection->update(self::TABLE_NAME_EVENTS, ['slug' => $slug], ['uid' => $row['uid']]);
        }

        if ($this->logger instanceof LoggerAwareInterface) {
            $this->logger->info('All events that had no slug now have one.');
        }

        return true;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME_EVENTS);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }
}
