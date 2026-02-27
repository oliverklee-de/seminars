<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Controller\BackEnd;

use OliverKlee\Seminars\BackEnd\EmailService;
use OliverKlee\Seminars\BackEnd\Permissions;
use OliverKlee\Seminars\Controller\BackEnd\EmailController;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\EventTopic;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Tests\Unit\Controller\RedirectMockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\BackEnd\EmailController
 */
final class EmailControllerTest extends UnitTestCase
{
    use BackEndControllerTestHelper;
    use RedirectMockTrait;

    protected bool $resetSingletonInstances = true;

    /**
     * @var EmailController&MockObject&AccessibleObjectInterface
     */
    private EmailController $subject;

    /**
     * @var TemplateView&MockObject
     */
    private TemplateView $viewMock;

    /**
     * @var Permissions&MockObject
     */
    private Permissions $permissionsMock;

    /**
     * @var EmailService&MockObject
     */
    private EmailService $emailServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::markTestSkipped('These tests need to be reworked to work with TYPO3 v12.');
        }

        $moduleTemplateFactory = $this->createModuleTemplateFactory();
        $this->permissionsMock = $this->createMock(Permissions::class);
        $this->emailServiceMock = $this->createMock(EmailService::class);

        $methodsToMock = ['htmlResponse', 'redirect', 'redirectToUri'];
        /** @var EmailController&AccessibleObjectInterface&MockObject $subject */
        $subject = $this->getAccessibleMock(
            EmailController::class,
            $methodsToMock,
            [$moduleTemplateFactory, $this->permissionsMock, $this->emailServiceMock],
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

    public function tearDown(): void
    {
        GeneralUtility::purgeInstances();

        unset($GLOBALS['BE_USER'], $GLOBALS['LANG']);

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
    public function composeActionWithoutReadPermissionsForEventsThrowsException(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(false);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1671020157);
        $this->expectExceptionMessage('Missing read permissions for events.');

        $this->subject->composeAction(new SingleEvent(), 1);
    }

    /**
     * @test
     */
    public function composeActionWithoutReadPermissionsForRegistrationsThrowsException(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1671020198);
        $this->expectExceptionMessage('Missing read permissions for registrations.');

        $this->subject->composeAction(new SingleEvent(), 1);
    }

    /**
     * @test
     */
    public function composeActionReturnsHtmlResponse(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $event = new SingleEvent();
        $result = $this->subject->composeAction($event, 1);

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function composeActionPassesProvidedEventToView(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $event = new SingleEvent();
        $this->viewMock->expects(self::exactly(4))->method('assign')->withConsecutive(
            ['event', $event],
            ['pageUid', self::anything()],
            ['subject', ''],
            ['body', self::anything()],
        );

        $this->subject->composeAction($event, 1);
    }

    /**
     * @test
     */
    public function composeActionPassesProvidedPageUidToView(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $pageUid = 5;
        $this->viewMock->expects(self::exactly(4))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['pageUid', $pageUid],
            ['subject', ''],
            ['body', self::anything()],
        );

        $this->subject->composeAction(new SingleEvent(), $pageUid);
    }

    /**
     * @test
     */
    public function composeActionWithoutSubjectOrBodyPassesEmptySubjectToView(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $this->viewMock->expects(self::exactly(4))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['pageUid', self::anything()],
            ['subject', ''],
            ['body', self::anything()],
        );

        $this->subject->composeAction(new SingleEvent(), 1);
    }

    /**
     * @test
     */
    public function composeActionWithoutSubjectOrBodyPassesEmptyBodyToView(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $this->viewMock->expects(self::exactly(4))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['pageUid', self::anything()],
            ['subject', self::anything()],
            ['body', ''],
        );

        $this->subject->composeAction(new SingleEvent(), 1);
    }

    /**
     * @test
     */
    public function composeActionPassesProvidedSubjectToView(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $subject = 'Test subject';
        $this->viewMock->expects(self::exactly(4))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['pageUid', self::anything()],
            ['subject', $subject],
            ['body', self::anything()],
        );

        $this->subject->composeAction(new SingleEvent(), 1, $subject, '');
    }

    /**
     * @test
     */
    public function composeActionPassesProvidedBodyToView(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $body = 'Test body';
        $this->viewMock->expects(self::exactly(4))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['pageUid', self::anything()],
            ['subject', self::anything()],
            ['body', $body],
        );

        $this->subject->composeAction(new SingleEvent(), 1, '', $body);
    }

    /**
     * @test
     */
    public function sendActionWithoutReadPermissionsForEventsThrowsException(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(false);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1671020157);
        $this->expectExceptionMessage('Missing read permissions for events.');

        $this->subject->sendAction(new SingleEvent(), '', '');
    }

    /**
     * @test
     */
    public function sendActionWithoutReadPermissionsForRegistrationsThrowsException(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1671020198);
        $this->expectExceptionMessage('Missing read permissions for registrations.');

        $this->subject->sendAction(new SingleEvent(), '', '');
    }

    /**
     * @test
     */
    public function sendActionWithSingleEventSendsEmailWithProvidedSubjectAndBody(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);
        $this->stubRedirect();

        $event = new SingleEvent();
        $subject = 'email subject';
        $body = 'email body';

        $this->emailServiceMock
            ->expects(self::once())->method('sendPlainTextEmailToRegularAttendees')
            ->with($event, $subject, $body);

        $this->subject->sendAction($event, $subject, $body);
    }

    /**
     * @test
     */
    public function sendActionWithEventDateSendsEmailWithProvidedSubjectAndBody(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);
        $this->stubRedirect();

        $event = new EventDate();
        $subject = 'email subject';
        $body = 'email body';

        $this->emailServiceMock
            ->expects(self::once())->method('sendPlainTextEmailToRegularAttendees')
            ->with($event, $subject, $body);

        $this->subject->sendAction($event, $subject, $body);
    }

    /**
     * @test
     */
    public function sendActionWithEventTopicDoesNotSendEmail(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);
        $this->stubRedirect();

        $event = new EventTopic();
        $subject = 'email subject';
        $body = 'email body';

        $this->emailServiceMock->expects(self::never())->method('sendPlainTextEmailToRegularAttendees');

        $this->subject->sendAction($event, $subject, $body);
    }

    /**
     * @test
     */
    public function sendActionRedirectsToOverview(): void
    {
        $this->permissionsMock->method('hasReadAccessToEvents')->willReturn(true);
        $this->permissionsMock->method('hasReadAccessToRegistrations')->willReturn(true);

        $event = new SingleEvent();

        $this->mockRedirect('overview', 'BackEnd\\Module');

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->sendAction($event, '', '');
        } else {
            $result = $this->subject->sendAction($event, '', '');
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }
}
