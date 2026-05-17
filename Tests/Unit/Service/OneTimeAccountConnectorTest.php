<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Service;

use OliverKlee\Seminars\Service\OneTimeAccountConnector;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\OneTimeAccountConnector
 */
final class OneTimeAccountConnectorTest extends UnitTestCase
{
    private OneTimeAccountConnector $subject;

    /**
     * @var FrontendUserAuthentication&MockObject
     */
    private FrontendUserAuthentication $frontEndUserAuthenticationMock;

    protected function setUp(): void
    {
        parent::setUp();

        $mockFrontEndController = $this->createMock(TypoScriptFrontendController::class);
        $this->frontEndUserAuthenticationMock = $this->createMock(FrontendUserAuthentication::class);
        $mockFrontEndController->fe_user = $this->frontEndUserAuthenticationMock;

        $request = (new ServerRequest())->withAttribute('frontend.user', $this->frontEndUserAuthenticationMock);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $this->subject = new OneTimeAccountConnector();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getOneTimeAccountUserUidForNullUserUidReturnsNull(): void
    {
        $this->frontEndUserAuthenticationMock
            ->method('getSessionData')->with('onetimeaccountUserUid')
            ->willReturn(null);

        self::assertNull($this->subject->getOneTimeAccountUserUid());
    }

    /**
     * @test
     */
    public function getOneTimeAccountUserUidForEmptyUserUidReturnsNull(): void
    {
        $this->frontEndUserAuthenticationMock->method('getSessionData')->with('onetimeaccountUserUid')->willReturn('');

        self::assertNull($this->subject->getOneTimeAccountUserUid());
    }

    /**
     * @test
     */
    public function getOneTimeAccountUserUidForZeroUserUidReturnsNull(): void
    {
        $this->frontEndUserAuthenticationMock->method('getSessionData')->with('onetimeaccountUserUid')->willReturn(0);

        self::assertNull($this->subject->getOneTimeAccountUserUid());
    }

    /**
     * @test
     */
    public function getOneTimeAccountUserUidForPositiveUserUidReturnsUserUid(): void
    {
        $userUid = 63;
        $this->frontEndUserAuthenticationMock
            ->method('getSessionData')
            ->with('onetimeaccountUserUid')->willReturn($userUid);

        self::assertSame($userUid, $this->subject->getOneTimeAccountUserUid());
    }

    /**
     * @test
     */
    public function destroyOneTimeSessionForRegularLoginDoesSetAnySessionDate(): void
    {
        $this->frontEndUserAuthenticationMock->method('getKey')->with('user', 'onetimeaccount')->willReturn(null);

        $this->frontEndUserAuthenticationMock->expects(self::never())->method('setAndSaveSessionData');

        $this->subject->destroyOneTimeSession();
    }

    /**
     * @test
     */
    public function destroyOneTimeSessionForOneTimeSessionWithoutLoginRemovesUserUidFromSession(): void
    {
        $this->frontEndUserAuthenticationMock->method('getKey')->with('user', 'onetimeaccount')->willReturn(null);
        $this->frontEndUserAuthenticationMock
            ->method('getSessionData')->with('onetimeaccountUserUid')
            ->willReturn(5);

        $this->frontEndUserAuthenticationMock
            ->expects(self::once())->method('setAndSaveSessionData')
            ->with('onetimeaccountUserUid', null);

        $this->subject->destroyOneTimeSession();
    }

    /**
     * @test
     */
    public function destroyOneTimeSessionForRegularLoginAndOneTimeSessionRemovesUserUidFromSession(): void
    {
        $this->frontEndUserAuthenticationMock->method('getKey')->with('user', 'onetimeaccount')->willReturn(true);
        $this->frontEndUserAuthenticationMock
            ->method('getSessionData')->with('onetimeaccountUserUid')
            ->willReturn(5);

        $this->frontEndUserAuthenticationMock
            ->expects(self::once())->method('setAndSaveSessionData')
            ->with('onetimeaccountUserUid', null);

        $this->subject->destroyOneTimeSession();
    }
}
