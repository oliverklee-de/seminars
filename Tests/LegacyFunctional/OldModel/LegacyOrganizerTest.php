<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\OldModel;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\OldModel\LegacyOrganizer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyOrganizer
 */
final class LegacyOrganizerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private LegacyOrganizer $subject;

    private TestingFramework $testingFramework;

    private LegacyOrganizer $maximalFixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework('tx_seminars');
        $subjectUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'Test organizer',
                'email' => 'foo@example.com',
            ],
        );
        $this->subject = new LegacyOrganizer($subjectUid);

        $maximalFixtureUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'Test organizer',
                'homepage' => 'https://www.example.com/',
                'email' => 'maximal-foo@example.com',
                'email_footer' => "line 1\nline 2",
                'description' => 'foo',
            ],
        );
        $this->maximalFixture = new LegacyOrganizer($maximalFixtureUid);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    //////////////////////////////////////////
    // Tests for creating organizer objects.
    //////////////////////////////////////////

    /**
     * @test
     */
    public function createFromUid(): void
    {
        self::assertTrue(
            $this->subject->isOk(),
        );
    }

    ////////////////////////////////////////////////
    // Tests for getting the organizer attributes.
    ////////////////////////////////////////////////

    /**
     * @test
     */
    public function getNameWithNameReturnsName(): void
    {
        self::assertEquals(
            'Test organizer',
            $this->subject->getName(),
        );
    }

    /**
     * @test
     */
    public function hasHomepageWithEmptyHomepageReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasHomepage(),
        );
    }

    /**
     * @test
     */
    public function hasHomepageWithHomepageReturnsTrue(): void
    {
        self::assertTrue(
            $this->maximalFixture->hasHomepage(),
        );
    }

    /**
     * @test
     */
    public function getHomepage(): void
    {
        self::assertEquals(
            '',
            $this->subject->getHomepage(),
        );
        self::assertEquals(
            'https://www.example.com/',
            $this->maximalFixture->getHomepage(),
        );
    }

    /**
     * @test
     */
    public function getEmailFooterForEmptyFooterReturnsEmptyString(): void
    {
        self::assertEquals(
            '',
            $this->subject->getEmailFooter(),
        );
    }

    /**
     * @test
     */
    public function getEmailFooterForNonEmptyFooterReturnsThisFooter(): void
    {
        self::assertEquals(
            "line 1\nline 2",
            $this->maximalFixture->getEmailFooter(),
        );
    }

    /**
     * @test
     */
    public function getEmailAddressWithEmailAddressReturnsEmailAddress(): void
    {
        self::assertEquals(
            'foo@example.com',
            $this->subject->getEmailAddress(),
        );
    }

    /////////////////////////////////////
    // Tests concerning the description
    /////////////////////////////////////

    /**
     * @test
     */
    public function hasDescriptionForOrganizerWithoutDescriptionReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasDescription(),
        );
    }

    /**
     * @test
     */
    public function hasDescriptionForOrganizerWithDescriptionReturnsTrue(): void
    {
        self::assertTrue(
            $this->maximalFixture->hasDescription(),
        );
    }

    /**
     * @test
     */
    public function getDescriptionForOrganizerWithoutDescriptionReturnsEmptyString(): void
    {
        self::assertEquals(
            '',
            $this->subject->getDescription(),
        );
    }

    /**
     * @test
     */
    public function getDescriptionForOrganizerWithDescriptionReturnsDescription(): void
    {
        self::assertEquals(
            'foo',
            $this->maximalFixture->getDescription(),
        );
    }
}
