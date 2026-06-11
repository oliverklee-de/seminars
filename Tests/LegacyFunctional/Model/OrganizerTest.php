<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Model;

use OliverKlee\Seminars\Model\Organizer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\Organizer
 */
final class OrganizerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected bool $initializeDatabase = false;

    private Organizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Organizer();
    }

    ///////////////////////////////
    // Tests regarding the name.
    ///////////////////////////////

    /**
     * @test
     */
    public function getNameWithNonEmptyNameReturnsName(): void
    {
        $this->subject->setData(['title' => 'Fabulous organizer']);

        self::assertEquals(
            'Fabulous organizer',
            $this->subject->getName(),
        );
    }

    ////////////////////////////////////////
    // Tests regarding the email address.
    ////////////////////////////////////////

    /**
     * @test
     */
    public function getEmailAddressWithNonEmptyEmailAddressReturnsEmailAddress(): void
    {
        $this->subject->setData(['email' => 'mail@example.com']);

        self::assertEquals(
            'mail@example.com',
            $this->subject->getEmailAddress(),
        );
    }

    ///////////////////////////////////////
    // Tests regarding the email footer.
    ///////////////////////////////////////

    /**
     * @test
     */
    public function getEmailFooterInitiallyReturnsAnEmptyString(): void
    {
        $this->subject->setData([]);

        self::assertEquals(
            '',
            $this->subject->getEmailFooter(),
        );
    }

    /**
     * @test
     */
    public function getEmailFooterWithNonEmptyEmailFooterReturnsEmailFooter(): void
    {
        $this->subject->setData(['email_footer' => 'Example Inc.']);

        self::assertEquals(
            'Example Inc.',
            $this->subject->getEmailFooter(),
        );
    }

    /**
     * @test
     */
    public function setEmailFooterSetsEmailFooter(): void
    {
        $this->subject->setEmailFooter('Example Inc.');

        self::assertEquals(
            'Example Inc.',
            $this->subject->getEmailFooter(),
        );
    }

    /**
     * @test
     */
    public function hasEmailFooterInitiallyReturnsFalse(): void
    {
        $this->subject->setData([]);

        self::assertFalse(
            $this->subject->hasEmailFooter(),
        );
    }

    /**
     * @test
     */
    public function hasEmailFooterWithNonEmptyEmailFooterReturnsTrue(): void
    {
        $this->subject->setEmailFooter('Example Inc.');

        self::assertTrue(
            $this->subject->hasEmailFooter(),
        );
    }
}
