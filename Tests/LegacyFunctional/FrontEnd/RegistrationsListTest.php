<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\FrontEnd;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\FrontEnd\RegistrationsList;
use OliverKlee\Seminars\Middleware\ResponseHeadersModifier;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\AbstractView
 * @covers \OliverKlee\Seminars\FrontEnd\RegistrationsList
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class RegistrationsListTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RegistrationsList $subject;

    private TestingFramework $testingFramework;

    /**
     * @var positive-int the UID of a seminar to which the fixture relates
     */
    private int $seminarUid;

    /**
     * @var positive-int the UID of a front end user for testing purposes
     */
    private int $feUserUid;

    /**
     * @var positive-int the UID of a registration for testing purposes
     */
    private int $registrationUid;

    private ResponseHeadersModifier $responseHeadersModifier;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->testingFramework = new TestingFramework();
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);

        $this->getLanguageService();

        $this->responseHeadersModifier = new ResponseHeadersModifier();
        GeneralUtility::setSingletonInstance(ResponseHeadersModifier::class, $this->responseHeadersModifier);

        $this->seminarUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'title' => 'Test event & more',
                'attendees_max' => 10,
                'needs_registration' => 1,
            ],
        );

        $this->subject = new RegistrationsList(
            [
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
                'enableRegistration' => 1,
            ],
            'list_registrations',
            $this->seminarUid,
            $this->getFrontEndController()->cObj,
        );
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    ///////////////////////
    // Utility functions.
    ///////////////////////

    private function getFrontEndController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * Creates an FE user, registers them to the seminar with the UID in
     * $this->seminarUid and logs them in.
     *
     * Note: This function creates a registration record.
     */
    private function createLogInAndRegisterFrontEndUser(): void
    {
        $this->feUserUid = $this->testingFramework->createAndLoginFrontEndUser(
            '',
            ['name' => 'Tom & Jerry'],
        );
        $this->registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $this->feUserUid,
            ],
        );
    }

    /////////////////////////////////////
    // Tests for the utility functions.
    /////////////////////////////////////

    /**
     * @test
     */
    public function createLogInAndRegisterFrontEndUserLogsInFrontEndUser(): void
    {
        $this->createLogInAndRegisterFrontEndUser();

        self::assertTrue(GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->isLoggedIn());
    }

    /**
     * @test
     */
    public function createLogInAndRegisterFrontEndUserCreatesRegistrationRecord(): void
    {
        $this->createLogInAndRegisterFrontEndUser();
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_seminars_attendances');

        self::assertSame(
            1,
            $connection->count('*', 'tx_seminars_attendances', []),
        );
    }

    ////////////////////////////////////
    // Tests for creating the fixture.
    ////////////////////////////////////

    /**
     * @test
     */
    public function createFixtureWithInvalidWhatToDisplayThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value "foo" of the first parameter $whatToDisplay is not valid.');
        new RegistrationsList(
            ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html'],
            'foo',
            0,
            $this->getFrontEndController()->cObj,
        );
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function createFixtureWithListRegistrationsAsWhatToDisplayDoesNotThrowException(): void
    {
        new RegistrationsList(
            ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html'],
            'list_registrations',
            0,
            $this->getFrontEndController()->cObj,
        );
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function createFixtureWithListVipRegistrationsAsWhatToDisplayDoesNotThrowException(): void
    {
        new RegistrationsList(
            ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html'],
            'list_vip_registrations',
            0,
            $this->getFrontEndController()->cObj,
        );
    }

    ///////////////////////
    // Tests for render()
    ///////////////////////

    /**
     * @test
     */
    public function renderContainsHtmlspecialcharedEventTitle(): void
    {
        self::assertStringContainsString('Test event &amp; more', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithNegativeSeminarUidReturnsHeader404(): void
    {
        $subject = new RegistrationsList(
            ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html'],
            'list_registrations',
            -1,
            $this->getFrontEndController()->cObj,
        );
        $subject->render();

        self::assertSame(404, $this->responseHeadersModifier->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function renderWithZeroSeminarUidReturnsHeader404(): void
    {
        $subject = new RegistrationsList(
            ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html'],
            'list_registrations',
            0,
            $this->getFrontEndController()->cObj,
        );
        $subject->render();

        self::assertSame(404, $this->responseHeadersModifier->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function renderWithoutLoggedInFrontEndUserReturnsHeader403(): void
    {
        $this->subject->render();

        self::assertSame(403, $this->responseHeadersModifier->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndNotRegisteredFrontEndUserReturnsHeader403(): void
    {
        $this->testingFramework->createFrontEndUser();
        $this->subject->render();

        self::assertSame(403, $this->responseHeadersModifier->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserDoesNotReturnHeader403(): void
    {
        $this->createLogInAndRegisterFrontEndUser();
        $this->subject->render();

        self::assertNull($this->responseHeadersModifier->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainHeaderForTheFrontEndUserUid(): void
    {
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', 'uid');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<th scope="col">Number</th>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainTheFrontEndUserUid(): void
    {
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', 'uid');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<td>' . $this->feUserUid . '</td>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainHeaderForTheFrontEndUserName(): void
    {
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', 'name');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<th scope="col">Name</th>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainTheFrontEndUserName(): void
    {
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', 'name');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<td>Tom &amp; Jerry</td>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainHeaderForTheFrontEndUserUidAndName(): void
    {
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', 'uid,name');
        $this->createLogInAndRegisterFrontEndUser();
        $result = $this->subject->render();

        self::assertStringContainsString('<th scope="col">Number</th>', $result);
        self::assertStringContainsString('<th scope="col">Name</th>', $result);
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainTheFrontEndUserUidAndName(): void
    {
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', 'uid,name');
        $this->createLogInAndRegisterFrontEndUser();
        $this->testingFramework->changeRecord(
            'fe_users',
            $this->feUserUid,
            ['name' => 'Tom & Jerry'],
        );
        $result = $this->subject->render();

        self::assertStringContainsString('<td>' . $this->feUserUid . '</td>', $result);
        self::assertStringContainsString('<td>Tom &amp; Jerry</td>', $result);
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainHeaderForTheRegistrationUid(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<th scope="col">Ticket ID</th>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainTheRegistrationUid(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<td>' . $this->registrationUid . '</td>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainHeaderForTheRegistrationSeats(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'seats');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<th scope="col">Seats</th>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainTheRegistrationSeats(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'seats');
        $this->createLogInAndRegisterFrontEndUser();
        $this->testingFramework->changeRecord(
            'tx_seminars_attendances',
            $this->registrationUid,
            ['seats' => 42],
        );

        self::assertStringContainsString('<td>42</td>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderCanContainTheRegistrationInterests(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'interests');
        $this->createLogInAndRegisterFrontEndUser();
        $this->testingFramework->changeRecord(
            'tx_seminars_attendances',
            $this->registrationUid,
            ['interests' => 'everything practical & theoretical'],
        );

        self::assertStringContainsString(
            '<td>everything practical &amp; theoretical</td>',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainHeaderForTheRegistrationUidAndSeats(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid,seats');
        $this->createLogInAndRegisterFrontEndUser();

        self::assertStringContainsString('<th scope="col">Ticket ID</th>', $this->subject->render());
        self::assertStringContainsString('<th scope="col">Seats</th>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithLoggedInAndRegisteredFrontEndUserCanContainTheRegistrationUidAndSeats(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid,seats');
        $this->createLogInAndRegisterFrontEndUser();
        $this->testingFramework->changeRecord(
            'tx_seminars_attendances',
            $this->registrationUid,
            ['seats' => 42],
        );

        self::assertStringContainsString('<td>' . $this->registrationUid . '</td>', $this->subject->render());
        self::assertStringContainsString('<td>42</td>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithEmptyShowFeUserFieldsInRegistrationsListDoesNotContainUnresolvedLabel(): void
    {
        $this->createLogInAndRegisterFrontEndUser();
        $this->subject->setConfigurationValue('showFeUserFieldsInRegistrationsList', '');

        self::assertStringNotContainsString('label_', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithEmptyShowRegistrationFieldsInRegistrationListDoesNotContainUnresolvedLabel(): void
    {
        $this->createLogInAndRegisterFrontEndUser();
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', '');

        self::assertStringNotContainsString('label_', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderWithDeletedUserForRegistrationHidesUsersRegistration(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid');

        $this->createLogInAndRegisterFrontEndUser();

        $this->testingFramework->changeRecord(
            'fe_users',
            $this->feUserUid,
            ['deleted' => 1],
        );

        self::assertStringNotContainsString('<td>' . $this->registrationUid . '</td>', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderSeparatesMultipleRegistrationsWithTableRows(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid');
        $this->createLogInAndRegisterFrontEndUser();

        $feUserUid = $this->testingFramework->createFrontEndUser();
        $now = GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('date', 'timestamp');
        $secondRegistration = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $feUserUid,
                'crdate' => $now + 500,
            ],
        );

        self::assertMatchesRegularExpression(
            '/' . $this->registrationUid . '<\\/td>.*<\\/tr>' .
            '.*<tr>.*<td>' . $secondRegistration . '/s',
            $this->subject->render(),
        );
    }

    ///////////////////////////////////////////////////////
    // Tests concerning registrations on the waiting list
    ///////////////////////////////////////////////////////

    /**
     * @test
     */
    public function renderForNoWaitingListRegistrationsNotContainsWaitingListLabel(): void
    {
        self::assertStringNotContainsString(
            $this->translate('label_waiting_list'),
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderForWaitingListRegistrationsContainsWaitingListLabel(): void
    {
        $this->subject->setConfigurationValue('showRegistrationFieldsInRegistrationList', 'uid');
        $this->createLogInAndRegisterFrontEndUser();

        $feUserUid = $this->testingFramework->createFrontEndUser();
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $feUserUid,
                'registration_queue' => 1,
            ],
        );

        self::assertStringContainsString($this->translate('label_waiting_list'), $this->subject->render());
    }

    /**
     * @test
     */
    public function renderCanContainWaitingListRegistrations(): void
    {
        $this->subject->setConfigurationValue(
            'showRegistrationFieldsInRegistrationList',
            'uid',
        );
        $this->createLogInAndRegisterFrontEndUser();

        $feUserUid = $this->testingFramework->createFrontEndUser();
        $secondRegistration = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'seminar' => $this->seminarUid,
                'user' => $feUserUid,
                'registration_queue' => 1,
            ],
        );

        self::assertMatchesRegularExpression('/<td>' . $secondRegistration . '/s', $this->subject->render());
    }
}
