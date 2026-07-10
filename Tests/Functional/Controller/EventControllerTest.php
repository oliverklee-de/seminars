<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Controller;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\EventController
 */
final class EventControllerTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/EventController';

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
        'typo3/cms-fluid-styled-content',
    ];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/seminars/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Sites/SiteStructure.csv');
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:seminars/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:seminars/Configuration/TypoScript/setup.typoscript',
                'EXT:seminars/Tests/Functional/Controller/Fixtures/TypoScript/Setup/Rendering.typoscript',
                'EXT:seminars/Tests/Functional/Controller/Fixtures/TypoScript/Setup/PluginConfiguration.typoscript',
            ],
        ]);
    }

    /**
     * @test
     */
    public function archiveActionForNoEventsShowsMessage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedMessage = LocalizationUtility::translate('plugin.eventArchive.message.noEventsFound', 'seminars');
        self::assertIsString($expectedMessage);
        self::assertStringContainsString($expectedMessage, $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersTitleOfPastSingleEventInStorageFolder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersTitleOfPastSingleEventInSubfolderOfStorageFolder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventInSubfolder.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function archiveActionIgnoresEventInOtherFolder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventInOtherFolder.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function archiveActionForSingleEventLinksEventTitleToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = '#<a [^>]*href="/event-single-view/1"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionForSingleEventLinksEventTitleWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventArchive.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionForSingleEventLinksSingleViewLinkTextToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventArchive.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $expected = '#<a [^>]*href="/event-single-view/1"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionForSingleEventLinksSingleViewLinkTextWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventArchive.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventArchive.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionDoesDoesNotRenderFutureSingleEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function archiveActionForEventDateRendersTitleOfTopic(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function archiveActionForEventDateLinksEventTitleFromTopicToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = '#<a [^>]*href="/event-single-view/2"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionForEventDateLinksEventTitleFromTopicWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventArchive.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionForEventDateLinksSingleViewLinkTextToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventArchive.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $expected = '#<a [^>]*href="/event-single-view/2"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionForEventDateLinksSingleViewLinkTextWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventArchive.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventArchive.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersDateOfSingleDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/SingleDayPastEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-03', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersStartAndEndOfMultiDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/MultiDayPastEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-02–2024-11-03', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersOrganizersOfEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithTwoOrganizers.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Rainbow Recitals,', $html);
        self::assertStringContainsString('Fortran Foundation', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersCityOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithOneVenue.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Bonn', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersAllCitiesOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithTwoVenuesInDifferentCities.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Bonn', $html);
        self::assertStringContainsString('Köln', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersOnlyOneCityForMultipleVenuesInSameCity(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithTwoVenuesInSameCity.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Bonn', $html);
        self::assertStringNotContainsString('Bonn,', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersTitleOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithOneVenue.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersAllTitlesOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithTwoVenuesInDifferentCities.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
        self::assertStringContainsString('Premier Inn', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function eventFormatForArchiveActionDataProvider(): array
    {
        return [
            'on-site' => ['PastOnSiteEvent.csv', '0'],
            'hybrid' => ['PastHybridEvent.csv', '1'],
            'online' => ['PastOnlineEvent.csv', '2'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider eventFormatForArchiveActionDataProvider
     */
    public function archiveActionRendersEventFormat(string $fixtureFile, string $labelKey): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/' . $fixtureFile);

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $keyPrefix = 'plugin.eventArchive.events.property.eventFormat.';
        $expected = LocalizationUtility::translate($keyPrefix . $labelKey, 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersEventType(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithEventType.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('workshop', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersCategories(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithTwoCategories.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('intense', $html);
        self::assertStringContainsString('laid-back', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersSpeakers(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastEventWithTwoSpeakers.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Sally Speaker,', $html);
        self::assertStringContainsString('Sam Speaker', $html);
    }

    /**
     * @test
     */
    public function archiveActionRendersEventUid(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/EventArchiveContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $labelWithPlaceholder = LocalizationUtility::translate(
            'plugin.eventArchive.events.property.eventUid.number',
            'seminars',
        );
        self::assertIsString($labelWithPlaceholder);
        $expected = sprintf($labelWithPlaceholder, 1);
        self::assertSame('#1', $expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForNoEventsShowsMessage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedMessage = LocalizationUtility::translate('plugin.eventArchive.message.noEventsFound', 'seminars');
        self::assertIsString($expectedMessage);
        self::assertStringContainsString($expectedMessage, $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersTitleOfFutureSingleEventInStorageFolder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersTitleOfFutureSingleEventInSubfolderOfStorageFolder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventInSubfolder.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function outlookActionIgnoresEventInOtherFolder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventInOtherFolder.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function outlookActionForSingleEventLinksEventTitleToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = '#<a [^>]*href="/event-single-view/1"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForSingleEventLinksEventTitleWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForSingleEventLinksSingleViewLinkTextToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventOutlook.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $expected = '#<a [^>]*href="/event-single-view/1"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForSingleEventLinksSingleViewLinkTextWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventOutlook.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionDoesDoesNotRenderPastSingleEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/PastSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventDateRendersTitleOfTopic(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Extension Development with Extbase and Fluid', $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventDateLinksEventTitleFromTopicToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = '#<a [^>]*href="/event-single-view/2"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventDateLinksEventTitleFromTopicWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*Extension Development#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventDateLinksSingleViewLinkTextToSingleView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventOutlook.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $expected = '#<a [^>]*href="/event-single-view/2"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventDateLinksSingleViewLinkTextWithAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $linkText = LocalizationUtility::translate('plugin.eventOutlook.events.property.singleViewLink', 'seminars');
        self::assertIsString($linkText);
        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.singleViewLink.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>\\s*' . $linkText . '#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersDateOfSingleDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/SingleDayFutureEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2039-12-01', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersStartAndEndOfMultiDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/MultiDayFutureEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2039-12-01–2039-12-02', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersDateOfEventDate(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/SingleDayFutureEventDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2039-12-01', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersStartAndEndOfMultiDayEventDate(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/MultiDayFutureEventDateWithTopic.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2039-12-01–2039-12-02', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersOrganizersOfEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithTwoOrganizers.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Rainbow Recitals,', $html);
        self::assertStringContainsString('Fortran Foundation', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersCityOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithOneVenue.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Bonn', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersAllCitiesOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithTwoVenuesInDifferentCities.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Bonn', $html);
        self::assertStringContainsString('Köln', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersOnlyOneCityForMultipleVenuesInSameCity(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithTwoVenuesInSameCity.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Bonn', $html);
        self::assertStringNotContainsString('Bonn,', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersTitleOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithOneVenue.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersAllTitlesOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithTwoVenuesInDifferentCities.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
        self::assertStringContainsString('Premier Inn', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function eventFormatForOutlookActionDataProvider(): array
    {
        return [
            'on-site' => ['FutureOnSiteEvent.csv', '0'],
            'hybrid' => ['FutureHybridEvent.csv', '1'],
            'online' => ['FutureOnlineEvent.csv', '2'],
        ];
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function eventFormatForOutlookActionForEventDateDataProvider(): array
    {
        return [
            'on-site' => ['FutureOnSiteEventDateWithTopic.csv', '0'],
            'hybrid' => ['FutureHybridEventDateWithTopic.csv', '1'],
            'online' => ['FutureOnlineEventDateWithTopic.csv', '2'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider eventFormatForOutlookActionDataProvider
     */
    public function outlookActionRendersEventFormat(string $fixtureFile, string $labelKey): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/' . $fixtureFile);

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $keyPrefix = 'plugin.eventOutlook.events.property.eventFormat.';
        $expected = LocalizationUtility::translate($keyPrefix . $labelKey, 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     *
     * @dataProvider eventFormatForOutlookActionForEventDateDataProvider
     */
    public function outlookActionForEventDateRendersEventFormat(string $fixtureFile, string $labelKey): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/' . $fixtureFile);

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $keyPrefix = 'plugin.eventOutlook.events.property.eventFormat.';
        $expected = LocalizationUtility::translate($keyPrefix . $labelKey, 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersCategories(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithTwoCategories.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('intense', $html);
        self::assertStringContainsString('laid-back', $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventDateRendersCategories(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventDateWithTwoCategories.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('intense', $html);
        self::assertStringContainsString('laid-back', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersSpeakers(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithTwoSpeakers.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Sally Speaker,', $html);
        self::assertStringContainsString('Sam Speaker', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersEventUid(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $labelWithPlaceholder = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.eventUid.number',
            'seminars',
        );
        self::assertIsString($labelWithPlaceholder);
        $expected = sprintf($labelWithPlaceholder, 1);
        self::assertSame('#1', $expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithPriceRendersPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithStandardPrice.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('499.50 €', $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithoutPriceDoesNotRenderPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithoutStandardPrice.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('€', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersEventType(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithEventType.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('workshop', $html);
    }

    /**
     * @test
     */
    public function outlookActionRendersEventTypeOfEventDate(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithEventDateType.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('workshop', $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithUnlimitedSeatsRendersAvailable(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithUnlimitedSeats.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $available = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.vacancies.available',
            'seminars',
        );
        self::assertIsString($available);
        self::assertStringContainsString($available, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithMoreThanEnoughVacanciesRendersAvailable(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithMoreThanEnoughVacancies.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $available = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.vacancies.available',
            'seminars',
        );
        self::assertIsString($available);
        self::assertStringContainsString($available, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithExactlyEnoughVacanciesRendersAvailable(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithExactlyEnoughVacancies.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $available = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.vacancies.available',
            'seminars',
        );
        self::assertIsString($available);
        self::assertStringContainsString($available, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithLessThanEnoughVacanciesRendersFewLeft(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithLessThanEnoughVacancies.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $fewLeft = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.vacancies.fewLeft',
            'seminars',
        );
        self::assertIsString($fewLeft);
        self::assertStringContainsString($fewLeft, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithOneVacancyRendersFewLeft(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $fewLeft = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.vacancies.fewLeft',
            'seminars',
        );
        self::assertIsString($fewLeft);
        self::assertStringContainsString($fewLeft, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithNoVacancyRendersFullyBooked(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithNoVacancies.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $fullyBooked = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.vacancies.fullyBooked',
            'seminars',
        );
        self::assertIsString($fullyBooked);
        self::assertStringContainsString($fullyBooked, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithOneVacancyRendersLinkToRegistrationForEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = 'href="/registration?tx_seminars_eventregistration%5Baction%5D=checkPrerequisites&amp;'
            . 'tx_seminars_eventregistration%5Bcontroller%5D=EventRegistration&amp;'
            . 'tx_seminars_eventregistration%5Bevent%5D=1';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithOneVacancyRendersRegistrationLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedMessage = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.registration.register',
            'seminars',
        );
        self::assertIsString($expectedMessage);
        self::assertStringContainsString($expectedMessage, $html);
    }

    /**
     * @test
     */
    public function outlookActionForEventWithOneVacancyRendersLinkWithRegistrationLabelAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.registration.register.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForFutureEventWithNoVacanciesAndWaitingListRendersLinkToRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithNoVacanciesAndWaitingList.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = 'href="/registration?tx_seminars_eventregistration%5Baction%5D=checkPrerequisites&amp;'
            . 'tx_seminars_eventregistration%5Bcontroller%5D=EventRegistration&amp;'
            . 'tx_seminars_eventregistration%5Bevent%5D=1';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForFutureEventWithNoVacanciesAndWaitingListRendersWaitingListLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithNoVacanciesAndWaitingList.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedMessage = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.registration.waitingList',
            'seminars',
        );
        self::assertIsString($expectedMessage);
        self::assertStringContainsString($expectedMessage, $html);
    }

    /**
     * @test
     */
    public function outlookActionForFutureEventWithNoVacanciesAndWaitingListRendersLinkWithWaitingListAriaLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithNoVacanciesAndWaitingList.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $ariaLabel = LocalizationUtility::translate(
            'plugin.eventOutlook.events.property.registration.waitingList.ariaLabel',
            'seminars',
            ['Extension Development with Extbase and Fluid'],
        );
        self::assertIsString($ariaLabel);
        $encodedLabel = \htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5);
        $expected = '#<a [^>]*aria-label="' . $encodedLabel . '"[^>]*>#s';
        self::assertMatchesRegularExpression($expected, $html);
    }

    /**
     * @test
     */
    public function outlookActionForFutureEventWithNoVacanciesAndNoWaitingListDoesNotRenderLinkToRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithNoVacancies.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('href="/registration', $html);
    }

    /**
     * @test
     */
    public function outlookActionForFutureEventWithVacanciesAndDeadlineOverDoesNotRenderLinkToRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/EventOutlookContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/outlookAction/FutureEventWithVacanciesAndDeadlineOver.csv');

        $request = (new InternalRequest())->withPageId(1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('href="/registration', $html);
    }

    /**
     * @test
     */
    public function showActionRendersTitleOfFutureSingleEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureSingleEvent.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertMatchesRegularExpression('#<h1>\\s*Extension Development#s', $html);
    }

    /**
     * @test
     */
    public function showActionRendersTitleOfPastSingleEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastSingleEvent.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertMatchesRegularExpression('#<h1>\\s*Extension Development#s', $html);
    }

    /**
     * @test
     */
    public function showActionRendersDisplayTitleOfEventDate(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureDateWithTopic.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertMatchesRegularExpression('#<h1>\\s*Extension Development#s', $html);
        self::assertStringNotContainsString('date record', $html);
    }

    /**
     * @test
     */
    public function showActionRendersDisplayTitleOfEventTopic(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Topic.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertMatchesRegularExpression('#<h1>\\s*Extension Development#s', $html);
    }

    /**
     * @test
     */
    public function showActionRendersEventType(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithEventType.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('workshop', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function singleDayEventDataProvider(): array
    {
        return [
            'without time slots' => ['SingleDayPastEvent.csv'],
            'with time slots' => ['SingleDayPastEventWithTimeSlots.csv'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider singleDayEventDataProvider
     */
    public function showActionRendersDateOfSingleDayEvent(string $csvDataSet): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvDataSet);

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-03', $html);
    }

    /**
     * @test
     */
    public function showActionRendersDateOfSingleDayEventOnlyOnce(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/SingleDayPastEvent.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertSame(1, \substr_count($html, '2024-11-03'));
    }

    /**
     * @test
     */
    public function showActionRendersStartAndEndOfMultiDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/archiveAction/MultiDayPastEvent.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-02–2024-11-03', $html);
    }

    /**
     * @test
     *
     * @dataProvider singleDayEventDataProvider
     */
    public function showActionRendersStartAndEndTimeOfSingleDayEvent(string $csvDataSet): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvDataSet);

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('09:00–17:00', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function multiDayEventDataProvider(): array
    {
        return [
            'without time slots' => ['MultiDayPastEvent.csv'],
            'with time slots' => ['MultiDayPastEventWithTimeSlots.csv'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider multiDayEventDataProvider
     */
    public function showActionRendersStartDateAndTimeOfMultiDayEvent(string $csvDataSet): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvDataSet);

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-02 09:00', $html);
    }

    /**
     * @test
     *
     * @dataProvider multiDayEventDataProvider
     */
    public function showActionRendersEndDateAndTimeOfMultiDayEvent(string $csvDataSet): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvDataSet);

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-03 17:00', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotTimesOfSingleDayPastEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/SingleDayPastEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedTimeWithUnit = LocalizationUtility::translate('timeWithUnit', 'seminars', ['09:00–09:00']);
        self::assertIsString($expectedTimeWithUnit);
        self::assertStringContainsString($expectedTimeWithUnit, $html);

        $expectedTimeWithUnit2 = LocalizationUtility::translate('timeWithUnit', 'seminars', ['09:00–17:00']);
        self::assertIsString($expectedTimeWithUnit2);
        self::assertStringContainsString($expectedTimeWithUnit2, $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotsDateOfSingleDayPastEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/SingleDayPastEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-03', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotsDatesOfMultiDayPastEventWithTimeSlots(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/MultiDayPastEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('2024-11-02', $html);
        self::assertStringContainsString('2024-11-03', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotsTimesOfMultiDayPastEventWithTimeSlots(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/MultiDayPastEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedTimeWithUnit = LocalizationUtility::translate('timeWithUnit', 'seminars', ['09:00–17:00']);
        self::assertIsString($expectedTimeWithUnit);
        self::assertStringContainsString($expectedTimeWithUnit, $html);

        $expectedTimeWithUnit2 = LocalizationUtility::translate('timeWithUnit', 'seminars', ['09:00–17:00']);
        self::assertIsString($expectedTimeWithUnit2);
        self::assertStringContainsString($expectedTimeWithUnit2, $html);
    }

    /**
     * @test
     */
    public function showActionRendersTitleOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastEventWithOneVenue.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
    }

    /**
     * @test
     */
    public function showActionRendersAddressOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastEventWithOneVenue.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Kurt-Georg-Kiesinger-Allee 1', $html);
        self::assertStringContainsString('53175 Bonn', $html);
    }

    /**
     * @test
     */
    public function showActionConvertsNewlinesToBreakInVenuAddress(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastEventWithOneVenue.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Kurt-Georg-Kiesinger-Allee 1<br />', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderMultipleVenues(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastEventWithTwoVenuesInSameCity.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
        self::assertStringContainsString('Kameha Grand', $html);
    }

    /**
     * @test
     */
    public function showActionRendersRoom(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithAllScalarData.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('room 13 B', $html);
    }

    /**
     * @test
     */
    public function showActionRendersPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithAllScalarData.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('499.50 €', $html);
    }

    /**
     * @test
     */
    public function showActionWithNonZeroVatRateRendersPriceWithVat(): void
    {
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:seminars/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:seminars/Configuration/TypoScript/setup.typoscript',
                'EXT:seminars/Tests/Functional/Controller/Fixtures/TypoScript/Setup/Rendering.typoscript',
                'EXT:seminars/Tests/Functional/Controller/Fixtures/TypoScript/Setup/PluginConfiguration.typoscript',
                'EXT:seminars/Tests/Functional/Controller/Fixtures/TypoScript/Setup/Vat.typoscript',
            ],
        ]);

        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithAllScalarData.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('594.41 €', $html);
        self::assertStringContainsString('499.50 €', $html);
        self::assertStringContainsString('94.91 €', $html);
    }

    /**
     * @test
     */
    public function showActionForPastSingleEventDoesNotRenderPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastSingleEventWithPrice.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('500.50 €', $html);
    }

    /**
     * @test
     */
    public function showActionForPastEventDateDoesNotRenderPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/PastEventDateWithPrice.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('500.50 €', $html);
    }

    /**
     * @test
     */
    public function showActionForSingleEventWithoutDateRendersPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/SingleEventWithPriceAndNoDate.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('500.50 €', $html);
    }

    /**
     * @test
     */
    public function showActionForEventDateWithoutDateRendersPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventDateWithPriceAndTopicAndNoDate.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('500.50', $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithRegistrationDeadlineOverDoesNotRenderPrice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithVacanciesAndDeadlineOver.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('499.50 €', $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithUnlimitedSeatsRendersAvailable(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithUnlimitedSeats.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $available = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.vacancies.available',
            'seminars',
        );
        self::assertIsString($available);
        self::assertStringContainsString($available, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithMoreThanEnoughVacanciesRendersAvailable(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithMoreThanEnoughVacancies.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $available = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.vacancies.available',
            'seminars',
        );
        self::assertIsString($available);
        self::assertStringContainsString($available, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithExactlyEnoughVacanciesRendersAvailable(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithExactlyEnoughVacancies.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $available = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.vacancies.available',
            'seminars',
        );
        self::assertIsString($available);
        self::assertStringContainsString($available, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithLessThanEnoughVacanciesRendersFewLeft(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithLessThanEnoughVacancies.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $fewLeft = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.vacancies.fewLeft',
            'seminars',
        );
        self::assertIsString($fewLeft);
        self::assertStringContainsString($fewLeft, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithOneVacancyRendersFewLeft(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $fewLeft = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.vacancies.fewLeft',
            'seminars',
        );
        self::assertIsString($fewLeft);
        self::assertStringContainsString($fewLeft, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithNoVacancyRendersFullyBooked(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithNoVacancies.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $fullyBooked = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.vacancies.fullyBooked',
            'seminars',
        );
        self::assertIsString($fullyBooked);
        self::assertStringContainsString($fullyBooked, $html);
    }

    /**
     * @test
     */
    public function showActionRendersDescriptionAsRichText(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithAllScalarData.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('a <b>big</b> event', $html);
    }

    /**
     * @test
     */
    public function showActionRendersNameOfSpeaker(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithOneSpeaker.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Oliver Klee', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderMultipleSpeakers(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithTwoSpeakers.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('Oliver Klee', $html);
        self::assertStringContainsString('Bilbo Baggins', $html);
    }

    /**
     * @test
     */
    public function showActionRendersOrganizationOfSpeaker(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithOneSpeaker.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('[oliverklee.de] TYPO3 und Workshops', $html);
    }

    /**
     * @test
     */
    public function showActionRendersExternalHomepageOfSpeakerAsLink(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithSpeakerWithExternalHomepage.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('href="https://www.oliverklee.de/', $html);
    }

    /**
     * @test
     */
    public function showActionRendersInternalHomepageOfSpeakerAsLink(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithSpeakerWithInternalHomepage.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('href="/speaker-details', $html);
    }

    /**
     * @test
     */
    public function showActionWithOneSpeakerUsesSingularHeading(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithOneSpeaker.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedLabel = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.speakers.one',
            'seminars',
        );
        self::assertIsString($expectedLabel);
        self::assertStringContainsString($expectedLabel, $html);
    }

    /**
     * @test
     */
    public function showActionWithTwoSpeakersUsesPluralHeading(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithTwoSpeakers.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedLabel = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.speakers.many',
            'seminars',
        );
        self::assertIsString($expectedLabel);
        self::assertStringContainsString($expectedLabel, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithOneVacancyRendersLinkToRegistrationForEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = 'href="/registration?tx_seminars_eventregistration%5Baction%5D=checkPrerequisites&amp;'
            . 'tx_seminars_eventregistration%5Bcontroller%5D=EventRegistration&amp;'
            . 'tx_seminars_eventregistration%5Bevent%5D=1';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionForEventWithOneVacancyRendersRegistrationLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithOneVacancy.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedMessage = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.registration.register',
            'seminars',
        );
        self::assertIsString($expectedMessage);
        self::assertStringContainsString($expectedMessage, $html);
    }

    /**
     * @test
     */
    public function showActionForFutureEventWithNoVacanciesAndWaitingListRendersLinkToRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithNoVacanciesAndWaitingList.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = 'href="/registration?tx_seminars_eventregistration%5Baction%5D=checkPrerequisites&amp;'
            . 'tx_seminars_eventregistration%5Bcontroller%5D=EventRegistration&amp;'
            . 'tx_seminars_eventregistration%5Bevent%5D=1';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionCanHaveRegistrationLinkTwice(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithNoVacanciesAndWaitingList.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertSame(2, \substr_count($html, 'href="/registration'));
    }

    /**
     * @test
     */
    public function showActionForFutureEventWithNoVacanciesAndWaitingListRendersWaitingListLabel(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithNoVacanciesAndWaitingList.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expectedMessage = LocalizationUtility::translate(
            'plugin.eventSingleView.events.property.registration.waitingList',
            'seminars',
        );
        self::assertIsString($expectedMessage);
        self::assertStringContainsString($expectedMessage, $html);
    }

    /**
     * @test
     */
    public function showActionForFutureEventWithNoVacanciesAndNoWaitingListDoesNotRenderLinkToRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithNoVacancies.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('href="/registration', $html);
    }

    /**
     * @test
     */
    public function showActionForFutureEventWithVacanciesAndDeadlineOverDoesNotRenderLinkToRegistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/EventSingleViewContentElement.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/FutureEventWithVacanciesAndDeadlineOver.csv');

        $request = (new InternalRequest())
            ->withPageId(3)
            ->withQueryParameter('tx_seminars_eventsingleview[event]', 1);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('href="/registration', $html);
    }
}
