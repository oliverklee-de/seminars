<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Controller\BackEnd\ModuleController;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use OliverKlee\Seminars\Domain\Repository\Registration\RegistrationRepository;
use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use OliverKlee\Seminars\Tests\Unit\Controller\RedirectMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\BackEnd\ModuleController
 * @covers \OliverKlee\Seminars\Controller\BackEnd\PageUidTrait
 */
final class ModuleControllerTest extends UnitTestCase
{
    use BackEndControllerTestHelper;
    use RedirectMockTrait;

    protected bool $resetSingletonInstances = true;

    /**
     * @var ModuleController&MockObject&AccessibleObjectInterface
     */
    private ModuleController $subject;

    /**
     * @var TemplateView&MockObject
     */
    private TemplateView $viewMock;

    /**
     * @var Permissions&MockObject
     */
    private Permissions $permissionsMock;

    /**
     * @var PageRenderer&MockObject
     */
    private PageRenderer $pageRendererMock;

    /**
     * @var EventRepository&MockObject
     */
    private EventRepository $eventRepositoryMock;

    /**
     * @var RegistrationRepository&MockObject
     */
    private RegistrationRepository $registrationRepositoryMock;

    /**
     * @var EventStatisticsCalculator&MockObject
     */
    private EventStatisticsCalculator $eventStatisticsCalculatorMock;

    protected function setUp(): void
    {
        parent::setUp();

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('These tests need to be reworked to work with TYPO3 v12.');
        }

        $moduleTemplateFactory = $this->createModuleTemplateFactory();
        $this->eventRepositoryMock = $this->createMock(EventRepository::class);
        $this->registrationRepositoryMock = $this->createMock(RegistrationRepository::class);
        $this->eventStatisticsCalculatorMock = $this->createMock(EventStatisticsCalculator::class);
        $this->permissionsMock = $this->createMock(Permissions::class);
        $this->pageRendererMock = $this->createMock(PageRenderer::class);

        $methodsToMock = ['htmlResponse', 'redirect', 'redirectToUri'];
        /** @var ModuleController&AccessibleObjectInterface&MockObject $subject */
        $subject = $this->getAccessibleMock(
            ModuleController::class,
            $methodsToMock,
            [
                $moduleTemplateFactory,
                $this->eventRepositoryMock,
                $this->registrationRepositoryMock,
                $this->eventStatisticsCalculatorMock,
                $this->permissionsMock,
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
    }

    protected function tearDown(): void
    {
        unset($_GET['id'], $_GET['pid'], $GLOBALS['LANG'], $GLOBALS['BE_USER']);
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
    public function pageUidIsTakenFromGetId(): void
    {
        $pageUid = 15;
        $_GET['id'] = (string)$pageUid;

        self::assertSame($pageUid, $this->subject->getPageUid());
    }

    /**
     * @test
     */
    public function pageUidIsTakenFromPostId(): void
    {
        $pageUid = 15;
        $_GET['id'] = (string)$pageUid;

        self::assertSame($pageUid, $this->subject->getPageUid());
    }

    /**
     * @test
     */
    public function pageUidForNoIdInRequestIsZero(): void
    {
        self::assertSame(0, $this->subject->getPageUid());
    }

    /**
     * @test
     */
    public function overviewActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->overviewAction();

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function overviewActionPassesPermissionsToView(): void
    {
        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', $this->permissionsMock],
                ['pageUid', self::anything()],
                ['events', self::anything()],
                ['numberOfRegistrations', self::anything()],
            );

        $this->subject->overviewAction();
    }

    /**
     * @test
     */
    public function overviewActionPassesPageUidToView(): void
    {
        $pageUid = 8;
        $_GET['id'] = (string)$pageUid;

        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', $pageUid],
                ['events', self::anything()],
                ['numberOfRegistrations', self::anything()],
            );

        $this->subject->overviewAction();
    }

    /**
     * @test
     */
    public function overviewActionEnrichesEventsWithRawData(): void
    {
        $events = [new SingleEvent()];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findByPageUidInBackEndMode')
            ->with(self::anything())->willReturn($events);
        $this->eventRepositoryMock
            ->expects(self::once())->method('enrichWithRawData')
            ->with($events);

        $this->subject->overviewAction();
    }

    /**
     * @test
     */
    public function overviewActionEnrichesEventsWithStatistics(): void
    {
        $event = new SingleEvent();
        $events = [$event];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findByPageUidInBackEndMode')
            ->with(self::anything())->willReturn($events);
        $this->eventStatisticsCalculatorMock
            ->expects(self::once())->method('enrichWithStatistics')
            ->with($event);

        $this->subject->overviewAction();
    }

    /**
     * @test
     */
    public function overviewActionPassesEventsOnPageUidToView(): void
    {
        $pageUid = 8;
        $_GET['id'] = (string)$pageUid;

        $events = [new SingleEvent()];
        $this->eventRepositoryMock
            ->expects(self::once())->method('findByPageUidInBackEndMode')
            ->with($pageUid)->willReturn($events);
        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', self::anything()],
                ['events', $events],
                ['numberOfRegistrations', self::anything()],
            );

        $this->subject->overviewAction();
    }

    /**
     * @test
     */
    public function overviewActionPassesNumberOfRegistrationsOnPageUidToView(): void
    {
        $pageUid = 8;
        $_GET['id'] = (string)$pageUid;

        $numberOfRegistrations = 42;
        $this->registrationRepositoryMock
            ->expects(self::once())->method('countRegularRegistrationsByPageUid')
            ->with($pageUid)->willReturn($numberOfRegistrations);
        $this->viewMock
            ->expects(self::exactly(4))->method('assign')
            ->withConsecutive(
                ['permissions', self::anything()],
                ['pageUid', self::anything()],
                ['events', self::anything()],
                ['numberOfRegistrations', $numberOfRegistrations],
            );

        $this->subject->overviewAction();
    }

    /**
     * @test
     */
    public function overviewActionLoadsJavaScriptModule(): void
    {
        $this->pageRendererMock
            ->expects(self::once())->method('loadRequireJsModule')
            ->with('TYPO3/CMS/Seminars/BackEnd/DeleteConfirmationAmdModule');

        $this->subject->overviewAction();
    }
}
