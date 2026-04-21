<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Venue;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Venue>
 */
class VenueRepository extends Repository
{
    protected $defaultOrderings = ['title' => QueryInterface::ORDER_ASCENDING];

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param non-empty-array<int> $uids
     *
     * @return QueryResultInterface<Venue>
     */
    public function findVenuesByUids(array $uids): QueryResultInterface
    {
        $query = $this->createQuery();

        return $query
            ->matching($query->in('uid', $uids))
            ->execute();
    }

    /**
     * Finds the venues the given user has access to in the FE editor.
     *
     * If the user has no venue access restriction, returns all venues.
     *
     * @return QueryResultInterface<Venue>
     */
    public function findVenuesAccessibleToFrontendUser(FrontendUser $user): QueryResultInterface
    {
        $accessibleVenueUids = $user->getUidsOfAvailableVenuesForFrontEndEditor();

        return ($accessibleVenueUids !== [])
            ? $this->findVenuesByUids($accessibleVenueUids)
            : $this->findAll();
    }
}
