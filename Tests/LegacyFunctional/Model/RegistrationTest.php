<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Model;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Registration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\Registration
 */
final class RegistrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected bool $initializeDatabase = false;

    private Registration $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->testingFramework = new TestingFramework();
        $this->subject = new Registration();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    ///////////////////////////////
    // Tests regarding the title.
    ///////////////////////////////

    /**
     * @test
     */
    public function getTitleWithNonEmptyTitleReturnsTitle(): void
    {
        $this->subject->setData(['title' => 'registration for event']);

        self::assertEquals(
            'registration for event',
            $this->subject->getTitle(),
        );
    }

    ////////////////////////////////////////
    // Tests regarding the front-end user.
    ////////////////////////////////////////

    /**
     * @test
     */
    public function setFrontEndUserSetsFrontEndUser(): void
    {
        $frontEndUser = new FrontEndUser();
        $this->subject->setFrontEndUser($frontEndUser);

        self::assertSame($frontEndUser, $this->subject->getFrontEndUser());
    }

    ///////////////////////////////
    // Tests regarding the event.
    ///////////////////////////////

    /**
     * @test
     */
    public function getEventReturnsEvent(): void
    {
        $event = MapperRegistry::get(EventMapper::class)
            ->getNewGhost();
        $this->subject->setData(['seminar' => $event]);

        self::assertSame(
            $event,
            $this->subject->getEvent(),
        );
    }

    /**
     * @test
     */
    public function getSeminarReturnsEvent(): void
    {
        $event = MapperRegistry::get(EventMapper::class)
            ->getNewGhost();
        $this->subject->setData(['seminar' => $event]);

        self::assertSame(
            $event,
            $this->subject->getSeminar(),
        );
    }

    /**
     * @test
     */
    public function setEventSetsEvent(): void
    {
        /** @var Event $event */
        $event = MapperRegistry::get(EventMapper::class)->getNewGhost();
        $this->subject->setEvent($event);

        self::assertSame(
            $event,
            $this->subject->getEvent(),
        );
    }

    /**
     * @test
     */
    public function setSeminarSetsEvent(): void
    {
        /** @var Event $event */
        $event = MapperRegistry::get(EventMapper::class)->getNewGhost();
        $this->subject->setSeminar($event);

        self::assertSame(
            $event,
            $this->subject->getEvent(),
        );
    }

    ///////////////////////////////
    // Tests regarding the seats.
    ///////////////////////////////

    /**
     * @test
     */
    public function getSeatsWithoutSeatsReturnsZero(): void
    {
        $this->subject->setData([]);

        self::assertEquals(
            0,
            $this->subject->getSeats(),
        );
    }

    /**
     * @test
     */
    public function getSeatsWithNonZeroSeatsReturnsSeats(): void
    {
        $this->subject->setData(['seats' => 42]);

        self::assertEquals(
            42,
            $this->subject->getSeats(),
        );
    }

    ///////////////////////////////////////////////
    // Tests regarding hasRegisteredThemselves().
    ///////////////////////////////////////////////

    /**
     * @test
     */
    public function hasRegisteredThemselvesForThirdPartyRegistrationReturnsFalse(): void
    {
        $this->subject->setData([]);

        self::assertFalse(
            $this->subject->hasRegisteredThemselves(),
        );
    }

    /**
     * @test
     */
    public function hasRegisteredThemselvesForSelfRegistrationReturnsTrue(): void
    {
        $this->subject->setData(['registered_themselves' => true]);

        self::assertTrue(
            $this->subject->hasRegisteredThemselves(),
        );
    }

    /////////////////////////////////////////
    // Tests regarding the attendees names.
    /////////////////////////////////////////

    /**
     * @test
     */
    public function getAttendeesNamesWithoutAttendeesNamesReturnsEmptyString(): void
    {
        $this->subject->setData([]);

        self::assertEquals(
            '',
            $this->subject->getAttendeesNames(),
        );
    }

    /**
     * @test
     */
    public function getAttendeesNamesWithAttendeesNamesReturnsAttendeesNames(): void
    {
        $this->subject->setData(['attendees_names' => 'John Doe']);

        self::assertEquals(
            'John Doe',
            $this->subject->getAttendeesNames(),
        );
    }

    /**
     * @test
     */
    public function setAttendeesNamesSetsAttendeesNames(): void
    {
        $this->subject->setAttendeesNames('John Doe');

        self::assertEquals(
            'John Doe',
            $this->subject->getAttendeesNames(),
        );
    }
}
