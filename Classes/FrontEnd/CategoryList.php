<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use OliverKlee\Seminars\BagBuilder\CategoryBagBuilder;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\OldModel\LegacyCategory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * This class creates a category list.
 */
class CategoryList extends AbstractView
{
    /**
     * Creates an HTML list of categories.
     *
     * This list is limited to categories for which there are events in the
     * selected time-frame and in the selected sysfolders. Categories for which
     * all events are canceled will always be ignored.
     *
     * @return string HTML code of the category list or a formatted message if there are no categories to display
     */
    public function render(): string
    {
        $recursive = \max(0, $this->getConfValueInteger('recursive'));
        $seminarBagBuilder = GeneralUtility::makeInstance(EventBagBuilder::class);
        $seminarBagBuilder->setSourcePages($this->getConfValueString('pages'), $recursive);

        $seminarBagBuilder->ignoreCanceledEvents();
        try {
            // @phpstan-ignore-next-line We're allowing invalid values to be passed and rely on the exception for this.
            $seminarBagBuilder->setTimeFrame($this->getConfValueString('timeframeInList', 's_template_special'));
        } catch (\Exception $exception) {
            // Ignores the exception because the user will be warned of the problem by the configuration check.
        }

        $eventUids = $seminarBagBuilder->build()->getUids();

        $categoryBagBuilder = GeneralUtility::makeInstance(CategoryBagBuilder::class);
        $categoryBagBuilder->limitToEvents($eventUids);
        $categoryBag = $categoryBagBuilder->build();

        // Only lists categories for which there are events.
        if (($eventUids !== '') && !$categoryBag->isEmpty()) {
            $allCategories = '';

            /** @var LegacyCategory $category */
            foreach ($categoryBag as $category) {
                $link = $this->createLinkToListViewLimitedByCategory(
                    $category->getUid(),
                    \htmlspecialchars($category->getTitle(), ENT_QUOTES | ENT_HTML5),
                );
                $this->setMarker('category_title', $link);

                $allCategories .= $this->getSubpart('SINGLE_CATEGORY_ITEM');
            }

            $this->setMarker('all_category_items', $allCategories);
            $result = $this->getSubpart('VIEW_CATEGORIES');
        } else {
            $result = $this->getSubpart('VIEW_NO_CATEGORIES');
        }

        return $result;
    }

    /**
     * Creates a hyperlink with the title $title to the current list view,
     * limited to the category provided by the parameter $categoryUid.
     *
     * @param int $categoryUid UID of the category to which the list view should be limited, must be > 0
     * @param string $title title of the link, must not be empty
     *
     * @return string link to the list view limited to the given category or an
     *                empty string if there is an error
     */
    public function createLinkToListViewLimitedByCategory(int $categoryUid, string $title): string
    {
        if ($categoryUid <= 0) {
            throw new \InvalidArgumentException('$categoryUid must be > 0.', 1333293037);
        }
        if ($title === '') {
            throw new \InvalidArgumentException('$title must not be empty.', 1333293044);
        }
        \assert($this->cObj instanceof ContentObjectRenderer);

        return $this->cObj->typoLink(
            $title,
            [
                'parameter' => (string)$this->getConfValueInteger('listPID'),
                'additionalParams' => HttpUtility::buildQueryString(['tx_seminars_pi1[category]' => $categoryUid], '&'),
            ],
        );
    }

    /**
     * Creates the list of categories for the event list view.
     *
     * @param array<int, array{title: string}> $categoriesToDisplay
     *
     * @return string the HTML output, will be empty if $categoriesToDisplay is empty
     */
    public function createCategoryList(array $categoriesToDisplay): string
    {
        if (empty($categoriesToDisplay)) {
            return '';
        }

        $allCategoryLinks = [];
        foreach ($categoriesToDisplay as $uid => $categoryData) {
            $linkText = \htmlspecialchars($categoryData['title'], ENT_QUOTES | ENT_HTML5);
            $allCategoryLinks[] = $this->createLinkToListViewLimitedByCategory($uid, $linkText);
        }

        return \implode(', ', $allCategoryLinks);
    }
}
