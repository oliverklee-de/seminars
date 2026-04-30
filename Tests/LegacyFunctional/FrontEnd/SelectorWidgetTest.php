<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\FrontEnd;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\FrontEnd\SelectorWidget;
use OliverKlee\Seminars\Hooks\Interfaces\SeminarSelectorWidget;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\AbstractView
 * @covers \OliverKlee\Seminars\FrontEnd\SelectorWidget
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class SelectorWidgetTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private SelectorWidget $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework('tx_seminars');
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);

        $this->getLanguageService();

        $this->subject = new SelectorWidget(
            [
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
            ],
            $this->getFrontEndController()->cObj,
        );
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    //////////////////////
    // Utility functions
    //////////////////////

    private function getFrontEndController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    //////////////////////////////////////////
    // General tests concerning the fixture.
    //////////////////////////////////////////

    /**
     * @test
     */
    public function fixtureIsAFrontEndSelectorWidgetObject(): void
    {
        self::assertInstanceOf(SelectorWidget::class, $this->subject);
    }

    ///////////////////////
    // Tests for render()
    ///////////////////////

    /**
     * @test
     */
    public function renderWithAllSearchOptionsHiddenReturnsEmptyString(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', '');

        self::assertEquals(
            '',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledSearchWidgetContainsSearchingHints(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        self::assertStringContainsString(
            $this->translate('label_searching_hints'),
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledSearchWidgetContainsSubmitButton(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        self::assertStringContainsString(
            '<input type="submit" value="' .
            $this->translate('label_selector_submit') . '" />',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledSearchWidgetContainsResetButton(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        self::assertStringContainsString(
            '<input type="submit" value="' .
            $this->translate('label_selector_reset') . '"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledShowEmptyEntryInOptionListsContainsEmptyOption(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );
        $this->subject->setConfigurationValue(
            'showEmptyEntryInOptionLists',
            true,
        );

        self::assertStringContainsString(
            '<option value="0">' .
            $this->translate('label_selector_pleaseChoose') .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForTwoEnabledSearchPartsRendersBothSearchParts(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type,city',
        );

        $output = $this->subject->render();

        self::assertStringContainsString(
            $this->translate('label_event_type'),
            $output,
        );
        self::assertStringContainsString(
            $this->translate('label_city'),
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForEnabledSearchWidgetDoesNotHaveUnreplacedMarkers(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type,city,place,full_text_search,date,' .
            'age,organizer,price',
        );

        self::assertStringNotContainsString(
            '###',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledSearchWidgetCallsSeminarSelectorWidgetHook(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'city');

        $hook = $this->createMock(SeminarSelectorWidget::class);
        $hook->expects(self::once())->method('modifySelectorWidget')->with($this->subject, self::anything());

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][SeminarSelectorWidget::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->render();
    }

    /////////////////////////////////////////////
    // Test for removeDummyOptionFromFormData()
    /////////////////////////////////////////////

    /**
     * @test
     */
    public function removeDummyOptionFromFormDataRemovesDummyOptionAtBeginningOfArray(): void
    {
        self::assertEquals(
            ['CH', 'DE'],
            SelectorWidget::removeDummyOptionFromFormData(
                [0, 'CH', 'DE'],
            ),
        );
    }

    /**
     * @test
     */
    public function removeDummyOptionFromFormDataRemovesDummyOptionInMiddleOfArray(): void
    {
        self::assertEquals(
            ['CH', 'DE'],
            SelectorWidget::removeDummyOptionFromFormData(
                ['CH', 0, 'DE'],
            ),
        );
    }

    /**
     * @test
     */
    public function removeDummyOptionFromFormDataWithEmptyFormDataReturnsEmptyArray(): void
    {
        self::assertEquals(
            [],
            SelectorWidget::removeDummyOptionFromFormData(
                [],
            ),
        );
    }

    ////////////////////////////////////////////////////////////////
    // Tests concerning the rendering of the event_type option box
    ////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForEventTypeHiddenInConfigurationHidesEventTypeSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_EVENT_TYPE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledEventTypeCanContainEventTypeOption(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $eventTypeTitle = 'test event type';
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => $eventTypeTitle],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid],
        );

        self::assertStringContainsString(
            '<option value="' . $eventTypeUid . '">' . $eventTypeTitle .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledEventTypeHtmlSpecialCharsTheEventTypeTitle(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $eventTypeTitle = '< Test >';
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => $eventTypeTitle],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid],
        );

        self::assertStringContainsString(
            '<option value="' . $eventTypeUid . '">' .
            \htmlspecialchars($eventTypeTitle, ENT_QUOTES | ENT_HTML5) .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledEventTypePreselectsSelectedValue(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $eventTypeTitle = 'test event type';
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => $eventTypeTitle],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid],
        );

        $this->subject->piVars['event_type'][] = (string)$eventTypeUid;

        self::assertStringContainsString(
            $eventTypeUid . '" selected="selected">' . $eventTypeTitle .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledEventTypeCanPreselectTwoValues(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $eventTypeTitle = 'foo';
        $eventTypeTitle2 = 'bar';
        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => $eventTypeTitle],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid],
        );

        $eventTypeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => $eventTypeTitle2],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid2],
        );

        $this->subject->piVars['event_type'][] = (string)$eventTypeUid;
        $this->subject->piVars['event_type'][] = (string)$eventTypeUid2;

        $output = $this->subject->render();

        self::assertStringContainsString(
            $eventTypeUid . '" selected="selected">' . $eventTypeTitle .
            '</option>',
            $output,
        );
        self::assertStringContainsString(
            $eventTypeUid2 . '" selected="selected">' . $eventTypeTitle2 .
            '</option>',
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForEnabledEventTypeContainsSelectorForEventTypes(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[event_type][]" ' .
            'id="tx_seminars_pi1-event_type" size="5" multiple="multiple">',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function itemsInSearchBoxAreSortedAlphabetically(): void
    {
        $subject = $this
            ->getMockBuilder(SelectorWidget::class)
            ->onlyMethods(
                [
                    'hasSearchField',
                    'getEventTypeData',
                    'getPlaceData',
                    'getCityData',
                ],
            )->setConstructorArgs(
                [
                    [
                        'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
                        'displaySearchFormFields' => 'event_type',
                    ],
                    $this->getFrontEndController()->cObj,
                ],
            )->getMock();
        $subject
            ->method('hasSearchField')
            ->willReturn(true);
        $subject
            ->expects(self::once())->method('getEventTypeData')
            ->willReturn([1 => 'Foo', 2 => 'Bar']);
        $subject
            ->method('getPlaceData')
            ->willReturn([]);
        $subject
            ->method('getCityData')
            ->willReturn([]);

        $output = $subject->render();
        self::assertTrue(
            strpos($output, 'Bar') < strpos($output, 'Foo'),
        );
    }

    ///////////////////////////////////////////////////////////
    // Tests concerning the rendering of the place option box
    ///////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForDisabledPlaceOptionsHidesPlaceSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_PLACE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPlaceOptionsContainsPlaceOptions(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'place',
        );
        $placeTitle = 'test place';
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => $placeTitle],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid,
            'place',
        );

        self::assertStringContainsString(
            '<option value="' . $placeUid . '">' . $placeTitle . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPlaceOptionsHtmlSpecialCharsThePlaceTitle(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'place',
        );
        $placeTitle = '<>';
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => $placeTitle],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid,
            'place',
        );

        self::assertStringContainsString(
            '<option value="' . $placeUid . '">' .
            \htmlspecialchars($placeTitle, ENT_QUOTES | ENT_HTML5) . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPlaceOptionsContainsSelectorForPlaces(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'place',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[place][]" ' .
            'id="tx_seminars_pi1-place" size="5" multiple="multiple">',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPlaceOptionsCanPreselectPlaceOption(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'place',
        );
        $placeTitle = 'test place';
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => $placeTitle],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid,
            'place',
        );

        $this->subject->piVars['place'][] = (string)$placeUid;

        self::assertStringContainsString(
            '<option value="' . $placeUid . '" selected="selected">' . $placeTitle . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPlaceOptionsCanPreselectMultiplePlaceOptions(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'place',
        );
        $placeTitle = 'foo';
        $placeTitle2 = 'bar';
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => $placeTitle],
        );
        $placeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['title' => $placeTitle2],
        );

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid,
            'place',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid2,
            'place',
        );

        $this->subject->piVars['place'][] = (string)$placeUid;
        $this->subject->piVars['place'][] = (string)$placeUid2;

        $output = $this->subject->render();

        self::assertStringContainsString(
            '<option value="' . $placeUid . '" selected="selected">' .
            $placeTitle . '</option>',
            $output,
        );
        self::assertStringContainsString(
            '<option value="' . $placeUid2 . '" selected="selected">' .
            $placeTitle2 . '</option>',
            $output,
        );
    }

    //////////////////////////////////////////////////////////
    // Tests concerning the rendering of the city option box
    //////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForDisabledCityOptionsHidesCitySubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'place',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_CITY'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCityOptionsCanContainCityOption(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'city');

        $cityName = 'test city';
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => $cityName],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid,
            'place',
        );

        self::assertStringContainsString(
            '<option value="' . $cityName . '">' . $cityName . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCityOptionsCanContainTwoCityOptiona(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'city');
        $cityName1 = 'foo city';
        $cityName2 = 'bar city';

        $placeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => $cityName1],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid1,
            $placeUid1,
            'place',
        );

        $placeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => $cityName2],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid2,
            $placeUid2,
            'place',
        );

        $output = $this->subject->render();

        self::assertStringContainsString(
            '<option value="' . $cityName1 . '">' . $cityName1 . '</option>',
            $output,
        );
        self::assertStringContainsString(
            '<option value="' . $cityName2 . '">' . $cityName2 . '</option>',
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCityOptionsCanPreselectCityOption(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'city');
        $cityTitle = 'test city';
        $placeUid = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => $cityTitle],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $placeUid,
            'place',
        );

        $this->subject->piVars['city'][] = $cityTitle;

        self::assertStringContainsString(
            '<option value="' . $cityTitle . '" selected="selected">' .
            $cityTitle . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCityOptionsCanPreselectMultipleCityOptions(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'city');
        $cityTitle1 = 'bar city';
        $cityTitle2 = 'foo city';

        $placeUid1 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => $cityTitle1],
        );
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid1,
            $placeUid1,
            'place',
        );

        $placeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_sites',
            ['city' => $cityTitle2],
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid2,
            $placeUid2,
            'place',
        );

        $this->subject->piVars['city'][] = $cityTitle1;
        $this->subject->piVars['city'][] = $cityTitle2;

        $output = $this->subject->render();

        self::assertStringContainsString(
            '<option value="' . $cityTitle1 . '" selected="selected">' .
            $cityTitle1 . '</option>',
            $output,
        );
        self::assertStringContainsString(
            '<option value="' . $cityTitle2 . '" selected="selected">' .
            $cityTitle2 . '</option>',
            $output,
        );
    }

    ////////////////////////////////////////////////
    // Tests concerning the full text search input
    ////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForDisabledFullTextSearchHidesFullTextSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_TEXT'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledFullTextSearchContainsFullTextSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'full_text_search',
        );

        $this->subject->render();

        self::assertTrue(
            $this->subject->isSubpartVisible('SEARCH_PART_TEXT'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledFullTextSearchCanFillSearchedWordIntoTextbox(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'full_text_search',
        );

        $searchWord = 'foo bar';
        $this->subject->piVars['sword'] = $searchWord;

        self::assertStringContainsString(
            $searchWord,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledFullTextSearchHtmlSpecialCharsSearchedWord(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'full_text_search',
        );

        $searchWord = '<>';
        $this->subject->piVars['sword'] = $searchWord;

        self::assertStringContainsString(
            \htmlspecialchars($searchWord, ENT_QUOTES | ENT_HTML5),
            $this->subject->render(),
        );
    }

    /////////////////////////////////////
    // Tests concerning the date search
    /////////////////////////////////////

    /**
     * @test
     */
    public function renderForDisabledDateSearchHidesDateSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_DATE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchContainsDayFromDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[from_day]"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchContainsMonthFromDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[from_month]"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchContainsYearFromDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[from_year]"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchContainsDayToDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[to_day]"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchContainsMonthToDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[to_month]"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchContainsYearToDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<select name="tx_seminars_pi1[to_year]"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchAndNumberOfYearsInDateFilterSetToTwoContainsTwoYearsInDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );
        $this->subject->setConfigurationValue(
            'numberOfYearsInDateFilter',
            2,
        );

        $output = $this->subject->render();
        $currentYear = (int)date('Y');

        self::assertStringContainsString(
            '<option value="' . $currentYear . '">' . $currentYear . '</option>',
            $output,
        );
        self::assertStringContainsString(
            '<option value="' . ($currentYear + 1) . '">' . ($currentYear + 1) . '</option>',
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForEnabledDateSearchAddsAnEmptyOptionToTheDropDown(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        self::assertStringContainsString(
            '<option value="0">&nbsp;</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForSentToMonthValuePreselectsToMonthValue(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );

        $this->subject->piVars['to_month'] = 5;

        self::assertStringContainsString(
            '<option value="5" selected="selected">5</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForSentFromDatePreselectsFromDateValues(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );
        $this->subject->setConfigurationValue(
            'numberOfYearsInDateFilter',
            2,
        );

        $thisYear = date('Y');
        $this->subject->piVars['from_day'] = 2;
        $this->subject->piVars['from_month'] = 5;
        $this->subject->piVars['from_year'] = $thisYear;

        $output = $this->subject->render();

        self::assertStringContainsString(
            '<option value="2" selected="selected">2</option>',
            $output,
        );
        self::assertStringContainsString(
            '<option value="5" selected="selected">5</option>',
            $output,
        );
        self::assertStringContainsString(
            '<option value="' . $thisYear . '" selected="selected">' .
            $thisYear . '</option>',
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForNoSentDatePreselectsNoDateValues(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );
        $this->subject->setConfigurationValue(
            'numberOfYearsInDateFilter',
            2,
        );

        self::assertStringNotContainsString(
            'selected="selected"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForBothSentDatesZeroPreselectsNoDateValues(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'date',
        );
        $this->subject->setConfigurationValue(
            'numberOfYearsInDateFilter',
            2,
        );

        $this->subject->piVars['from_day'] = 0;
        $this->subject->piVars['from_month'] = 0;
        $this->subject->piVars['from_year'] = 0;
        $this->subject->piVars['to_day'] = 0;
        $this->subject->piVars['to_month'] = 0;
        $this->subject->piVars['to_year'] = 0;

        self::assertStringNotContainsString(
            'selected="selected"',
            $this->subject->render(),
        );
    }

    ///////////////////////////////////////////////
    // Tests concerning the event type limitation
    ///////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForEventTypeLimitedAndEventTypeDisplayedShowsTheLimitedEventType(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'foo_type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid],
        );

        $this->subject->setConfigurationValue(
            'limitListViewToEventTypes',
            $eventTypeUid,
        );

        self::assertStringContainsString(
            'foo_type',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEventTypeLimitedAndEventTypeDisplayedHidesEventTypeNotLimited(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'event_type',
        );

        $eventTypeUid = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'foo_type'],
        );
        $eventTypeUid2 = $this->testingFramework->createRecord(
            'tx_seminars_event_types',
            ['title' => 'bar_type'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['event_type' => $eventTypeUid2],
        );

        $this->subject->setConfigurationValue(
            'limitListViewToEventTypes',
            $eventTypeUid,
        );

        self::assertStringNotContainsString(
            'bar_type',
            $this->subject->render(),
        );
    }

    ////////////////////////////////////////////////
    // Tests concerning the organizer search widget
    ////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForOrganizersLimitedAndOrganizerDisplayedShowsTheLimitedOrganizers(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'Organizer Foo'],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->testingFramework->createRecord('tx_seminars_seminars'),
            $organizerUid,
            'organizers',
        );

        $this->subject->setConfigurationValue(
            'limitListViewToOrganizers',
            $organizerUid,
        );

        self::assertStringContainsString(
            'Organizer Foo',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForOrganizerLimitedAndOrganizersDisplayedHidesTheOrganizersWhichAreNotTheLimitedOnes(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => 'Organizer Bar'],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->testingFramework->createRecord('tx_seminars_seminars'),
            $organizerUid1,
            'organizers',
        );

        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
        );

        $this->subject->setConfigurationValue(
            'limitListViewToOrganizers',
            $organizerUid2,
        );

        self::assertStringNotContainsString(
            'Organizer Bar',
            $this->subject->render(),
        );
    }

    // Tests concerning the category search widget

    /**
     * @test
     */
    public function renderForCategoriesLimitedAndCategoryDisplayedShowsTheLimitedCategories(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');

        $categoryUid = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => 'Category Foo']);
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->testingFramework->createRecord('tx_seminars_seminars'),
            $categoryUid,
            'categories',
        );

        $this->subject->setConfigurationValue('limitListViewToCategories', $categoryUid);

        self::assertStringContainsString(
            'Category Foo',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForCategoryLimitedAndCategoriesDisplayedHidesTheCategoriesWhichAreNotTheLimitedOnes(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');

        $categoryUid1 = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => 'Category Bar']);
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->testingFramework->createRecord('tx_seminars_seminars'),
            $categoryUid1,
            'categories',
        );

        $categoryUid2 = $this->testingFramework->createRecord('tx_seminars_categories');

        $this->subject->setConfigurationValue('limitListViewToCategories', $categoryUid2);

        self::assertStringNotContainsString(
            'Category Bar',
            $this->subject->render(),
        );
    }

    //////////////////////////////////////////
    // Tests concerning the age search input
    //////////////////////////////////////////

    /**
     * @test
     */
    public function renderForDisabledAgeSearchHidesAgeSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_AGE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledAgeSearchContainsAgeSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'age',
        );

        $this->subject->render();

        self::assertTrue(
            $this->subject->isSubpartVisible('SEARCH_PART_AGE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledAgeSearchCanFillSearchedAgeIntoTextbox(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'age',
        );

        $searchedAge = 15;
        $this->subject->piVars['age'] = $searchedAge;

        self::assertStringContainsString(
            (string)$searchedAge,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledAgeSearchAndAgeValueZeroDoesNotShowAgeValueZero(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'age',
        );

        $searchedAge = 0;
        $this->subject->piVars['age'] = $searchedAge;

        self::assertStringNotContainsString(
            'age]" value="' . $searchedAge . '"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledAgeSearchDoesNotIncludeNonIntegerAgeAsValue(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'age',
        );

        $searchedAge = 'Hallo';
        $this->subject->piVars['age'] = $searchedAge;

        self::assertStringNotContainsString(
            $searchedAge,
            $this->subject->render(),
        );
    }

    ////////////////////////////////////////////////////////////////
    // Tests concerning the rendering of the organizer option box
    ////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForOrganizerHiddenInConfigurationHidesOrganizerSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_ORGANIZER'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledOrganizerContainsOrganizerOption(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $organizerName = 'test organizer';
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => $organizerName],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        self::assertStringContainsString(
            '<option value="' . $organizerUid . '">' . $organizerName .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledOrganizerHtmlSpecialCharsTheOrganizersName(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $organizerName = '< Organizer Name >';
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => $organizerName],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        self::assertStringContainsString(
            '<option value="' . $organizerUid . '">' .
            \htmlspecialchars($organizerName, ENT_QUOTES | ENT_HTML5) .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledOrganizerPreselectsSelectedValue(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $organizerName = 'Organizer Name';
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => $organizerName],
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers',
        );

        $this->subject->piVars['organizer'][] = (string)$organizerUid;

        self::assertStringContainsString(
            $organizerUid . '" selected="selected">' . $organizerName .
            '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledOrganizerCanPreselectTwoValues(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');

        $organizerName1 = 'Organizer 1';
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => $organizerName1],
        );
        $organizerName2 = 'Organizer 2';
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            ['title' => $organizerName2],
        );

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid1,
            'organizers',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid2,
            'organizers',
        );

        $this->subject->piVars['organizer'][] = (string)$organizerUid1;
        $this->subject->piVars['organizer'][] = (string)$organizerUid2;

        $output = $this->subject->render();

        self::assertStringContainsString(
            $organizerUid1 . '" selected="selected">' . $organizerName1 .
            '</option>',
            $output,
        );
        self::assertStringContainsString(
            $organizerUid2 . '" selected="selected">' . $organizerName2 .
            '</option>',
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForEnabledOrganizerContainsOrganizersSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'organizer',
        );

        $this->subject->render();

        self::assertTrue(
            $this->subject->isSubpartVisible('SEARCH_PART_ORGANIZER'),
        );
    }

    // Tests concerning the rendering of the category option box

    /**
     * @test
     */
    public function renderForCategoryHiddenInConfigurationHidesCategorySubpart(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'city');

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_ORGANIZER'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCategoryContainsCategoryOption(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');

        $categoryName = 'test category';
        $categoryUid = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => $categoryName]);
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $categoryUid,
            'categories',
        );

        self::assertStringContainsString(
            '<option value="' . $categoryUid . '">' . $categoryName . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCategoryHtmlSpecialCharsTheCategoriesName(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');

        $categoryName = '< Category Name >';
        $categoryUid = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => $categoryName]);
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $categoryUid,
            'categories',
        );

        self::assertStringContainsString(
            '<option value="' . $categoryUid . '">' . \htmlspecialchars(
                $categoryName,
                ENT_QUOTES | ENT_HTML5,
            ) . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCategoryPreselectsSelectedValue(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');

        $categoryName = 'Category Name';
        $categoryUid = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => $categoryName]);
        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $categoryUid,
            'categories',
        );

        $this->subject->piVars['categories'][] = (string)$categoryUid;

        self::assertStringContainsString(
            $categoryUid . '" selected="selected">' . $categoryName . '</option>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCategoryCanPreselectTwoValues(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');

        $eventUid = $this->testingFramework->createRecord('tx_seminars_seminars');

        $categoryName1 = 'Category 1';
        $categoryUid1 = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => $categoryName1]);
        $categoryName2 = 'Category 2';
        $categoryUid2 = $this->testingFramework->createRecord('tx_seminars_categories', ['title' => $categoryName2]);

        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $categoryUid1,
            'categories',
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $categoryUid2,
            'categories',
        );

        $this->subject->piVars['categories'][] = (string)$categoryUid1;
        $this->subject->piVars['categories'][] = (string)$categoryUid2;

        $output = $this->subject->render();

        self::assertStringContainsString(
            $categoryUid1 . '" selected="selected">' . $categoryName1 . '</option>',
            $output,
        );
        self::assertStringContainsString(
            $categoryUid2 . '" selected="selected">' . $categoryName2 . '</option>',
            $output,
        );
    }

    /**
     * @test
     */
    public function renderForEnabledCategoryContainsCategoriesSubpart(): void
    {
        $this->subject->setConfigurationValue('displaySearchFormFields', 'categories');
        $this->subject->render();

        self::assertTrue(
            $this->subject->isSubpartVisible('SEARCH_PART_CATEGORIES'),
        );
    }

    ////////////////////////////////////////////
    // Tests concerning the price search input
    ////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForDisabledPriceSearchHidesPriceSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'city',
        );

        $this->subject->render();

        self::assertFalse(
            $this->subject->isSubpartVisible('SEARCH_PART_PRICE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchContainsPriceSearchSubpart(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $this->subject->render();

        self::assertTrue(
            $this->subject->isSubpartVisible('SEARCH_PART_PRICE'),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchCanFillSearchedPriceFromIntoTextbox(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $priceFrom = 10;
        $this->subject->piVars['price_from'] = $priceFrom;

        self::assertStringContainsString(
            (string)$priceFrom,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchCanFillSearchedPriceToIntoTextbox(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $priceTo = 50;
        $this->subject->piVars['price_to'] = $priceTo;

        self::assertStringContainsString(
            (string)$priceTo,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchAndPriceFromZeroDoesNotShowZeroForPriceFrom(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $priceFrom = 0;
        $this->subject->piVars['price_from'] = $priceFrom;

        self::assertStringNotContainsString(
            'price_from]" value="' . $priceFrom . '"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchAndPriceToZeroDoesNotShowZeroForPriceTo(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $priceTo = 0;
        $this->subject->piVars['price_to'] = $priceTo;

        self::assertStringNotContainsString(
            'price_to]" value="' . $priceTo . '"',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchDoesNotIncludeNonIntegerPriceFromAsValue(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $priceFrom = 'Hallo';
        $this->subject->piVars['price_from'] = $priceFrom;

        self::assertStringNotContainsString(
            $priceFrom,
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForEnabledPriceSearchDoesNotIncludeNonIntegerPriceToAsValue(): void
    {
        $this->subject->setConfigurationValue(
            'displaySearchFormFields',
            'price',
        );

        $priceTo = 'Hallo';
        $this->subject->piVars['price_from'] = $priceTo;

        self::assertStringNotContainsString(
            $priceTo,
            $this->subject->render(),
        );
    }
}
