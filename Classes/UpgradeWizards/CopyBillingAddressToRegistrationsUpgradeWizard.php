<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\UpgradeWizards;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Copies the billing address for all registrations from the FE user records to ensure all registrations have a
 * billing address.
 *
 * @deprecated will be removed in version 7.0.0 in #4486
 *
 * @internal
 */
class CopyBillingAddressToRegistrationsUpgradeWizard implements
    UpgradeWizardInterface,
    RepeatableInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const TABLE_NAME_REGISTRATIONS = 'tx_seminars_attendances';
    private const TABLE_NAME_USERS = 'fe_users';

    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    public function getIdentifier(): string
    {
        return 'seminars_copyBillingAddressToRegistrations';
    }

    public function getTitle(): string
    {
        return 'Copy billing address to registrations';
    }

    public function getDescription(): string
    {
        return 'Copies the billing address for all registrations from the FE users.';
    }

    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->getRegistrationQueryBuilder();

        $billingAddressCondition = $queryBuilder
            ->expr()
            ->eq('separate_billing_address', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        $deletedCondition = $queryBuilder
            ->expr()
            ->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        $query = $queryBuilder
            ->count('*')
            ->from(self::TABLE_NAME_REGISTRATIONS)
            ->where($billingAddressCondition)
            ->andWhere($deletedCondition);

        $queryResult = $query->executeQuery();
        $count = (int)$queryResult->fetchOne();

        return $count > 0;
    }

    public function executeUpdate(): bool
    {
        $registrationConnection = $this->getConnectionPool()->getConnectionForTable(self::TABLE_NAME_REGISTRATIONS);
        $registrationQueryBuilder = $this->getRegistrationQueryBuilder();
        $userQueryBuilder = $this->getUserQueryBuilder();

        $billingAddressCondition = $registrationQueryBuilder
            ->expr()
            ->eq('separate_billing_address', $registrationQueryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        $deletedCondition = $registrationQueryBuilder
            ->expr()
            ->eq('deleted', $registrationQueryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        $registrationsQuery = $registrationQueryBuilder
            ->select('*')
            ->from(self::TABLE_NAME_REGISTRATIONS)
            ->where($billingAddressCondition)
            ->andWhere($deletedCondition);

        $registrations = $registrationsQuery->executeQuery()->fetchAllAssociative();
        foreach ($registrations as $registration) {
            $registrationUid = (int)($registration['uid'] ?? 0);
            \assert($registrationUid > 0);
            $updatedRegistrationData = ['separate_billing_address' => 1];

            $userUid = (int)($registration['user'] ?? 0);
            if ($userUid > 0) {
                $uidCondition = $userQueryBuilder
                    ->expr()
                    ->eq('uid', $userQueryBuilder->createNamedParameter($userUid, Connection::PARAM_INT));
                $deletedCondition = $userQueryBuilder
                    ->expr()
                    ->eq('deleted', $userQueryBuilder->createNamedParameter(0, Connection::PARAM_INT));
                $userQuery = $userQueryBuilder
                    ->select('*')
                    ->from(self::TABLE_NAME_USERS)
                    ->where($uidCondition)
                    ->andWhere($deletedCondition);
                $user = $userQuery->executeQuery()->fetchAssociative();
                if (\is_array($user)) {
                    $updatedRegistrationData['company'] = \trim(\substr($user['company'] ?? '', 0, 80));
                    $updatedRegistrationData['address'] = \trim(\substr($user['address'] ?? '', 0, 40));
                    $updatedRegistrationData['zip'] = \trim(\substr($user['zip'] ?? '', 0, 10));
                    $updatedRegistrationData['city'] = \trim(\substr($user['city'] ?? '', 0, 40));
                    $updatedRegistrationData['country'] = \trim(\substr($user['country'] ?? '', 0, 40));
                    $updatedRegistrationData['telephone'] = \trim(\substr($user['telephone'] ?? '', 0, 40));
                    $updatedRegistrationData['email'] = \trim(\substr($user['email'] ?? '', 0, 50));

                    $fullName = \trim($user['name'] ?? '');
                    if ($fullName === '') {
                        $firstName = $user['first_name'] ?? '';
                        $lastName = $user['last_name'] ?? '';
                        $fullName = \trim($firstName . ' ' . $lastName);
                    }
                    $updatedRegistrationData['name'] = \trim(\substr($fullName, 0, 80));
                }
            }

            $registrationConnection
                ->update(self::TABLE_NAME_REGISTRATIONS, $updatedRegistrationData, ['uid' => $registrationUid]);
        }

        if ($this->logger instanceof LoggerAwareInterface) {
            $this->logger->info(
                '{registrationCount} registrations updated with billing address',
                ['registrationCount' => \count($registrations)],
            );
        }

        return true;
    }

    private function getConnectionPool(): ConnectionPool
    {
        return $this->connectionPool;
    }

    private function getRegistrationQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_NAME_REGISTRATIONS);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    private function getUserQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_NAME_USERS);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }
}
