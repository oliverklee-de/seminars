<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\BagBuilder;

use OliverKlee\Seminars\Bag\CategoryBag;

/**
 * This builder class creates customized category bag objects.
 *
 * @extends AbstractBagBuilder<CategoryBag>
 */
class CategoryBagBuilder extends AbstractBagBuilder
{
    /**
     * @var class-string<CategoryBag> class name of the bag class that will be built
     */
    protected string $bagClassName = CategoryBag::class;

    /**
     * @var non-empty-string the table name of the bag to build
     */
    protected string $tableName = 'tx_seminars_categories';

    /**
     * @var string the sorting field
     */
    protected string $orderBy = 'title';

    /**
     * @var string the UIDs of the current events as comma-separated list, will be set by limitToEvents
     */
    protected string $eventUids = '';

    /**
     * Limits the bag to the categories of the events provided by the parameter `$eventUids`.
     *
     * Example: The events with the provided UIDs reference categories 9 and 12.
     * So the bag will be limited to the categories 9 and 12 (plus any additional
     * limits).
     *
     * @param string $eventUids comma-separated list of UIDs of the events to which the category selection
     *        should be limited, may be empty, all UIDs must be > 0
     */
    public function limitToEvents(string $eventUids): void
    {
        $cleanUids = \trim($eventUids);
        if ($cleanUids === '') {
            return;
        }

        if (!preg_match('/^(\\d+,)*\\d+$/', $cleanUids) || preg_match('/(^|,)0+(,|$)/', $cleanUids)) {
            throw new \InvalidArgumentException(
                '$eventUids must be a comma-separated list of positive integers.',
                1333292640,
            );
        }

        $this->whereClauseParts['event'] = 'EXISTS ('
            . 'SELECT * FROM tx_seminars_seminars_categories_mm'
            . ' WHERE tx_seminars_seminars_categories_mm.uid_local IN('
            . $cleanUids . ') AND tx_seminars_seminars_categories_mm'
            . '.uid_foreign = tx_seminars_categories.uid)';

        $this->eventUids = $cleanUids;
    }

    /**
     * Sets the values of additionalTables, whereClauseParts and orderBy for the
     * category bag.
     * These changes are made so that the categories are sorted by the relation
     * sorting set in the back end.
     *
     * Before this function can be called, limitToEvents has to be called.
     */
    public function sortByRelationOrder(): void
    {
        if ($this->eventUids === '') {
            throw new \BadMethodCallException(
                'The event UIDs were empty. This means limitToEvents has not been called. '
                . 'LimitToEvents has to be called before calling this function.',
                1333292662,
            );
        }

        $this->addAdditionalTableName('tx_seminars_seminars_categories_mm');
        $this->whereClauseParts['category'] = 'tx_seminars_categories.uid = '
            . 'tx_seminars_seminars_categories_mm.uid_foreign AND '
            . 'tx_seminars_seminars_categories_mm.uid_local IN ('
            . $this->eventUids . ')';
        $this->orderBy = 'tx_seminars_seminars_categories_mm.sorting ASC';
    }
}
