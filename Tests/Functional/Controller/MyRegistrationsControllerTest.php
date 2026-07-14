<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Controller;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\MyRegistrationsController
 */
final class MyRegistrationsControllerTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/MyRegistrationsController';

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
        'typo3conf/ext/seminars/Tests/Functional/Controller/Fixtures/MyRegistrationsController/downloadAttendeeAttachmentAction/fileadmin/speaker.txt'
        => 'fileadmin/speaker.txt',
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/MyRegistrationsContentElement.csv');
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
    public function indexActionForNoUserLoggedInShowsPleaseLogInMessage(): void
    {
        $request = (new InternalRequest())->withPageId(7);

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.error.notLoggedIn', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionForNoUserLoggedInReturnsStatus403(): void
    {
        self::markTestSkipped('Currently, the HTTP status code gets lost when using executeFrontendSubRequest.');

        $request = (new InternalRequest())->withPageId(7);
        $status = $this->executeFrontendSubRequest($request)->getStatusCode();

        self::assertSame(403, $status);
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserReturnsStatus200(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserForNoRegistrationsReturnsNoRegistrationsMessage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate(
            'plugin.myRegistrations.messages.noRegistrations_formal',
            'seminars',
        );
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserForRegistrationsOfOtherUsersReturnsNoRegistrationsMessage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationOfOtherUser.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate(
            'plugin.myRegistrations.messages.noRegistrations_formal',
            'seminars',
        );
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionWithLoggedInUserForRegistrationsOfOtherUsersDoesNotRenderEventTitle(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationOfOtherUser.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringNotContainsString('some other event', $html);
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function indexActionWithLoggedInUserForRegistrationsWithDeletedEventDoesNotCrash(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationForDeletedEvent.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $this->executeFrontendSubRequest($request, $requestContext);
    }

    /**
     * @test
     */
    public function indexActionRendersDateOfSingleDaySingleEventRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationForSingleDayEvent.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersDateOfMultiDaySingleEventRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationForMultiDayEvent.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01–2039-12-02', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersEventTypeOfSingleEventRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithEventType.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('workshop', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersTitleOfSingleEventRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/Registration.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('the event title', $html);
    }

    /**
     * @test
     */
    public function indexActionDoesNotRenderHiddenRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/HiddenRegistration.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringNotContainsString('the event title', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersTopicTitleOfEventDateRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationForEventDate.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('the topic title', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersOrganizersOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithTwoOrganizers.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Rainbow Recitals,', $html);
        self::assertStringContainsString('Fortran Foundation', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersSingleCityOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithOneVenue.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Bonn', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersMultipleCitiesOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithTwoVenuesInDifferentCities.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Bonn', $html);
        self::assertStringContainsString('Köln', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersSingleVenueOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithOneVenue.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersMultipleVenuesOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithTwoVenuesInDifferentCities.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
        self::assertStringContainsString('Premier Inn', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function attendanceModeForIndexActionDataProvider(): array
    {
        return [
            'on-site' => ['OnSiteRegistration.csv', '1'],
            'online' => ['OnlineRegistration.csv', '2'],
            'hybrid' => ['HybridRegistration.csv', '3'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider attendanceModeForIndexActionDataProvider
     */
    public function indexActionRendersAttendanceModeOfRegistration(string $fixtureFile, string $labelKey): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/' . $fixtureFile);

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $keyPrefix = 'plugin.myRegistrations.property.attendanceMode.';
        $expected = LocalizationUtility::translate($keyPrefix . $labelKey, 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionRendersCategoriesOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationForEventWithTwoCategories.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('intense', $html);
        self::assertStringContainsString('laid-back', $html);
    }

    /**
     * @test
     */
    public function indexActionForEventDateRendersCategoriesOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationForEventDateWithTwoCategories.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('intense', $html);
        self::assertStringContainsString('laid-back', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersSpeakersOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegistrationWithTwoSpeakers.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Sally Speaker,', $html);
        self::assertStringContainsString('Sam Speaker', $html);
    }

    /**
     * @test
     */
    public function indexActionRendersEventUid(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/Registration.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $labelWithPlaceholder = LocalizationUtility::translate(
            'plugin.myRegistrations.property.eventUid.number',
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
    public function indexActionRendersRegularRegistrationStatusOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/RegularRegistration.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.property.registrationStatus.0', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionRendersWaitingListRegistrationStatusOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/WaitingListRegistration.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.property.registrationStatus.1', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionRendersNonbindingReserverationRegistrationStatusOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/NonbindingReservation.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.property.registrationStatus.2', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function indexActionLinksEventTitleToShowAction(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/indexAction/Registration.csv');

        $request = (new InternalRequest())->withPageId(7);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $urlPrefix = '/my-events\\?tx_seminars_myregistrations%5Baction%5D=show&amp;'
            . 'tx_seminars_myregistrations%5Bcontroller%5D=MyRegistrations&amp;'
            . 'tx_seminars_myregistrations%5Bregistration%5D=1';
        self::assertMatchesRegularExpression('#' . $urlPrefix . '[^"]*">.*the event title#s', $html);
    }

    /**
     * @test
     */
    public function showActionForNoUserLoggedInShowsPleaseLogInMessage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Registration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext());

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.error.notLoggedIn', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionForNoUserLoggedInReturnsStatus403(): void
    {
        self::markTestSkipped('Currently, the HTTP status code gets lost when using executeFrontendSubRequest.');

        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Registration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext());
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function showActionWithRegistrationOfLoggedInUserReturnsStatus200(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Registration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function showActionWithRegistrationOfOtherUserReturnsStatus404(): void
    {
        self::markTestSkipped('Currently, the HTTP status code gets lost when using executeFrontendSubRequest.');

        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Registration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function showActionWithRegistrationOfOtherUserRendersNotFoundMessage(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationOfOtherUser.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);
        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.error.notFound', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionRendersTitleOfSingleEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Registration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertMatchesRegularExpression('#<h1>\\s*the event title#s', $html);
    }

    /**
     * @test
     */
    public function showActionRendersDisplayTitleOfEventDate(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForEventDate.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertMatchesRegularExpression('#<h1>\\s*the topic title#s', $html);
        self::assertStringNotContainsString('the date title', $html);
    }

    /**
     * @test
     */
    public function showActionRendersEventType(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithEventType.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('workshop', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function singleDayEventDataProvider(): array
    {
        return [
            'without time slots' => ['RegistrationForSingleDayEvent.csv'],
            'with time slots' => ['RegistrationForSingleDayEventWithTimeSlots.csv'],
        ];
    }

    /**
     * @test
     * @dataProvider singleDayEventDataProvider
     */
    public function showActionRendersDateOfSingleDayEvent(string $csvFile): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvFile);

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01', $html);
    }

    /**
     * @test
     */
    public function showActionRendersDateOfSingleDayEventOnlyOnce(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForSingleDayEvent.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertSame(1, \substr_count($html, '2039-12-01'));
    }

    /**
     * @test
     */
    public function showActionRendersStartAndEndOfMultiDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForMultiDayEvent.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01–2039-12-02', $html);
    }

    /**
     * @test
     * @dataProvider singleDayEventDataProvider
     */
    public function showActionRendersStartAndEndTimeOfSingleDayEvent(string $csvFile): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvFile);

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('09:00–17:00', $html);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function multiDayEventDataProvider(): array
    {
        return [
            'without time slots' => ['RegistrationForMultiDayEvent.csv'],
            'with time slots' => ['RegistrationForMultiDayEventWithTimeSlots.csv'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider multiDayEventDataProvider
     */
    public function showActionRendersStartDateAndTimeOfMultiDayEvent(string $csvDataSet): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvDataSet);

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01 09:00', $html);
    }

    /**
     * @test
     * @dataProvider multiDayEventDataProvider
     */
    public function showActionRendersEndDateAndTimeOfMultiDayEvent(string $csvDataSet): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/' . $csvDataSet);

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-02 17:00', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotTimesOfSingleDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForSingleDayEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expectedTimeWithUnit = LocalizationUtility::translate('timeWithUnit', 'seminars', ['09:00–13:00']);
        self::assertIsString($expectedTimeWithUnit);
        self::assertStringContainsString($expectedTimeWithUnit, $html);

        $expectedTimeWithUnit2 = LocalizationUtility::translate('timeWithUnit', 'seminars', ['15:00–17:00']);
        self::assertIsString($expectedTimeWithUnit2);
        self::assertStringContainsString($expectedTimeWithUnit2, $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotsDateOfSingleDayEvent(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForSingleDayEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotsDatesOfMultiDayEventWithTimeSlots(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForMultiDayEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('2039-12-01', $html);
        self::assertStringContainsString('2039-12-02', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderTimeSlotsTimesOfMultiDayEventWithTimeSlots(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationForMultiDayEventWithTimeSlots.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expectedTimeWithUnit = LocalizationUtility::translate('timeWithUnit', 'seminars', ['09:00–13:00']);
        self::assertIsString($expectedTimeWithUnit);
        self::assertStringContainsString($expectedTimeWithUnit, $html);

        $expectedTimeWithUnit2 = LocalizationUtility::translate('timeWithUnit', 'seminars', ['15:00–17:00']);
        self::assertIsString($expectedTimeWithUnit2);
        self::assertStringContainsString($expectedTimeWithUnit2, $html);
    }

    /**
     * @test
     */
    public function showActionRendersTitleOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithOneVenue.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();
        self::assertStringContainsString('Maritim Hotel', $html);
    }

    /**
     * @test
     */
    public function showActionRendersAddressOfVenue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithOneVenue.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();
        self::assertStringContainsString('Kurt-Georg-Kiesinger-Allee 1', $html);
        self::assertStringContainsString('53175 Bonn', $html);
    }

    /**
     * @test
     */
    public function showActionConvertsNewlinesToBreakInVenuAddress(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithOneVenue.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Kurt-Georg-Kiesinger-Allee 1<br />', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderMultipleVenues(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithTwoVenuesInSameCity.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Maritim Hotel', $html);
        self::assertStringContainsString('Kameha Grand', $html);
    }

    /**
     * @test
     */
    public function showActionRendersRoom(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/Registration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('room 13 B', $html);
    }

    /**
     * @test
     */
    public function showActionRendersDescription(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithDescription.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('<p>Großartig, großartig, <em>sehr</em> großartig</p>', $html);
    }

    /**
     * @test
     */
    public function showActionCanRenderMultipleSpeakers(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithTwoSpeakers.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('Anna jajaja', $html);
        self::assertStringContainsString('Bella lalala', $html);
    }

    /**
     * @test
     */
    public function showActionRendersRegularRegistrationStatusOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegularRegistration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.property.registrationStatus.0', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionRendersWaitingListRegistrationStatusOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/WaitingListRegistration.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.property.registrationStatus.1', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionRendersNonbindingReserverationRegistrationStatusOfRegistrationOfTheLoggedInUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/NonbindingReservation.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = LocalizationUtility::translate('plugin.myRegistrations.property.registrationStatus.2', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionForRegistrationWithUnregistrationPossibleShowsLinkToUnregistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegularRegistrationWithUnregistrationPossible.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $urlPrefix = '/my-events\\?tx_seminars_myregistrations%5Baction%5D=checkPrerequisites&amp;'
            . 'tx_seminars_myregistrations%5Bcontroller%5D=EventUnregistration&amp;'
            . 'tx_seminars_myregistrations%5Bregistration%5D=1';
        $linkText = LocalizationUtility::translate('plugin.myRegistrations.show.toUnregistrationForm', 'seminars');
        self::assertIsString($linkText);
        self::assertMatchesRegularExpression('#' . $urlPrefix . '[^"]*">.*' . $linkText . '#s', $html);
    }

    /**
     * @test
     */
    public function showActionForRegistrationWithUnregistrationNotPossibleDoesNotShowLinkToUnregistration(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/showAction/RegularRegistrationWithUnregistrationDeadlineOver.csv',
        );

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $urlPrefix = '/my-events?tx_seminars_myregistrations%5Baction%5D=checkPrerequisites&amp;'
            . 'tx_seminars_myregistrations%5Bcontroller%5D=EventUnregistration&amp;'
            . 'tx_seminars_myregistrations%5Bregistration%5D=1';
        self::assertStringNotContainsString($urlPrefix, $html);
    }

    /**
     * @test
     */
    public function showActionForRegularRegistrationWithDownloadsRendersLinkToAttachmentDownload(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegularRegistrationWithDownloadWithoutTitle.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expected = 'href="/my-events?'
            . 'tx_seminars_myregistrations%5Baction%5D=downloadAttendeeAttachment&amp;'
            . 'tx_seminars_myregistrations%5Bcontroller%5D=MyRegistrations&amp;'
            . 'tx_seminars_myregistrations%5BfileUid%5D=1&amp;'
            . 'tx_seminars_myregistrations%5Bregistration%5D=1';
        self::assertStringContainsString($expected, $html);
    }

    /**
     * @test
     */
    public function showActionForRegularRegistrationWithDownloadWithoutTitleUsesFilenameAsLinkText(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegularRegistrationWithDownloadWithoutTitle.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertMatchesRegularExpression('#>\\s*speaker\\.txt\\s*</a>#', $html);
    }

    /**
     * @test
     */
    public function showActionForRegularRegistrationWithDownloadWithTitleUsesTitleAsLinkText(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegularRegistrationWithDownloadWithTitle.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertMatchesRegularExpression('#>\\s*speaker portrait\\s*</a>#', $html);
    }

    /**
     * @test
     */
    public function showActionForWaitingListRegistrationWithDownloadsDoesNotRendersDownload(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/WaitingListRegistrationWithDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringNotContainsString('speaker.txt', $html);
    }

    /**
     * @test
     */
    public function showActionForNonbindingReservationWithDownloadsDoesNotRendersDownload(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/NonbindingReservationWithDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringNotContainsString('speaker.txt', $html);
    }

    /**
     * @test
     */
    public function showActionForRegularRegistrationWithDownloadStartDateInPastRendersDownload(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithDownloadStartInPast.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringContainsString('speaker.txt', $html);
    }

    /**
     * @test
     */
    public function showActionForRegularRegistrationWithDownloadStartDateInFuturesDoesNotRenderDownload(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/showAction/RegistrationWithDownloadStartInFuture.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'show')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        self::assertStringNotContainsString('speaker.txt', $html);
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionForRegistrationWithoutEventThrowsRuntimeException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationWithoutEvent.csv',
        );

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Event not found.');
        $this->expectExceptionCode(1742846429);

        $this->executeFrontendSubRequest($request, $requestContext);
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithUidOfInexistentFileThrowsRuntimeException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationWithoutDownload.csv',
        );

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found.');
        $this->expectExceptionCode(1742847711);

        $this->executeFrontendSubRequest($request, $requestContext);
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithUidOfFileFromOtherEventThrowsRuntimeException(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(
            self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownloadFromOtherEvent.csv',
        );

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found.');
        $this->expectExceptionCode(1742847711);

        $this->executeFrontendSubRequest($request, $requestContext);
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithValidFileUidReturnsStatusOkay(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithValidFileUidReturnsCacheControlPrivateHeader(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame('private', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithValidFileUidReturnsContentDispositionHeaderWithFilename(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame('filename="speaker.txt"', $response->getHeaderLine('Content-Disposition'));
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithValidFileUidReturnsContentLengthHeader(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame('7', $response->getHeaderLine('Content-Length'));
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithValidFileUidReturnsContentTypeHeaderWithFileMimeType(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $response = $this->executeFrontendSubRequest($request, $requestContext);

        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @test
     */
    public function downloadAttendeeAttachmentActionWithValidFileUidReturnsFileContents(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/FrontEndUserAndGroup.csv');
        $this->importCSVDataSet(self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/RegistrationAndDownload.csv');

        $request = (new InternalRequest())
            ->withPageId(7)
            ->withQueryParameter('tx_seminars_myregistrations[action]', 'downloadAttendeeAttachment')
            ->withQueryParameter('tx_seminars_myregistrations[controller]', 'MyRegistrations')
            ->withQueryParameter('tx_seminars_myregistrations[fileUid]', 1)
            ->withQueryParameter('tx_seminars_myregistrations[registration]', 1);
        $requestContext = (new InternalRequestContext())->withFrontendUserId(1);

        $responseBody = (string)$this->executeFrontendSubRequest($request, $requestContext)->getBody();

        $expectedFileContents = \file_get_contents(
            self::FIXTURES_PATH . '/downloadAttendeeAttachmentAction/fileadmin/speaker.txt',
        );
        self::assertSame($expectedFileContents, $responseBody);
    }
}
