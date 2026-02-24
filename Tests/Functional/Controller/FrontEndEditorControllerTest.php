<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\FrontEndEditorController
 */
final class FrontEndEditorControllerTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/FrontEndEditorController';
    private const ASSERTIONS_PATH = __DIR__ . '/Assertions/FrontEndEditorController';

    private const PAGE_UID = 8;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected array $coreExtensionsToLoad = [
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndEditorContentElement.csv');

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
     * @param positive-int $eventUid
     * @param positive-int $userUid
     */
    private function getTrustedPropertiesFromEditSingleEventForm(int $eventUid, int $userUid): string
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => $eventUid,
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId($userUid);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        return $this->getTrustedPropertiesFromHtml($html);
    }

    /**
     * @param positive-int $userUid
     */
    private function getTrustedPropertiesFromNewSingleEventForm(int $userUid): string
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId($userUid);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        return $this->getTrustedPropertiesFromHtml($html);
    }

    /**
     * @param positive-int $eventUid
     * @param positive-int $userUid
     */
    private function getTrustedPropertiesFromEditEventDateForm(int $eventUid, int $userUid): string
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => $eventUid,
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId($userUid);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        return $this->getTrustedPropertiesFromHtml($html);
    }

    /**
     * @param positive-int $userUid
     */
    private function getTrustedPropertiesFromNewEventDateForm(int $userUid): string
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId($userUid);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        return $this->getTrustedPropertiesFromHtml($html);
    }

    private function getTrustedPropertiesFromHtml(string $html): string
    {
        $matches = [];
        \preg_match('/__trustedProperties]" value="([a-zA-Z0-9&{};:,_\\[\\]]+)"/', $html, $matches);
        if (!isset($matches[1])) {
            throw new \RuntimeException('Could not fetch trustedProperties from returned HTML.', 1744911802);
        }

        return \html_entity_decode($matches[1]);
    }

    /**
     * @test
     */
    public function indexActionWithoutLoggedInUserDoesNotRenderEventsWithoutOwner(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithoutOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $response = $this->executeFrontendSubRequest($request);

        self::assertStringNotContainsString('event without owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithoutLoggedInUserDoesNotRenderEventsWithOwner(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $response = $this->executeFrontendSubRequest($request);

        self::assertStringNotContainsString('event with owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionHasHeadline(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        $expected = LocalizationUtility::translate('plugin.frontEndEditor.index.headline', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionByDefaultHasLinkToNewSingleEventAction(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=newSingleEvent'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionConfiguredForSingleEventsHasLinkToNewSingleEventAction(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/pageAndContentElementForCreatingSingleEvents.csv');

        $request = (new InternalRequest())->withPageId(99);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=newSingleEvent'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionConfiguredForEventDatesHasLinkToNewEventDateAction(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/pageAndContentElementForCreatingEventDates.csv');

        $request = (new InternalRequest())->withPageId(99);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=newEventDate'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserDoesNotRenderSingleEventsWithoutOwner(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithoutOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringNotContainsString('event without owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserDoesNotRenderSingleEventsFromOtherOwner(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventFromDifferentOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringNotContainsString('event from different owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserRendersSingleEventsOwnedByLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringContainsString('event with owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserRendersEventDateOwnedByLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/EventDateWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringContainsString('event date with owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserDoesNotRenderEventTopicOwnedByLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/EventTopicWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringNotContainsString('event topic with owner', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionRendersEventUid(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithOwnerAndHigherUid.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringContainsString('1337', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionRendersDateOfOneDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/OneDaySingleEventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertStringContainsString('2025-10-28', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionRendersDateOfMultiDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/TwoDaySingleEventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);
        $body = (string)$response->getBody();

        self::assertStringContainsString('2025-10-28', $body);
        self::assertStringContainsString('2025-10-29', $body);
    }

    /**
     * @test
     */
    public function indexActionForEventWithRegularRegistrationsRendersRegistrationCount(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithRegistrations.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);
        $body = (string)$response->getBody();

        self::assertStringContainsString(' 4', $body);
    }

    /**
     * @test
     */
    public function indexActionForEventWithVacanciesRendersVacanciesCount(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithVacancies.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);
        $body = (string)$response->getBody();

        self::assertStringContainsString('17', $body);
    }

    /**
     * @test
     */
    public function indexActionWithSingleEventHasEditSingleEventLink(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/SingleEventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=editSingleEvent'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor'
            . '&amp;tx_seminars_frontendeditor%5Bevent%5D=1';
        self::assertStringContainsString($expected, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function indexActionWithEventDateHasEditEventDateLink(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/EventDateWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=editEventDate'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor'
            . '&amp;tx_seminars_frontendeditor%5Bevent%5D=1';
        self::assertStringContainsString($expected, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function editSingleEventActionHasUpdateSingleEventFormAction(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=updateSingleEvent'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function nonDateFormFieldKeysForSingleEventDataProvider(): array
    {
        return [
            'internalTitle' => ['internalTitle'],
            'description' => ['description'],
            'registrationRequired' => ['registrationRequired'],
            'waitingList' => ['waitingList'],
            'minimumNumberOfRegistrations' => ['minimumNumberOfRegistrations'],
            'maximumNumberOfRegistrations' => ['maximumNumberOfRegistrations'],
            'numberOfOfflineRegistrations' => ['numberOfOfflineRegistrations'],
            'standardPrice' => ['standardPrice'],
            'earlyBirdPrice' => ['earlyBirdPrice'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider nonDateFormFieldKeysForSingleEventDataProvider
     */
    public function editSingleEventActionHasAllNonDateFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function dateFormFieldKeysForSingleEventDataProvider(): array
    {
        return [
            'start' => ['start'],
            'end' => ['end'],
            'earlyBirdDeadline' => ['earlyBirdDeadline'],
            'registrationDeadline' => ['registrationDeadline'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider dateFormFieldKeysForSingleEventDataProvider
     */
    public function editSingleEventActionHasAllDateFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][date]"', $html);
        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][dateFormat]"', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function singleAssociationFormFieldKeysForSingleEventDataProvider(): array
    {
        return [
            'eventType' => ['eventType'],
        ];
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function multiAssociationFormFieldKeysForSingleEventDataProvider(): array
    {
        return [
            'categories' => ['categories'],
            'venues' => ['venues'],
            'speakers' => ['speakers'],
            'organizers' => ['organizers'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider singleAssociationFormFieldKeysForSingleEventDataProvider
     * @dataProvider multiAssociationFormFieldKeysForSingleEventDataProvider
     */
    public function editSingleEventActionHasAllAssociationFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function formFieldKeysIrrelevantForSingleEventsDataProvider(): array
    {
        return [
            'topic' => ['topic'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider formFieldKeysIrrelevantForSingleEventsDataProvider
     */
    public function editSingleEventActionHasNoFormFieldsIrrelevantForSingleEvents(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider multiAssociationFormFieldKeysForSingleEventDataProvider
     */
    public function editSingleEventActionForEventWithAllAssociationsHasSelectedMultiAssociationOptions(
        string $key
    ): void {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwnerAndAllAssociations.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString(
            'name="tx_seminars_frontendeditor[event][' . $key . '][]" value="1" checked="checked"',
            $html,
        );
    }

    /**
     * @test
     */
    public function editSingleEventActionForEventWithAllAssociationsHasSelectedEventTypeOption(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwnerAndAllAssociations.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('<option value="1" selected="selected">workshop</option>', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function auxiliaryRecordTitlesForSingleEventDataProvider(): array
    {
        return [
            'categories' => ['cooking'],
            'eventType' => ['workshop'],
            'venues' => ['Jugendherberge Bonn'],
            'speakers' => ['Ned Knowledge'],
            'organizers' => ['Training Inc.'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $title
     * @dataProvider auxiliaryRecordTitlesForSingleEventDataProvider
     */
    public function editSingleEventActionHasTitlesOfAuxiliaryRecords(string $title): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString($title, $html);
    }

    /**
     * @test
     */
    public function editSingleEventActionWithOwnEventAssignsProvidedEventToView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString(
            '<input type="hidden" name="tx_seminars_frontendeditor[event][__identity]" value="1" />',
            $html,
        );
        self::assertStringContainsString('event with owner', $html);
    }

    /**
     * @test
     */
    public function editSingleEventActionForUserWithDefaultOrganizerHasNoOrganizerFormField(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/FrontEndUserWithDefaultOrganizer.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwnerWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][organizers]"', $html);
    }

    /**
     * @test
     */
    public function editSingleEventActionWithOwnEventRendersNumberOfOfflineRegistrations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('value="59"', $html);
    }

    /**
     * @test
     */
    public function editSingleEventActionWithEventFromOtherUserThrowsException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventFromDifferentOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $context);

        self::assertForbiddenResponse($response);
    }

    /**
     * @test
     */
    public function editSingleEventActionWithEventWithoutOwnerThrowsException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editSingleEventAction/EventWithoutOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editSingleEvent',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $context);

        self::assertForbiddenResponse($response);
    }

    /**
     * @test
     */
    public function updateSingleEventActionWithOwnEventUpdatesEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]'
            => $this->getTrustedPropertiesFromEditSingleEventForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateSingleEvent',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][numberOfOfflineRegistrations]' => '5',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateSingleEventAction/UpdatedEvent.csv');
    }

    /**
     * @test
     */
    public function updateSingleEventActionKeepsPidUnchanged(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/EventWithDifferentPid.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]'
            => $this->getTrustedPropertiesFromEditSingleEventForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateSingleEvent',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateSingleEventAction/EventWithDifferentPid.csv');
    }

    /**
     * @test
     */
    public function updateSingleEventActionCanSetOrganizer(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]'
            => $this->getTrustedPropertiesFromEditSingleEventForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateSingleEvent',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][organizers]' => '',
            'tx_seminars_frontendeditor[event][organizers][]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateSingleEventAction/UpdatedEventWithOrganizer.csv');
    }

    /**
     * @test
     */
    public function updateSingleEventActionForUserWithDefaultOrganizerKeepsOrganizerUnchanged(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/FrontEndUserWithDefaultOrganizer.csv');
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/updateSingleEventAction/EventWithOwnerWithDefaultOrganizer.csv',
        );

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]'
            => $this->getTrustedPropertiesFromEditSingleEventForm(1, 2),
            'tx_seminars_frontendeditor[action]' => 'updateSingleEvent',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'event with owner',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . '/updateSingleEventAction/EventWithOwnerWithDefaultOrganizer.csv',
        );
    }

    /**
     * @test
     */
    public function updateSingleEventActionCanSetCategory(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]'
            => $this->getTrustedPropertiesFromEditSingleEventForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateSingleEvent',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][categories]' => '',
            'tx_seminars_frontendeditor[event][categories][]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateSingleEventAction/UpdatedEventWithCategory.csv');
    }

    /**
     * @test
     */
    public function updateSingleEventActionUpdatesSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateSingleEventAction/EventWithOwner.csv');

        $newTitle = 'Karaoke party';
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]'
            => $this->getTrustedPropertiesFromEditSingleEventForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateSingleEvent',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => $newTitle,
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateSingleEventAction/UpdatedEventWithSlug.csv');
    }

    /**
     * @test
     */
    public function editEventDateActionHasUpdateEventDateFormAction(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=updateEventDate'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function nonDateFormFieldKeysForEventDateDataProvider(): array
    {
        return [
            'internalTitle' => ['internalTitle'],
            'registrationRequired' => ['registrationRequired'],
            'waitingList' => ['waitingList'],
            'minimumNumberOfRegistrations' => ['minimumNumberOfRegistrations'],
            'maximumNumberOfRegistrations' => ['maximumNumberOfRegistrations'],
            'numberOfOfflineRegistrations' => ['numberOfOfflineRegistrations'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider nonDateFormFieldKeysForEventDateDataProvider
     */
    public function editEventDateActionHasAllNonDateFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function dateFormFieldKeysForEventDateDataProvider(): array
    {
        return [
            'start' => ['start'],
            'end' => ['end'],
            'earlyBirdDeadline' => ['earlyBirdDeadline'],
            'registrationDeadline' => ['registrationDeadline'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider dateFormFieldKeysForEventDateDataProvider
     */
    public function editEventDateActionHasAllDateFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][date]"', $html);
        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][dateFormat]"', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function singleAssociationFormFieldKeysForEventDateDataProvider(): array
    {
        return [
            'topic' => ['topic'],
        ];
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function multiAssociationFormFieldKeysForEventDateDataProvider(): array
    {
        return [
            'venues' => ['venues'],
            'speakers' => ['speakers'],
            'organizers' => ['organizers'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider singleAssociationFormFieldKeysForEventDateDataProvider
     * @dataProvider multiAssociationFormFieldKeysForEventDateDataProvider
     */
    public function editEventDateActionHasAllAssociationFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/Topic.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function formFieldKeysIrrelevantForEventDatesDataProvider(): array
    {
        return [
            'description' => ['description'],
            'eventType' => ['eventType'],
            'categories' => ['categories'],
            'standardPrice' => ['standardPrice'],
            'earlyBirdPrice' => ['earlyBirdPrice'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider formFieldKeysIrrelevantForEventDatesDataProvider
     */
    public function editEventDateActionHasNoFormFieldsIrrelevantForEventDates(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider multiAssociationFormFieldKeysForEventDateDataProvider
     */
    public function editEventDateActionForEventWithAllAssociationsHasSelectedMultiAssociationOptions(
        string $key
    ): void {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwnerAndAllAssociations.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString(
            'name="tx_seminars_frontendeditor[event][' . $key . '][]" value="1" checked="checked"',
            $html,
        );
    }

    /**
     * @test
     */
    public function editEventDateActionForEventWithTopicHasSelectedTopicOption(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwnerAndTopic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('<option value="2" selected="selected">OOP with PHP</option>', $html);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function auxiliaryRecordTitlesForEventDateDataProvider(): array
    {
        return [
            'venues' => ['Jugendherberge Bonn'],
            'speakers' => ['Ned Knowledge'],
            'organizers' => ['Training Inc.'],
            'topic' => ['OOP with PHP'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $title
     * @dataProvider auxiliaryRecordTitlesForEventDateDataProvider
     */
    public function editEventDateActionHasTitlesOfAuxiliaryRecords(string $title): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/Topic.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString($title, $html);
    }

    /**
     * @test
     */
    public function editEventDateActionWithOwnEventAssignsProvidedEventToView(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString(
            '<input type="hidden" name="tx_seminars_frontendeditor[event][__identity]" value="1" />',
            $html,
        );
        self::assertStringContainsString('event with owner', $html);
    }

    /**
     * @test
     */
    public function editEventDateActionForUserWithDefaultOrganizerHasNoOrganizerFormField(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/FrontEndUserWithDefaultOrganizer.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwnerWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][organizers]"', $html);
    }

    /**
     * @test
     */
    public function editEventDateActionWithOwnEventRendersNumberOfOfflineRegistrations(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('value="59"', $html);
    }

    /**
     * @test
     */
    public function editEventDateActionWithEventFromOtherUserThrowsException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventFromDifferentOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $context);

        self::assertForbiddenResponse($response);
    }

    /**
     * @test
     */
    public function editEventDateActionWithEventWithoutOwnerThrowsException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/editEventDateAction/EventWithoutOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'editEventDate',
            'tx_seminars_frontendeditor[event]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $context);

        self::assertForbiddenResponse($response);
    }

    /**
     * @test
     */
    public function updateEventDateActionWithOwnEventUpdatesEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][numberOfOfflineRegistrations]' => '5',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateEventDateAction/UpdatedEvent.csv');
    }

    /**
     * @test
     */
    public function updateEventDateActionKeepsPidUnchanged(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithDifferentPid.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateEventDateAction/EventWithDifferentPid.csv');
    }

    /**
     * @test
     */
    public function updateEventDateActionCanSetOrganizer(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithOwner.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][organizers]' => '',
            'tx_seminars_frontendeditor[event][organizers][]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateEventDateAction/UpdatedEventWithOrganizer.csv');
    }

    /**
     * @test
     */
    public function updateEventDateActionCanSetTopic(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithOwner.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][topic]' => '2',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateEventDateAction/UpdatedEventWithTopic.csv');
    }

    /**
     * @test
     */
    public function updateEventDateActionForUserWithDefaultOrganizerKeepsOrganizerUnchanged(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/FrontEndUserWithDefaultOrganizer.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithOwnerWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 2),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'event with owner',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . '/updateEventDateAction/EventWithOwnerWithDefaultOrganizer.csv',
        );
    }

    /**
     * @test
     */
    public function updateEventDateActionForEventWithTopicUpdatesSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithTopicAndOwner.csv');

        $newTitle = 'Karaoke party';
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => $newTitle,
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateEventDateAction/UpdatedEventWithTopicAndSlug.csv');
    }

    /**
     * @test
     */
    public function updateEventDateActionForEventWithoutTopicSetsSlugToUidOnly(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/updateEventDateAction/EventWithOwner.csv');

        $newTitle = 'Karaoke party';
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromEditEventDateForm(1, 1),
            'tx_seminars_frontendeditor[action]' => 'updateEventDate',
            'tx_seminars_frontendeditor[event][__identity]' => '1',
            'tx_seminars_frontendeditor[event][internalTitle]' => $newTitle,
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/updateEventDateAction/UpdatedEventWithUidOnlySlug.csv');
    }

    /**
     * @test
     */
    public function newSingleEventActionCanBeRendered(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('Create new event', $html);
    }

    /**
     * @test
     */
    public function newSingleEventActionHasFormTargetCreateSingleEventAction(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=createSingleEvent'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider nonDateFormFieldKeysForSingleEventDataProvider
     */
    public function newSingleEventActionHasAllNonDateFormFields(string $key): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider dateFormFieldKeysForSingleEventDataProvider
     */
    public function newSingleEventActionHasAllDateFormFields(string $key): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][date]"', $html);
        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][dateFormat]"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider multiAssociationFormFieldKeysForSingleEventDataProvider
     * @dataProvider singleAssociationFormFieldKeysForSingleEventDataProvider
     */
    public function newSingleEventActionHasAllAssociationFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newSingleEventAction/AuxiliaryRecords.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider formFieldKeysIrrelevantForSingleEventsDataProvider
     */
    public function newSingleEventEventActionHasNoFormFieldsIrrelevantForSingleEvents(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newSingleEventAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     */
    public function newSingleEventActionForUserWithDefaultOrganizerHasNoOrganizerFormField(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newSingleEventAction/FrontEndUserWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][organizers]"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $title
     * @dataProvider auxiliaryRecordTitlesForSingleEventDataProvider
     */
    public function newSingleEventActionHasTitlesOfAuxiliaryRecords(string $title): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newSingleEventAction/AuxiliaryRecords.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newSingleEvent',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString($title, $html);
    }

    /**
     * @test
     */
    public function createSingleEventActionCreatesSingleEvent(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedSingleEvent.csv');
    }

    /**
     * @test
     */
    public function createSingleEventActionSetsLoggedInUserAsOwnerOfProvidedEvent(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithOwner.csv');
    }

    /**
     * @test
     */
    public function createSingleEventActionSetsPidFromConfiguration(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithPid.csv');
    }

    /**
     * @test
     */
    public function createSingleEventActionSetsSlug(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithSlug.csv');
    }

    /**
     * @test
     */
    public function createSingleEventActionCanSetNumberOfOfflineRegistrations(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][numberOfOfflineRegistrations]' => '3',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithOfflineRegistrations.csv',
        );
    }

    /**
     * @test
     */
    public function createSingleEventActionCanSetOrganizer(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createSingleEventAction/AuxiliaryRecords.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][organizers]' => '',
            'tx_seminars_frontendeditor[event][organizers][]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithOrganizer.csv');
    }

    /**
     * @test
     */
    public function createSingleEventForUserWithDefaultOrganizerSetsDefaultOrganizer(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createSingleEventAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createSingleEventAction/FrontEndUserWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(2),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithDefaultOrganizer.csv',
        );
    }

    /**
     * @test
     */
    public function createSingleEventActionCanSetCategory(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createSingleEventAction/AuxiliaryRecords.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewSingleEventForm(1),
            'tx_seminars_frontendeditor[action]' => 'createSingleEvent',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][categories]' => '',
            'tx_seminars_frontendeditor[event][categories][]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createSingleEventAction/CreatedEventWithCategory.csv');
    }

    /**
     * @test
     */
    public function newEventDateActionCanBeRendered(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('Create new event', $html);
    }

    /**
     * @test
     */
    public function newEventDateActionHasFormTargetCreateEventDateAction(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        $expected = '?tx_seminars_frontendeditor%5Baction%5D=createEventDate'
            . '&amp;tx_seminars_frontendeditor%5Bcontroller%5D=FrontEndEditor';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider nonDateFormFieldKeysForEventDateDataProvider
     */
    public function newEventDateActionHasAllNonDateFormFields(string $key): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider dateFormFieldKeysForEventDateDataProvider
     */
    public function newEventDateActionHasAllDateFormFields(string $key): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][date]"', $html);
        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . '][dateFormat]"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider multiAssociationFormFieldKeysForEventDateDataProvider
     * @dataProvider singleAssociationFormFieldKeysForEventDateDataProvider
     */
    public function newEventDateActionHasAllAssociationFormFields(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/AuxiliaryRecords.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     * @dataProvider formFieldKeysIrrelevantForEventDatesDataProvider
     */
    public function newEventDateEventActionHasNoFormFieldsIrrelevantForEventDates(string $key): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][' . $key . ']"', $html);
    }

    /**
     * @test
     */
    public function newEventDateActionForUserWithDefaultOrganizerHasNoOrganizerFormField(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/FrontEndUserWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('name="tx_seminars_frontendeditor[event][organizers]"', $html);
    }

    /**
     * @test
     *
     * @param non-empty-string $title
     * @dataProvider auxiliaryRecordTitlesForEventDateDataProvider
     */
    public function newEventDateActionHasTitlesOfAuxiliaryRecords(string $title): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/newEventDateAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[action]' => 'newEventDate',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString($title, $html);
    }

    /**
     * @test
     */
    public function createEventDateActionCreatesEventDate(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventDate.csv');
    }

    /**
     * @test
     */
    public function createEventDateActionSetsLoggedInUserAsOwnerOfProvidedEvent(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithOwner.csv');
    }

    /**
     * @test
     */
    public function createEventDateActionSetsPidFromConfiguration(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithPid.csv');
    }

    /**
     * @test
     */
    public function createEventDateActionCanSetTopic(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createEventDateAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][topic]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithTopic.csv');
    }

    /**
     * @test
     */
    public function createEventDateActionSetsSlug(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createEventDateAction/Topic.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][topic]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithTopicAndSlug.csv');
    }

    /**
     * @test
     */
    public function createEventDateActionCanSetNumberOfOfflineRegistrations(): void
    {
        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][numberOfOfflineRegistrations]' => '3',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithOfflineRegistrations.csv',
        );
    }

    /**
     * @test
     */
    public function createEventDateActionCanSetOrganizer(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createEventDateAction/AuxiliaryRecords.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(1),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
            'tx_seminars_frontendeditor[event][organizers]' => '',
            'tx_seminars_frontendeditor[event][organizers][]' => '1',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithOrganizer.csv');
    }

    /**
     * @test
     */
    public function createEventDateForUserWithDefaultOrganizerSetsDefaultOrganizer(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createEventDateAction/AuxiliaryRecords.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/createEventDateAction/FrontEndUserWithDefaultOrganizer.csv');

        $request = (new InternalRequest())->withPageId(self::PAGE_UID)->withQueryParameters([
            'tx_seminars_frontendeditor[__trustedProperties]' => $this->getTrustedPropertiesFromNewEventDateForm(2),
            'tx_seminars_frontendeditor[action]' => 'createEventDate',
            'tx_seminars_frontendeditor[event][internalTitle]' => 'Karaoke party',
        ]);
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . '/createEventDateAction/CreatedEventWithDefaultOrganizer.csv');
    }

    private static function assertForbiddenResponse(ResponseInterface $response): void
    {
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Forbidden', $response->getReasonPhrase());
        self::assertStringContainsString(
            'You do not have permission to edit this event.',
            $response->getBody()->__toString(),
        );
    }
}
