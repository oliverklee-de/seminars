<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Bag\EventBag;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use OliverKlee\Seminars\Hooks\HookProvider;
use OliverKlee\Seminars\Hooks\Interfaces\SeminarSelectorWidget;
use OliverKlee\Seminars\Mapper\PlaceMapper;
use OliverKlee\Seminars\Model\Place;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyOrganizer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class creates a selector widget.
 */
class SelectorWidget extends AbstractView
{
    /**
     * @var list<non-empty-string> the keys of the search fields which should be displayed in the search form
     */
    private array $displayedSearchFields = [];

    /**
     * @var string the prefix of every subpart of the search widget
     */
    private const SUBPART_PREFIX = 'SEARCH_PART_';

    /**
     * @var EventBag all seminars to show in the list view
     */
    private EventBag $seminarBag;

    /**
     * @var Collection<Place>|null all places which are assigned to at least one event
     */
    private ?Collection $places = null;

    protected ?HookProvider $selectorWidgetHookProvider = null;

    /**
     * Returns the selector widget if it is not hidden.
     *
     * The selector widget will automatically be hidden, if no search option is
     * selected to be displayed.
     *
     * @return string the HTML code of the selector widget, may be empty
     */
    public function render(): string
    {
        if (!$this->hasConfValueString('displaySearchFormFields', 's_listView')) {
            return '';
        }

        $this->initialize();

        $this->fillOrHideSearchSubpart('event_type');
        $this->hideSubparts(self::SUBPART_PREFIX . 'language');
        $this->fillOrHideSearchSubpart('place');
        $this->hideSubparts(self::SUBPART_PREFIX . 'country');
        $this->fillOrHideSearchSubpart('city');
        $this->fillOrHideSearchSubpart('organizer');
        $this->fillOrHideSearchSubpart('categories');
        $this->fillOrHideFullTextSearch();
        $this->fillOrHideDateSearch();
        $this->fillOrHideAgeSearch();
        $this->fillOrHidePriceSearch();

        $this->getSelectorWidgetHookProvider()->executeHook('modifySelectorWidget', $this, $this->seminarBag);

        return $this->getSubpart('SELECTOR_WIDGET');
    }

    /**
     * Initializes some variables needed for further processing.
     */
    private function initialize(): void
    {
        $this->displayedSearchFields = GeneralUtility::trimExplode(
            ',',
            $this->getConfValueString('displaySearchFormFields', 's_listView'),
            true,
        );

        $builder = GeneralUtility::makeInstance(EventBagBuilder::class);
        $builder->limitToEventTypes(
            GeneralUtility::intExplode(',', $this->getConfValueString('limitListViewToEventTypes', 's_listView'), true),
        );
        $builder->limitToOrganizers($this->getConfValueString('limitListViewToOrganizers', 's_listView'));
        $builder->limitToCategories($this->getConfValueString('limitListViewToCategories', 's_listView'));

        $this->seminarBag = $builder->build();
    }

    //////////////////////
    // Utility functions
    //////////////////////

    /**
     * Adds a dummy option to the array of options. This is needed if the
     * user wants to show the option box as drop-down selector instead of
     * a multi-line select.
     *
     * With the default configuration, this method is a no-op as
     * "showEmptyEntryInOptionLists" is disabled.
     *
     * If this option is activated in the TS configuration, the dummy option
     * will be prepended to the existing arrays. So we can be sure that the
     * dummy option will always be the first one in the array and thus shown
     * first in the drop-down.
     *
     * @param string[] $options options, may be empty
     */
    private function addEmptyOptionIfNeeded(array &$options): void
    {
        if (!$this->getConfValueBoolean('showEmptyEntryInOptionLists', 's_template_special')) {
            return;
        }

        $options = [0 => $this->translate('label_selector_pleaseChoose')] + $options;
    }

    /**
     * Removes the dummy option from the submitted form data.
     *
     * @param array $formData the POST data submitted from the form, may be empty
     *
     * @return array the POST data without the dummy option
     */
    public static function removeDummyOptionFromFormData(array $formData): array
    {
        $cleanedFormData = [];

        foreach ($formData as $value) {
            if ($value !== 0 && $value !== '0') {
                $cleanedFormData[] = $value;
            }
        }

        return $cleanedFormData;
    }

    /**
     * Creates the HTML code for a single option box of the selector widget.
     *
     * @param 'event_type'|'city'|'places' $name
     * @param string[] $options
     *        the options for the option box with the option value as key and the option label as value, may be empty
     *
     * @return string the HTML content for the select, will not be empty
     */
    private function createOptionBox(string $name, array $options): string
    {
        $this->setMarker('options_header', $this->translate('label_' . $name));
        $this->setMarker('optionbox_name', $this->prefixId . '[' . $name . '][]');
        $this->setMarker('optionbox_id', $this->prefixId . '-' . $name);

        $optionsList = '';
        foreach ($options as $key => $label) {
            $this->setMarker('option_label', \htmlspecialchars($label, ENT_QUOTES | ENT_HTML5));
            $this->setMarker('option_value', $key);

            // Preselects the option if it was selected by the user.
            $selectedFields = $this->piVars[$name] ?? null;
            if (\is_array($selectedFields) && \in_array((string)$key, $selectedFields, true)) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            $this->setMarker('option_selected', $selected);

            $optionsList .= $this->getSubpart('OPTIONS_ENTRY');
        }

        $this->setMarker('options', $optionsList);

        return $this->getSubpart('OPTIONS_BOX');
    }

    /**
     * Creates a list of places with all places that are assigned to at least
     * one event.
     *
     * The list of places is stored in the member variable $this->places.
     *
     * Before this function is called, it must be assured that the seminar bag
     * is not empty.
     */
    private function collectPlaces(): void
    {
        if ($this->seminarBag->isEmpty()) {
            throw new \BadMethodCallException(
                'The seminar bag must not be empty when calling this function.',
                1333293276,
            );
        }
        if ($this->places instanceof Collection) {
            return;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_seminars_sites');
        $eventUids = GeneralUtility::intExplode(',', $this->seminarBag->getUids());
        $eventUidsParameter = $queryBuilder->createNamedParameter($eventUids, Connection::PARAM_INT_ARRAY);
        $dataOfPlacesQueryResult = $queryBuilder
            ->select('tx_seminars_sites.*')
            ->from('tx_seminars_sites')
            ->join(
                'tx_seminars_sites',
                'tx_seminars_seminars_place_mm',
                'mm',
                $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->quoteIdentifier('tx_seminars_sites.uid')),
            )
            ->where($queryBuilder->expr()->in('mm.uid_local', $eventUidsParameter))
            ->orderBy('mm.sorting')
            ->executeQuery();
        $dataOfPlaces = $dataOfPlacesQueryResult->fetchAllAssociative();

        $mapper = MapperRegistry::getInstance()->getByClassName(PlaceMapper::class);
        $this->places = $mapper->getListOfModels($dataOfPlaces);
    }

    /**
     * Checks whether a given search field key should be displayed.
     *
     * @param string $fieldToCheck the search field name to check, must not be empty
     *
     * @return bool TRUE if the given field should be displayed as per
     *                 configuration, FALSE otherwise
     */
    protected function hasSearchField(string $fieldToCheck): bool
    {
        return \in_array($fieldToCheck, $this->displayedSearchFields, true);
    }

    /**
     * Creates a drop-down, including an empty option at the top.
     *
     * @param string[] $options the options for the drop-down, the keys will be used as values and the array values
     *        as labels for the options, may be empty
     * @param string $name the HTML name of the drop-down, must be not empty and must be unique
     *
     * @return string the generated HTML, will not be empty
     */
    private function createDropDown(array $options, string $name): string
    {
        $this->setMarker('dropdown_name', $this->prefixId . '[' . $name . ']');
        $this->setMarker('dropdown_id', $this->prefixId . '-' . $name);

        // Adds an empty option to the dropdown
        $this->setMarker('option_value', 0);
        $this->setMarker('option_label', '&nbsp;');
        $this->setMarker('option_selected', '');
        $optionsList = $this->getSubpart('OPTIONS_ENTRY');

        foreach ($options as $optionValue => $optionName) {
            $this->setMarker('option_value', $optionValue);
            $this->setMarker('option_label', $optionName);

            if ((string)($this->piVars[$name] ?? '') === (string)$optionValue) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }

            $this->setMarker('option_selected', $selected);
            $optionsList .= $this->getSubpart('OPTIONS_ENTRY');
        }

        $this->setMarker('dropdown_options', $optionsList);

        return $this->getSubpart('SINGLE_DROPDOWN');
    }

    /**
     * Gets the hook provider for the selector widget.
     *
     * @return HookProvider
     */
    protected function getSelectorWidgetHookProvider(): HookProvider
    {
        if (!$this->selectorWidgetHookProvider instanceof SeminarSelectorWidget) {
            $this->selectorWidgetHookProvider = GeneralUtility::makeInstance(
                HookProvider::class,
                SeminarSelectorWidget::class,
            );
        }

        return $this->selectorWidgetHookProvider;
    }

    ///////////////////////////////////////////////////////////////
    // Functions for hiding or filling the search widget subparts
    ///////////////////////////////////////////////////////////////

    /**
     * Fills or hides the subpart for the given search field.
     *
     * @param 'event_type'|'place'|'city'|'organizer'|'categories' $searchField
     */
    private function fillOrHideSearchSubpart(string $searchField): void
    {
        if (!$this->hasSearchField($searchField)) {
            $this->hideSubparts(self::SUBPART_PREFIX . strtoupper($searchField));
            return;
        }

        switch ($searchField) {
            case 'event_type':
                $optionData = $this->getEventTypeData();
                break;
            case 'place':
                $optionData = $this->getPlaceData();
                break;
            case 'city':
                $optionData = $this->getCityData();
                break;
            case 'organizer':
                $optionData = $this->getOrganizerData();
                break;
            case 'categories':
                $optionData = $this->getCategoryData();
                break;
            default:
                throw new \InvalidArgumentException(
                    'The given search field . "' . $searchField . '" was not an allowed value. ' .
                    'Allowed values are: "event_type", "city", "place" or "organizer".',
                    1333293298,
                );
        }

        asort($optionData, SORT_STRING);
        $this->addEmptyOptionIfNeeded($optionData);
        $optionBox = $this->createOptionBox($searchField, $optionData);

        $this->setMarker('options_' . $searchField, $optionBox);
    }

    /**
     * Fills or hides the full text search subpart.
     */
    private function fillOrHideFullTextSearch(): void
    {
        if (!$this->hasSearchField('full_text_search')) {
            $this->hideSubparts(self::SUBPART_PREFIX . 'TEXT');

            return;
        }

        $this->setMarker(
            'searchbox_value',
            \htmlspecialchars((string)($this->piVars['sword'] ?? ''), ENT_QUOTES | ENT_HTML5),
        );
    }

    /**
     * Fills or hides the date search subpart.
     */
    private function fillOrHideDateSearch(): void
    {
        if (!$this->hasSearchField('date')) {
            $this->hideSubparts(self::SUBPART_PREFIX . 'DATE');
            return;
        }

        $dateArrays = $this->createDateArray();

        foreach (['from', 'to'] as $fromOrTo) {
            $dropDowns = '';
            foreach ($dateArrays as $dropDownPart => $dateArray) {
                $dropDowns .= $this->createDropDown(
                    $dateArray,
                    $fromOrTo . '_' . $dropDownPart,
                );
            }
            $this->setMarker('options_date_' . $fromOrTo, $dropDowns);
        }
    }

    /**
     * Fills or hides the age search subpart.
     */
    private function fillOrHideAgeSearch(): void
    {
        if (!$this->hasSearchField('age')) {
            $this->hideSubparts(self::SUBPART_PREFIX . 'AGE');
            return;
        }
        $age = (int)($this->piVars['age'] ?? 0);

        $this->setMarker('age_value', $age > 0 ? $age : '');
    }

    /**
     * Fills or hides the price search subpart.
     */
    private function fillOrHidePriceSearch(): void
    {
        if (!$this->hasSearchField('price')) {
            $this->hideSubparts(self::SUBPART_PREFIX . 'PRICE');
            return;
        }

        $priceFrom = (int)($this->piVars['price_from'] ?? 0);
        $priceTo = (int)($this->piVars['price_to'] ?? 0);

        $this->setMarker('price_from_value', $priceFrom > 0 ? $priceFrom : '');
        $this->setMarker('price_to_value', $priceTo > 0 ? $priceTo : '');
    }

    ///////////////////////////////////////////////////////
    // Functions for retrieving Data for the option boxes
    ///////////////////////////////////////////////////////

    /**
     * Gets the data for the eventy type search field options.
     *
     * @return string[] the data for the event type search field options, the key
     *               will be the UID of the event type and the value will be the
     *               title of the event type, will be empty if no data could be
     *               found
     */
    protected function getEventTypeData(): array
    {
        $result = [];

        /** @var LegacyEvent $event */
        foreach ($this->seminarBag as $event) {
            $eventTypeUid = $event->getEventTypeUid();
            if ($eventTypeUid !== 0) {
                $eventTypeName = $event->getEventType();
                if (!isset($result[$eventTypeUid])) {
                    $result[$eventTypeUid] = $eventTypeName;
                }
            }
        }

        return $result;
    }

    /**
     * Gets the data for the place search field options.
     *
     * @return array<int, string> the data for the venue search field options;
     *         the key will be the UID of the place and the value will be the title of the place,
     *         will be empty if no data could be found
     */
    protected function getPlaceData(): array
    {
        if ($this->seminarBag->isEmpty()) {
            return [];
        }

        $result = [];
        $this->collectPlaces();

        /** @var Place $place */
        foreach ($this->places as $place) {
            $result[$place->getUid()] = $place->getTitle();
        }

        return $result;
    }

    /**
     * Gets the data for the city search field options.
     *
     * @return string[] the data for the city search field options; the key and the
     *               value will be the name of the city, will be empty if no
     *               data could be found
     */
    protected function getCityData(): array
    {
        if ($this->seminarBag->isEmpty()) {
            return [];
        }

        $result = [];
        $this->collectPlaces();

        /** @var Place $place */
        foreach ($this->places as $place) {
            $city = $place->getCity();
            $result[$city] = $city;
        }

        return $result;
    }

    /**
     * Compiles the possible values for date selector.
     *
     * @return array[] the first level contains day,
     *         month and year as key, the second level has the day, month or
     *         year value as value and key, will not be empty
     */
    private function createDateArray(): array
    {
        $result = ['day' => [], 'month' => [], 'year' => []];

        for ($day = 1; $day <= 31; $day++) {
            $result['day'][$day] = $day;
        }

        for ($month = 1; $month <= 12; $month++) {
            $result['month'][$month] = $month;
        }

        $currentYear = (int)date('Y');
        $targetYear = $currentYear + $this->getConfValueInteger('numberOfYearsInDateFilter', 's_listView');

        for ($year = $currentYear; $year < $targetYear; $year++) {
            $result['year'][$year] = $year;
        }

        return $result;
    }

    /**
     * Gets the data for the organizer search field options.
     *
     * @return string[] the data for the organizer search field options; the key
     *               will be the UID of the organizer and the value will be the
     *               name of the organizer, will be empty if no data could be
     *               found
     */
    private function getOrganizerData(): array
    {
        $result = [];

        /** @var LegacyEvent $event */
        foreach ($this->seminarBag as $event) {
            if ($event->hasOrganizers()) {
                /** @var LegacyOrganizer $organizer */
                foreach ($event->getOrganizerBag() as $organizer) {
                    $organizerUid = $organizer->getUid();
                    if (!isset($result[$organizerUid])) {
                        $result[$organizerUid] = $organizer->getName();
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Gets the data for the category search field options.
     *
     * @return string[] the data for the category search field options; the key
     *               will be the UID of the category and the value will be the
     *               name of the category, will be empty if no data could be
     *               found
     */
    private function getCategoryData(): array
    {
        $result = [];

        /** @var LegacyEvent $event */
        foreach ($this->seminarBag as $event) {
            if ($event->hasCategories()) {
                foreach ($event->getCategories() as $uid => $category) {
                    if (!isset($result[$uid])) {
                        $result[$uid] = $category['title'];
                    }
                }
            }
        }

        return $result;
    }
}
