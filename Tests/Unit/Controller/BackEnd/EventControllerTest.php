<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Controller\BackEnd\EventController;
use OliverKlee\Seminars\Csv\CsvDownloader;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use OliverKlee\Seminars\Tests\Unit\Controller\RedirectMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\BackEnd\EventController
 */
final class EventControllerTest extends UnitTestCase
{
    use BackEndControllerTestHelper;
    use RedirectMockTrait;

    protected bool $resetSingletonInstances = true;

    /**
     * @var EventController&MockObject&AccessibleObjectInterface
     */
    private EventController $subject;

    /**
     * @var TemplateView&MockObject
     */
    private TemplateView $viewMock;

    /**
     * @var EventRepository&MockObject
     */
    private EventRepository $eventRepositoryMock;

    /**
     * @var Permissions&MockObject
     */
    private Permissions $permissionsMock;

    /**
     * @var EventStatisticsCalculator&MockObject
     */
    private EventStatisticsCalculator $eventStatisticsCalculatorMock;

    /**
     * @var PageRenderer&MockObject
     */
    private PageRenderer $pageRendererMock;

    protected function setUp(): void
    {
        parent::setUp();

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('These tests need to be reworked to work with TYPO3 v12.');
        }

        $moduleTemplateFactory = $this->createModuleTemplateFactory();
        $this->eventRepositoryMock = $this->createMock(EventRepository::class);
        $this->permissionsMock = $this->createMock(Permissions::class);
        $this->eventStatisticsCalculatorMock = $this->createMock(EventStatisticsCalculator::class);
        $this->pageRendererMock = $this->createMock(PageRenderer::class);

        $methodsToMock = ['addFlashMessage', 'htmlResponse', 'redirect', 'redirectToUri'];
        /** @var EventController&AccessibleObjectInterface&MockObject $subject */
        $subject = $this->getAccessibleMock(
            EventController::class,
            $methodsToMock,
            [
                $moduleTemplateFactory,
                $this->eventRepositoryMock,
                $this->permissionsMock,
                $this->eventStatisticsCalculatorMock,
                $this->pageRendererMock,
            ],
        );
        $this->subject = $subject;

        $request = $this->createStub(ServerRequest::class);
        $this->subject->_set('request', $request);

        $responseStub = $this->createStub(HtmlResponse::class);
        $this->subject->method('htmlResponse')->willReturn($responseStub);

        $this->viewMock = $this->createMock(TemplateView::class);
        $this->viewMock->method('render')->willReturn('rendered view');
        $this->subject->_set('view', $this->viewMock);

        $csvDownloaderMock = $this->createMock(CsvDownloader::class);
        GeneralUtility::addInstance(CsvDownloader::class, $csvDownloaderMock);
    }

    protected function tearDown(): void
    {
        unset($_GET['id'], $GLOBALS['LANG'], $GLOBALS['BE_USER']);
        GeneralUtility::purgeInstances();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function isActionController(): void
    {
        self::assertInstanceOf(ActionController::class, $this->subject);
    }

    /**
     * @test
     */
    public function hideActionHidesEvent(): void
    {
        $uid = 15;
        $this->stubRedirect();

        $this->eventRepositoryMock->expects(self::once())->method('hideViaDataHandler')->with($uid);

        $this->subject->hideAction($uid);
    }

    /**
     * @test
     */
    public function hideActionRedirectsToModuleOverviewAction(): void
    {
        $this->mockRedirect('overview', 'BackEnd\\Module');

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->hideAction(15);
        } else {
            $result = $this->subject->hideAction(15);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function unhideActionUnhidesEvent(): void
    {
        $uid = 15;
        $this->stubRedirect();

        $this->eventRepositoryMock->expects(self::once())->method('unhideViaDataHandler')->with($uid);

        $this->subject->unhideAction($uid);
    }

    /**
     * @test
     */
    public function unhideActionRedirectsToModuleOverviewAction(): void
    {
        $this->mockRedirect('overview', 'BackEnd\\Module');

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->unhideAction(15);
        } else {
            $result = $this->subject->unhideAction(15);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function deleteActionDeletesEvent(): void
    {
        $uid = 15;
        $this->stubRedirect();

        $this->eventRepositoryMock->expects(self::once())->method('deleteViaDataHandler')->with($uid);

        $this->subject->deleteAction($uid);
    }

    /**
     * @test
     */
    public function deleteActionAddsFlashMessage(): void
    {
        $localizedMessage = 'Event deleted!';
        $this->stubRedirect();

        $this->languageServiceMock
            ->expects(self::once())->method('sL')
            ->with('LLL:EXT:seminars/Resources/Private/Language/locallang.xlf:backEndModule.message.eventDeleted')
            ->willReturn($localizedMessage);
        $this->subject->expects(self::once())->method('addFlashMessage')->with($localizedMessage);

        $this->subject->deleteAction(15);
    }

    /**
     * @test
     */
    public function deleteActionRedirectsToModuleOverviewAction(): void
    {
        $this->mockRedirect('overview', 'BackEnd\\Module');

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->deleteAction(15);
        } else {
            $result = $this->subject->deleteAction(15);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function searchActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->searchAction(1, '');

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function searchActionPassesPermissionsToView(): void
    {
        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', $this->permissionsMock],
                ['pageUid', self::anything()],
                ['events', self::anything()],
                ['searchTerm', self::anything()],
            );

        $this->subject->searchAction(1, '');
    }

    /**
     * @test
     */
    public function searchActionPassesPageUidToView(): void
    {
        $pageUid = 8;

        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', $pageUid],
                ['events', self::anything()],
                ['searchTerm', self::anything()],
            );

        $this->subject->searchAction($pageUid, '');
    }

    /**
     * @test
     */
    public function searchActionPassesEventsOnPageUidWithSearchTermToView(): void
    {
        $pageUid = 8;
        $searchTerm = 'no dice';

        $events = [new SingleEvent()];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findBySearchTermInBackEndMode')
            ->with($pageUid, $searchTerm)->willReturn($events);
        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', self::anything()],
                ['events', $events],
                ['searchTerm', self::anything()],
            );

        $this->subject->searchAction($pageUid, $searchTerm);
    }

    /**
     * @test
     */
    public function searchActionForMissingSearchTermPassesEventsOnPageUidWithEmptySearchTermToView(): void
    {
        $pageUid = 8;

        $events = [new SingleEvent()];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findBySearchTermInBackEndMode')
            ->with($pageUid, '')->willReturn($events);
        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', self::anything()],
                ['events', $events],
                ['searchTerm', self::anything()],
            );

        $this->subject->searchAction($pageUid);
    }

    /**
     * @test
     */
    public function searchActionEnrichesEventsWithRawData(): void
    {
        $events = [new SingleEvent()];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findBySearchTermInBackEndMode')
            ->with(self::anything(), self::anything())->willReturn($events);
        $this->eventRepositoryMock
            ->expects(self::once())->method('enrichWithRawData')
            ->with($events);

        $this->subject->searchAction(1, '');
    }

    /**
     * @test
     */
    public function searchActionEnrichesEventsWithStatistics(): void
    {
        $event = new SingleEvent();
        $events = [$event];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findBySearchTermInBackEndMode')
            ->with(self::anything(), self::anything())->willReturn($events);
        $this->eventStatisticsCalculatorMock
            ->expects(self::once())->method('enrichWithStatistics')
            ->with($event);

        $this->subject->searchAction(1, '');
    }

    /**
     * @test
     */
    public function searchActionPassesTrimmedSearchTermToView(): void
    {
        $searchTerm = ' no dice ';

        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', self::anything()],
                ['events', self::anything()],
                ['searchTerm', \trim($searchTerm)],
            );

        $this->subject->searchAction(1, $searchTerm);
    }

    /**
     * @test
     */
    public function searchActionLoadsJavaScriptModule(): void
    {
        $this->pageRendererMock
            ->expects(self::once())->method('loadRequireJsModule')
            ->with('TYPO3/CMS/Seminars/BackEnd/DeleteConfirmation');

        $this->subject->searchAction(1, 'specatacular event');
    }

    /**
     * @test
     */
    public function duplicateActionDuplicatesEvent(): void
    {
        $uid = 15;
        $this->stubRedirect();

        $this->eventRepositoryMock->expects(self::once())->method('duplicateViaDataHandler')->with($uid);

        $this->subject->duplicateAction($uid);
    }

    /**
     * @test
     */
    public function duplicateActionRedirectsToModuleOverviewAction(): void
    {
        $this->mockRedirect('overview', 'BackEnd\\Module');

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->duplicateAction(15);
        } else {
            $result = $this->subject->duplicateAction(15);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }
}
