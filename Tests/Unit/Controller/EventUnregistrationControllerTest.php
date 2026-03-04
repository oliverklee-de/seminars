<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Controller;

use OliverKlee\Seminars\Configuration\LegacyConfiguration;
use OliverKlee\Seminars\Controller\EventUnregistrationController;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Service\RegistrationManager;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\EventUnregistrationController
 */
final class EventUnregistrationControllerTest extends UnitTestCase
{
    use RedirectMockTrait;

    protected bool $resetSingletonInstances = true;

    /**
     * @var EventUnregistrationController&MockObject&AccessibleObjectInterface
     */
    private EventUnregistrationController $subject;

    /**
     * @var TemplateView&MockObject
     */
    private TemplateView $viewMock;

    /**
     * @var RegistrationManager&MockObject
     */
    private RegistrationManager $registrationManagerMock;

    private Context $context;

    /**
     * @var LegacyRegistration&MockObject
     */
    private LegacyRegistration $legacyRegistrationMock;

    /**
     * @var LegacyConfiguration&MockObject
     */
    private LegacyConfiguration $legacyConfigurationMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrationManagerMock = $this->createMock(RegistrationManager::class);
        $this->context = new Context();

        /** @var EventUnregistrationController&AccessibleObjectInterface&MockObject $subject */
        $subject = $this->getAccessibleMock(
            EventUnregistrationController::class,
            ['htmlResponse', 'redirect', 'redirectToUri'],
            [$this->registrationManagerMock, $this->context],
        );
        $this->subject = $subject;

        $responseStub = $this->createStub(HtmlResponse::class);
        $this->subject->method('htmlResponse')->willReturn($responseStub);

        $this->viewMock = $this->createMock(TemplateView::class);
        $this->subject->_set('view', $this->viewMock);

        $this->legacyRegistrationMock = $this->createMock(LegacyRegistration::class);
        GeneralUtility::addInstance(LegacyRegistration::class, $this->legacyRegistrationMock);
        $this->legacyConfigurationMock = $this->createMock(LegacyConfiguration::class);
        GeneralUtility::addInstance(LegacyConfiguration::class, $this->legacyConfigurationMock);
    }

    protected function tearDown(): void
    {
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
    public function checkPrerequisitesActionWithoutRegistrationForwardsToDenyAction(): void
    {
        $result = $this->subject->checkPrerequisitesAction();

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'registrationMissing'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithNullRegistrationForwardsToDenyAction(): void
    {
        $result = $this->subject->checkPrerequisitesAction(null);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'registrationMissing'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithRegistrationWithoutUserForwardsToDenyAction(): void
    {
        $result = $this->subject->checkPrerequisitesAction(new Registration());

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'registrationMissing'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithoutLoginForwardsToDenyAction(): void
    {
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn(3);
        $registration = new Registration();
        $registration->setUser($user);
        $this->context->setAspect('frontend.user', new UserAspect());

        $result = $this->subject->checkPrerequisitesAction($registration);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'registrationMissing'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithRegistrationByAnotherUserForwardsToDenyAction(): void
    {
        $registeredUserUid = 3;
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn($registeredUserUid);
        $registration = new Registration();
        $registration->setUser($user);

        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => 15];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));

        $result = $this->subject->checkPrerequisitesAction($registration);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'registrationMissing'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithRegistrationNotPossibleForwardsToDenyAction(): void
    {
        $registeredUserUid = 3;
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn($registeredUserUid);
        $registration = new Registration();
        $registration->setUser($user);

        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => $registeredUserUid];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));

        $legacyEvent = $this->createMock(LegacyEvent::class);
        $this->legacyRegistrationMock->expects(self::once())->method('getSeminarObject')->willReturn($legacyEvent);
        $legacyEvent->expects(self::once())->method('isUnregistrationPossible')->willReturn(false);

        $result = $this->subject->checkPrerequisitesAction($registration);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'noUnregistrationPossible'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithRegistrationPossibleRedirectsToConfirmAction(): void
    {
        $registeredUserUid = 3;
        $user = $this->createMock(FrontendUser::class);
        $user->method('getUid')->willReturn($registeredUserUid);
        $registration = new Registration();
        $registration->setUser($user);

        $userAuthentication = new FrontendUserAuthentication();
        $userAuthentication->user = ['uid' => $registeredUserUid];
        $this->context->setAspect('frontend.user', new UserAspect($userAuthentication));

        $legacyEvent = $this->createMock(LegacyEvent::class);
        $this->legacyRegistrationMock->expects(self::once())->method('getSeminarObject')->willReturn($legacyEvent);
        $legacyEvent->expects(self::once())->method('isUnregistrationPossible')->willReturn(true);

        $this->mockRedirect('confirm', null, null, ['registration' => $registration]);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction($registration);
        } else {
            $result = $this->subject->checkPrerequisitesAction($registration);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function denyActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->denyAction('registrationMissing');

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function denyActionPassesProvidedWarningMessageKeyToView(): void
    {
        $warningMessageKey = 'registrationMissing';
        $this->viewMock->expects(self::once())->method('assign')->with('warningMessageKey', $warningMessageKey);

        $this->subject->denyAction($warningMessageKey);
    }

    /**
     * @test
     */
    public function confirmActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->confirmAction(new Registration());

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function confirmActionPassesProvidedRegistrationToView(): void
    {
        $registration = new Registration();

        $this->viewMock->expects(self::once())->method('assign')->with('registration', $registration);

        $this->subject->confirmAction($registration);
    }

    /**
     * @test
     */
    public function unregisterActionRemovesRegistration(): void
    {
        $registrationUid = 4;
        $registration = $this->createMock(Registration::class);
        $registration->method('getUid')->willReturn($registrationUid);
        $this->stubRedirect();

        $this->registrationManagerMock
            ->expects(self::once())
            ->method('removeRegistration')->with($registrationUid, $this->legacyConfigurationMock);

        $this->subject->unregisterAction($registration);
    }

    /**
     * @test
     */
    public function unregisterActionRedirectsToThankYouActionWithEventFromRegistration(): void
    {
        $registration = new Registration();
        $event = new SingleEvent();
        $registration->setEvent($event);

        $this->mockRedirect('thankYou', null, null, ['event' => $event]);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->unregisterAction($registration);
        } else {
            $result = $this->subject->unregisterAction($registration);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function thankYouActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->thankYouAction(new SingleEvent());

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function thankYouActionPassesProvidedRegistrationToView(): void
    {
        $event = new SingleEvent();

        $this->viewMock->expects(self::once())->method('assign')->with('event', $event);

        $this->subject->thankYouAction($event);
    }
}
