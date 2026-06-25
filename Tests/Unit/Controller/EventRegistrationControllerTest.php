<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Controller;

use OliverKlee\Seminars\Controller\EventRegistrationController;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Service\OneTimeAccountConnector;
use OliverKlee\Seminars\Service\PriceFinder;
use OliverKlee\Seminars\Service\RegistrationGuard;
use OliverKlee\Seminars\Service\RegistrationProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\EventRegistrationController
 */
final class EventRegistrationControllerTest extends UnitTestCase
{
    use RedirectMockTrait;

    /**
     * @var EventRegistrationController&MockObject&AccessibleObjectInterface
     */
    private EventRegistrationController $subject;

    /**
     * @var RegistrationGuard&MockObject
     */
    private RegistrationGuard $registrationGuardMock;

    /**
     * @var RegistrationProcessor&MockObject
     */
    private RegistrationProcessor $registrationProcesserMock;

    /**
     * @var OneTimeAccountConnector&MockObject
     */
    private OneTimeAccountConnector $oneTimeAccountConnectorMock;

    /**
     * @var PriceFinder&MockObject
     */
    private PriceFinder $priceFinderMock;

    /**
     * @var TemplateView&MockObject
     */
    private TemplateView $viewMock;

    /**
     * @var UriBuilder&MockObject
     */
    private UriBuilder $uriBuilderMock;

    /**
     * @var Request&Stub
     */
    private Request $requestStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrationGuardMock = $this->createMock(RegistrationGuard::class);
        $this->registrationProcesserMock = $this->createMock(RegistrationProcessor::class);
        $this->oneTimeAccountConnectorMock = $this->createMock(OneTimeAccountConnector::class);
        $this->priceFinderMock = $this->createMock(PriceFinder::class);
        $this->requestStub = $this->createStub(Request::class);

        /** @var EventRegistrationController&AccessibleObjectInterface&MockObject $subject */
        $subject = $this->getAccessibleMock(
            EventRegistrationController::class,
            ['htmlResponse', 'redirect', 'redirectToUri'],
            [
                $this->registrationGuardMock,
                $this->registrationProcesserMock,
                $this->oneTimeAccountConnectorMock,
                $this->priceFinderMock,
            ],
        );
        $this->subject = $subject;

        $responseStub = $this->createStub(HtmlResponse::class);
        $this->subject->method('htmlResponse')->willReturn($responseStub);

        $this->viewMock = $this->createMock(TemplateView::class);
        $this->subject->_set('view', $this->viewMock);
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        $this->subject->_set('uriBuilder', $this->uriBuilderMock);
        $this->subject->_set('request', $this->requestStub);
        $this->subject->_set('settings', []);
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
    public function checkPrerequisitesActionWithoutEventRedirectsToMissingEventRedirectPage(): void
    {
        $pageUid = 42;
        $this->subject->_set('settings', ['pageForMissingEvent' => (string)$pageUid]);

        $this->mockRedirect(null, null, null, [], $pageUid);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction();
        } else {
            $result = $this->subject->checkPrerequisitesAction();
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionWithNullEventRedirectsToMissingEventRedirectPage(): void
    {
        $pageUid = 42;
        $this->subject->_set('settings', ['pageForMissingEvent' => (string)$pageUid]);

        $this->mockRedirect(null, null, null, [], $pageUid);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction(null);
        } else {
            $result = $this->subject->checkPrerequisitesAction(null);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForNoRegistrationPossibleAtAllForwardsToDenyAction(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock
            ->expects(self::once())->method('isRegistrationPossibleAtAnyTimeAtAll')
            ->with($event)->willReturn(false);

        $result = $this->subject->checkPrerequisitesAction($event);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'noRegistrationPossibleAtAll'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForNoRegistrationPossibleAtTheMomentForwardsToDenyAction(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock
            ->method('isRegistrationPossibleAtAnyTimeAtAll')
            ->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('isRegistrationPossibleByDate')
            ->with($event)->willReturn(false);

        $result = $this->subject->checkPrerequisitesAction($event);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'noRegistrationPossibleAtTheMoment'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForUserAlreadyRegisteredForwardsToDenyAction(): void
    {
        $userUid = 17;
        $this->registrationGuardMock
            ->method('getFrontEndUserUidFromSession')
            ->with($this->requestStub)->willReturn($userUid);

        $event = new SingleEvent();
        $this->registrationGuardMock->method('isRegistrationPossibleAtAnyTimeAtAll')->with($event)->willReturn(true);
        $this->registrationGuardMock->method('isRegistrationPossibleByDate')->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->method('existsFrontEndUserUidInSession')
            ->with($this->requestStub)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())
            ->method('isFreeFromRegistrationConflicts')->with($event, $userUid)->willReturn(false);

        $result = $this->subject->checkPrerequisitesAction($event);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'alreadyRegistered'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForFullyBookedEventWithoutWaitingListForwardsToDenyAction(): void
    {
        $userUid = 17;
        $this->registrationGuardMock
            ->method('getFrontEndUserUidFromSession')
            ->with($this->requestStub)->willReturn($userUid);

        $event = new SingleEvent();
        $this->registrationGuardMock->method('isRegistrationPossibleAtAnyTimeAtAll')->with($event)->willReturn(true);
        $this->registrationGuardMock->method('isRegistrationPossibleByDate')->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->method('existsFrontEndUserUidInSession')
            ->with($this->requestStub)->willReturn(true);
        $this->registrationGuardMock
            ->method('isFreeFromRegistrationConflicts')
            ->with($event, $userUid)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())
            ->method('getVacancies')->with($event)->willReturn(0);

        $result = $this->subject->checkPrerequisitesAction($event);

        self::assertInstanceOf(ForwardResponse::class, $result);
        self::assertSame('deny', $result->getActionName());
        self::assertSame(['warningMessageKey' => 'fullyBooked'], $result->getArguments());
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForFullyBookedEventWithWaitingListRedirectsToNewActionAndPassesEvent(): void
    {
        $userUid = 17;
        $this->registrationGuardMock
            ->method('getFrontEndUserUidFromSession')
            ->with($this->requestStub)->willReturn($userUid);

        $event = new SingleEvent();
        $event->setWaitingList(true);
        $this->registrationGuardMock->method('isRegistrationPossibleAtAnyTimeAtAll')->with($event)->willReturn(true);
        $this->registrationGuardMock->method('isRegistrationPossibleByDate')->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->method('existsFrontEndUserUidInSession')
            ->with($this->requestStub)->willReturn(true);
        $this->registrationGuardMock
            ->method('isFreeFromRegistrationConflicts')
            ->with($event, $userUid)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())
            ->method('getVacancies')->with($event)->willReturn(0);

        $this->mockRedirect('new', null, null, ['event' => $event]);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction($event);
        } else {
            $result = $this->subject->checkPrerequisitesAction($event);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForNoProblemsAndInfiniteVacanciesRedirectsToNewActionAndPassesEvent(): void
    {
        $userUid = 17;
        $this->registrationGuardMock
            ->method('getFrontEndUserUidFromSession')
            ->with($this->requestStub)->willReturn($userUid);

        $event = new SingleEvent();
        $this->registrationGuardMock
            ->expects(self::once())->method('isRegistrationPossibleAtAnyTimeAtAll')
            ->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('isRegistrationPossibleByDate')
            ->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('existsFrontEndUserUidInSession')
            ->with($this->requestStub)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('isFreeFromRegistrationConflicts')
            ->with($event, $userUid)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())
            ->method('getVacancies')->with($event)->willReturn(null);

        $this->mockRedirect('new', null, null, ['event' => $event]);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction($event);
        } else {
            $result = $this->subject->checkPrerequisitesAction($event);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForNoProblemsAndNonZeroVacanciesRedirectsToNewActionAndPassesEvent(): void
    {
        $userUid = 17;
        $this->registrationGuardMock
            ->method('getFrontEndUserUidFromSession')
            ->with($this->requestStub)->willReturn($userUid);

        $event = new SingleEvent();
        $this->registrationGuardMock
            ->expects(self::once())->method('isRegistrationPossibleAtAnyTimeAtAll')
            ->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('isRegistrationPossibleByDate')
            ->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('existsFrontEndUserUidInSession')
            ->with($this->requestStub)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())->method('isFreeFromRegistrationConflicts')
            ->with($event, $userUid)->willReturn(true);
        $this->registrationGuardMock
            ->expects(self::once())
            ->method('getVacancies')->with($event)->willReturn(1);

        $this->mockRedirect('new', null, null, ['event' => $event]);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction($event);
        } else {
            $result = $this->subject->checkPrerequisitesAction($event);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function checkPrerequisitesActionForNoUserInSessionRedirectsToLoginPageWithRedirectUrl(): void
    {
        $eventUid = 5;
        $event = $this->createMock(SingleEvent::class);
        $event->method('getUid')->willReturn($eventUid);
        $this->registrationGuardMock->method('isRegistrationPossibleAtAnyTimeAtAll')->with($event)->willReturn(true);
        $this->registrationGuardMock->method('isRegistrationPossibleByDate')->with($event)->willReturn(true);
        $this->registrationGuardMock
            ->method('existsFrontEndUserUidInSession')
            ->with($this->requestStub)->willReturn(false);

        $redirectUrl = 'https://example.com/current-page';
        $loginPageUrl = 'https://example.com/login-with-event-uid';
        $loginPageUid = 17;
        $this->subject->_set('settings', ['loginPage' => (string)$loginPageUid]);

        $this->uriBuilderMock->expects(self::exactly(2))->method('reset')->willReturnSelf();
        $this->uriBuilderMock->expects(self::exactly(2))->method('setCreateAbsoluteUri')->with(true)->willReturnSelf();
        $this->uriBuilderMock->expects(self::once())->method('setTargetPageUid')->with($loginPageUid)->willReturnSelf();
        $this->uriBuilderMock->expects(self::exactly(2))->method('setArguments')->withConsecutive(
            [['tx_seminars_eventregistration[event]' => $eventUid]],
            [['redirect_url' => $redirectUrl]],
        )->willReturnSelf();
        $this->uriBuilderMock
            ->expects(self::exactly(2))->method('buildFrontendUri')
            ->willReturnOnConsecutiveCalls($redirectUrl, $loginPageUrl);

        $this->mockRedirectToUri($loginPageUrl);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->checkPrerequisitesAction($event);
        } else {
            $result = $this->subject->checkPrerequisitesAction($event);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function denyActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->denyAction('noRegistrationPossibleAtAll');

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function denyActionPassesProvidedWarningMessageKeyToView(): void
    {
        $warningMessageKey = 'noRegistrationPossibleAtAll';
        $this->viewMock->expects(self::once())->method('assign')->with('warningMessageKey', $warningMessageKey);

        $this->subject->denyAction($warningMessageKey);
    }

    /**
     * @test
     */
    public function newActionReturnsHtml(): void
    {
        $result = $this->subject->newAction(new SingleEvent(), new Registration());

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function newActionAssertsBookableEventType(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock->expects(self::once())->method('assertBookableEventType')->with($event);

        $this->subject->newAction(new SingleEvent(), new Registration());
    }

    /**
     * @test
     */
    public function newActionWithSingleEventPassesProvidedEventToView(): void
    {
        $event = new SingleEvent();

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithEventDatePassesProvidedEventToView(): void
    {
        $event = new EventDate();

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithNullRegistrationPassesNewRegistrationToView(): void
    {
        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $event = new SingleEvent();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, null);
    }

    /**
     * @test
     */
    public function newActionWithoutRegistrationPassesNewRegistrationToView(): void
    {
        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $event = new SingleEvent();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, null);
    }

    /**
     * @test
     */
    public function newActionWithoutRegistrationPassesUsesFirstApplicablePriceForNewRegistration(): void
    {
        $event = new SingleEvent();
        $price1 = new Price(100.0, 'labelKey', Price::PRICE_EARLY_BIRD);
        $price2 = new Price(75.0, 'labelKey', Price::PRICE_SPECIAL_EARLY_BIRD);
        $applicablePrices = [Price::PRICE_EARLY_BIRD => $price1, Price::PRICE_SPECIAL_EARLY_BIRD => $price2];
        $this->priceFinderMock
            ->expects(self::once())->method('findApplicablePrices')->with($event)
            ->willReturn($applicablePrices);

        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $this->subject->newAction($event);

        self::assertSame($price1->getPriceCode(), $registration->getPriceCode());
    }

    /**
     * @test
     */
    public function newActionWithoutSettingForMaximumBookableSeatsAndUnlimitedVacanciesPassesDefaultToView(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock->expects(self::once())->method('getVacancies')->with($event)->willReturn(null);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithoutSettingForMaximumBookableSeatsAndMoreThanDefaultVacanciesPassesDefaultToView(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock->expects(self::once())->method('getVacancies')->with($event)->willReturn(11);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithoutMaximumBookableSeatsSettingAndLessThanDefaultVacanciesPassesVacanciesToView(): void
    {
        $event = new SingleEvent();
        $vacancies = 9;
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn($vacancies);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => $vacancies,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithInfiniteVacanciesPassesMaximumBookableSeatsFromSettingsToView(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn(null);

        $maximumBookableSeats = 15;
        $this->subject->_set('settings', ['maximumBookableSeats' => (string)$maximumBookableSeats]);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => $maximumBookableSeats,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithMoreVacanciesPassesMaximumBookableSeatsFromSettingsToView(): void
    {
        $event = new SingleEvent();
        $vacancies = 16;
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn($vacancies);

        $maximumBookableSeats = 15;
        $this->subject->_set('settings', ['maximumBookableSeats' => (string)$maximumBookableSeats]);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => $maximumBookableSeats,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithFewerVacanciesPassesActualVacanciesAsBookableSeatsToView(): void
    {
        $event = new SingleEvent();
        $vacancies = 9;
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn($vacancies);

        $maximumBookableSeats = 15;
        $this->subject->_set('settings', ['maximumBookableSeats' => (string)$maximumBookableSeats]);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => $vacancies,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithZeroVacanciesAndNoWaitingListPassesZeroAsBookableSeatsToView(): void
    {
        $event = new SingleEvent();
        $vacancies = 0;
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn($vacancies);

        $maximumBookableSeats = 15;
        $this->subject->_set('settings', ['maximumBookableSeats' => (string)$maximumBookableSeats]);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => $vacancies,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithZeroVacanciesAndWaitingListPassesBookableFromSettingsSeatsToView(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(true);
        $vacancies = 0;
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn($vacancies);

        $maximumBookableSeats = 5;
        $this->subject->_set('settings', ['maximumBookableSeats' => (string)$maximumBookableSeats]);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => $maximumBookableSeats,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithZeroVacanciesAndWaitingListAndNoSettingsPassesDefaultBookableSeatsToView(): void
    {
        $event = new SingleEvent();
        $event->setWaitingList(true);
        $vacancies = 0;
        $this->registrationGuardMock
            ->expects(self::once())->method('getVacancies')
            ->with($event)->willReturn($vacancies);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => [],
            ],
        );

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionPassesApplicablePricesToView(): void
    {
        $event = new SingleEvent();
        $applicablePrices = [new Price(0.0, 'labelKey', Price::PRICE_STANDARD)];
        $this->priceFinderMock
            ->expects(self::once())->method('findApplicablePrices')->with($event)
            ->willReturn($applicablePrices);

        $registration = new Registration();
        $this->viewMock->expects(self::once())->method('assignMultiple')->with(
            [
                'event' => $event,
                'registration' => $registration,
                'maximumBookableSeats' => 10,
                'applicablePrices' => $applicablePrices,
            ],
        );

        $this->subject->newAction($event, new Registration());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function booleanDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * @test
     * @dataProvider booleanDataProvider
     */
    public function newActionWithoutRegistrationKeepsRegisteredThemselvesUnchanged(bool $registeredThemselves): void
    {
        $this->subject->_set('settings', ['registerThemselvesDefault' => ($registeredThemselves ? '1' : '0')]);

        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $this->subject->newAction(new SingleEvent());

        self::assertSame($registeredThemselves, $registration->hasRegisteredThemselves());
    }

    /**
     * @test
     */
    public function newActionWithRegistrationEnrichesRegistrationWithMetadata(): void
    {
        $event = new SingleEvent();
        $settings = ['registration' => ['registrationRecordsStorageFolder' => '5']];
        $this->subject->_set('settings', $settings);

        $registration = new Registration();
        $this->registrationProcesserMock
            ->expects(self::once())->method('enrichWithMetadata')
            ->with($registration, $event, $settings, $this->requestStub);

        $this->subject->newAction($event, $registration);
    }

    /**
     * @test
     */
    public function newActionWithNullRegistrationEnrichesNewRegistrationWithMetadata(): void
    {
        $event = new SingleEvent();
        $settings = ['registration' => ['registrationRecordsStorageFolder' => '5']];
        $this->subject->_set('settings', $settings);

        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $this->registrationProcesserMock
            ->expects(self::once())->method('enrichWithMetadata')
            ->with($registration, $event, $settings, $this->requestStub);

        $this->subject->newAction($event, null);
    }

    /**
     * @test
     */
    public function newActionWithoutRegistrationEnrichesNewRegistrationWithMetadata(): void
    {
        $event = new SingleEvent();
        $settings = ['registration' => ['registrationRecordsStorageFolder' => '5']];
        $this->subject->_set('settings', $settings);

        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $this->registrationProcesserMock
            ->expects(self::once())->method('enrichWithMetadata')
            ->with($registration, $event, $settings, $this->requestStub);

        $this->subject->newAction($event);
    }

    /**
     * @test
     */
    public function newActionWithoutRegistrationAndWithoutRegisteredThemselvesSettingSetsItToTrue(): void
    {
        $registration = new Registration();
        GeneralUtility::addInstance(Registration::class, $registration);

        $this->subject->newAction(new SingleEvent());

        self::assertTrue($registration->hasRegisteredThemselves());
    }

    /**
     * @test
     * @dataProvider booleanDataProvider
     */
    public function newActionWithoutRegistrationUsesRegisteredThemselvesFromSettings(bool $registeredThemselves): void
    {
        $registration = new Registration();
        $registration->setRegisteredThemselves($registeredThemselves);

        $this->subject->newAction(new SingleEvent(), $registration);

        self::assertSame($registeredThemselves, $registration->hasRegisteredThemselves());
    }

    /**
     * @test
     */
    public function confirmActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->confirmAction(new SingleEvent(), new Registration());

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function confirmActionAssertsBookableEventType(): void
    {
        $event = new SingleEvent();
        $this->registrationGuardMock->expects(self::once())->method('assertBookableEventType')->with($event);

        $this->subject->confirmAction($event, new Registration());
    }

    /**
     * @test
     */
    public function confirmActionEnrichesRegistrationWithMetadata(): void
    {
        $registration = new Registration();
        $event = new SingleEvent();
        $settings = ['registration' => ['registrationRecordsStorageFolder' => '5']];
        $this->subject->_set('settings', $settings);

        $this->registrationProcesserMock
            ->expects(self::once())->method('enrichWithMetadata')
            ->with($registration, $event, $settings, $this->requestStub);

        $this->subject->confirmAction($event, $registration);
    }

    /**
     * @test
     */
    public function confirmActionCalculatesTotalPrice(): void
    {
        $registration = new Registration();
        $this->registrationProcesserMock
            ->expects(self::once())->method('calculateTotalPrice')
            ->with($registration);

        $this->subject->confirmAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function confirmActionWithSingleEventPassesProvidedEventToView(): void
    {
        $event = new SingleEvent();

        $this->viewMock->expects(self::exactly(3))->method('assign')->withConsecutive(
            ['event', $event],
            ['registration', self::anything()],
            ['applicablePrices', self::anything()],
        );

        $this->subject->confirmAction($event, new Registration());
    }

    /**
     * @test
     */
    public function confirmActionWithEventDatePassesProvidedEventToView(): void
    {
        $event = new EventDate();

        $this->viewMock->expects(self::exactly(3))->method('assign')->withConsecutive(
            ['event', $event],
            ['registration', self::anything()],
            ['applicablePrices', self::anything()],
        );

        $this->subject->confirmAction($event, new Registration());
    }

    /**
     * @test
     */
    public function confirmActionPassesProvidedRegistrationToView(): void
    {
        $registration = new Registration();

        $this->viewMock->expects(self::exactly(3))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['registration', $registration],
            ['applicablePrices', self::anything()],
        );

        $this->subject->confirmAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function confirmActionPassesApplicablePricesToView(): void
    {
        $event = new SingleEvent();
        $applicablePrices = [new Price(0.0, 'labelKey', Price::PRICE_STANDARD)];
        $this->priceFinderMock
            ->expects(self::once())->method('findApplicablePrices')->with($event)
            ->willReturn($applicablePrices);

        $this->viewMock->expects(self::exactly(3))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['registration', self::anything()],
            ['applicablePrices', $applicablePrices],
        );

        $this->subject->confirmAction($event, new Registration());
    }

    /**
     * @test
     */
    public function createActionAssertsBookableEventType(): void
    {
        $event = new SingleEvent();
        $this->stubRedirect();

        $this->registrationGuardMock->expects(self::once())->method('assertBookableEventType')->with($event);

        $this->subject->createAction($event, new Registration());
    }

    /**
     * @test
     */
    public function createActionEnrichesRegistrationWithMetadata(): void
    {
        $registration = new Registration();
        $event = new SingleEvent();
        $settings = ['registration' => ['registrationRecordsStorageFolder' => '5']];
        $this->subject->_set('settings', $settings);
        $this->stubRedirect();

        $this->registrationProcesserMock
            ->expects(self::once())->method('enrichWithMetadata')
            ->with($registration, $event, $settings, $this->requestStub);

        $this->subject->createAction($event, $registration);
    }

    /**
     * @test
     */
    public function createActionCalculatesTotalPrice(): void
    {
        $registration = new Registration();
        $this->stubRedirect();

        $this->registrationProcesserMock
            ->expects(self::once())->method('calculateTotalPrice')
            ->with($registration);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createActionCreatesRegistrationTitle(): void
    {
        $registration = new Registration();
        $this->stubRedirect();

        $this->registrationProcesserMock->expects(self::once())->method('createTitle')->with($registration);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createActionWithoutUserStorageSettingCreatesAdditionalPersonsWithZeroStorageFolderUid(): void
    {
        $registration = new Registration();
        $this->stubRedirect();

        $this->registrationProcesserMock
            ->expects(self::once())->method('createAdditionalPersons')
            ->with($registration, 0);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createActionCreatesAdditionalPersonsWithUserStorageFolderUidFromSettings(): void
    {
        $folderUid = 15;
        $this->subject->_set('settings', ['additionalPersonsStorageFolder' => (string)$folderUid]);
        $registration = new Registration();
        $this->stubRedirect();

        $this->registrationProcesserMock
            ->expects(self::once())->method('createAdditionalPersons')
            ->with($registration, $folderUid);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createActionPersistsRegistration(): void
    {
        $registration = new Registration();
        $this->stubRedirect();

        $this->registrationProcesserMock->expects(self::once())->method('persist')->with($registration);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createActionSendsEmail(): void
    {
        $registration = new Registration();
        $this->stubRedirect();

        $this->registrationProcesserMock->expects(self::once())->method('sendEmails')->with($registration);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createDestroysOneTimeAccountSession(): void
    {
        $registration = new Registration();
        $this->stubRedirect();

        $this->oneTimeAccountConnectorMock
            ->expects(self::once())->method('destroyOneTimeSession')
            ->with($this->requestStub);

        $this->subject->createAction(new SingleEvent(), $registration);
    }

    /**
     * @test
     */
    public function createActionRedirectsToThankYouActionAndPassesEventAndRegistration(): void
    {
        $event = new SingleEvent();
        $registration = new Registration();

        $this->mockRedirect('thankYou', null, null, ['event' => $event, 'registration' => $registration]);

        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->subject->createAction($event, $registration);
        } else {
            $result = $this->subject->createAction($event, $registration);
            self::assertInstanceOf(RedirectResponse::class, $result);
        }
    }

    /**
     * @test
     */
    public function thankYouActionReturnsHtmlResponse(): void
    {
        $result = $this->subject->thankYouAction(new SingleEvent(), new Registration());

        self::assertInstanceOf(HtmlResponse::class, $result);
    }

    /**
     * @test
     */
    public function thankYouActionPassesProvidedEventToView(): void
    {
        $event = new SingleEvent();

        $this->viewMock->expects(self::exactly(2))->method('assign')->withConsecutive(
            ['event', $event],
            ['registration', self::anything()],
        );

        $this->subject->thankYouAction($event, new Registration());
    }

    /**
     * @test
     */
    public function thankYouActionPassesProvidedRegistrationToView(): void
    {
        $registration = new Registration();

        $this->viewMock->expects(self::exactly(2))->method('assign')->withConsecutive(
            ['event', self::anything()],
            ['registration', $registration],
        );

        $this->subject->thankYouAction(new SingleEvent(), $registration);
    }
}
