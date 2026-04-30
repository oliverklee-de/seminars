<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\FrontEnd\DefaultController;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Seo\SingleViewPageTitleProvider;
use OliverKlee\Seminars\Tests\Functional\FrontEnd\Fixtures\TestingDefaultController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\DefaultController
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class SingleViewTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private TestingDefaultController $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework('tx_seminars');
        $this->buildSubjectForSingleView();
    }

    private function buildSubjectForSingleView(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/PageStructure.csv');
        $this->testingFramework->createFakeFrontEnd(1);

        $frontEndController = $GLOBALS['TSFE'] ?? null;
        self::assertInstanceOf(TypoScriptFrontendController::class, $frontEndController);
        $subject = new TestingDefaultController();
        $subject->setContentObjectRenderer($frontEndController->cObj);
        $subject->init(
            [
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
                'what_to_display' => 'single_view',
            ],
        );

        $this->subject = $subject;
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function eventDataDataProvider(): array
    {
        return [
            'title' => ['event &amp; organizer'],
            'subtitle' => ['subtitle &amp; more'],
            'room' => ['Rooms 2 &amp; 3'],
            'accreditation number' => ['4 &amp; 5'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider eventDataDataProvider
     */
    public function singleViewContainsEncodedEventData(string $expected): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOrganizer.csv');
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        self::assertStringContainsString($expected, $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function organizerDataDataProvider(): array
    {
        return [
            'organizer name' => ['Rupf &amp; Knack Deckendienste'],
            'organizer description' => ['Best organizer!'],
            'linked organizer homepage' => ['href="https://www.example.com"'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider organizerDataDataProvider
     */
    public function singleViewContainsEncodedOrganizerData(string $expected): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOrganizer.csv');
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function singleViewProvidesPageTitleProviderWithEventTitleAsTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOrganizer.csv');

        $pageTitleProvider = new SingleViewPageTitleProvider();
        GeneralUtility::setSingletonInstance(SingleViewPageTitleProvider::class, $pageTitleProvider);

        $this->subject->piVars['showUid'] = '1';

        $this->subject->main('', []);

        self::assertSame('event & organizer', $pageTitleProvider->getTitle());
    }

    /**
     * @test
     */
    public function singleViewForEventWithoutVenuesDoesNotHaveVenueHeading(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithoutVenue.csv');
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        $expected = LocalizationUtility::translate('label_place', 'seminars');
        self::assertIsString($expected);
        self::assertStringNotContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function singleViewForEventWithVenueHasVenueHeading(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOneVenue.csv');
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        $expected = LocalizationUtility::translate('label_place', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function singleViewForEventWithVenueAndVenueDetailsEnabledHasEncodedVenueTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOneVenue.csv');
        $this->subject->setConfigurationValue('showSiteDetails', true);
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        self::assertStringContainsString('The great &amp; calm hotel', $result);
    }

    /**
     * @test
     */
    public function singleViewForEventWithVenueAndVenueDetailsDisabledHasEncodedVenueTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOneVenue.csv');
        $this->subject->setConfigurationValue('showSiteDetails', false);
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        self::assertStringContainsString('The great &amp; calm hotel', $result);
    }

    /**
     * @test
     */
    public function singleViewForEventWithVenueAndVenueDetailsEnabledHasEncodedVenueAddress(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleView/SingleEventWithOneVenue.csv');
        $this->subject->setConfigurationValue('showSiteDetails', true);
        $this->subject->piVars['showUid'] = '1';

        $result = $this->subject->main('', []);

        self::assertStringContainsString('over &amp; the rainbow', $result);
    }
}
