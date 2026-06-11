<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Model;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Registration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\FrontEndUser
 */
final class FrontEndUserTest extends FunctionalTestCase
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

    private FrontEndUser $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new FrontEndUser();
        $this->testingFramework = $this->get(TestingFramework::class);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    // Tests concerning getRegistration and setRegistration

    /**
     * @test
     */
    public function getRegistrationReturnsRegistration(): void
    {
        $registration = new Registration();
        $this->subject->setData(
            ['tx_seminars_registration' => $registration],
        );

        self::assertSame(
            $registration,
            $this->subject->getRegistration(),
        );
    }

    /**
     * @test
     */
    public function setRegistrationSetsRegistration(): void
    {
        $registration = new Registration();
        $this->subject->setRegistration($registration);

        self::assertSame(
            $registration,
            $this->subject->getRegistration(),
        );
    }

    /**
     * @test
     */
    public function setRegistrationWithNullIsAllowed(): void
    {
        $this->subject->setRegistration();

        self::assertNull(
            $this->subject->getRegistration(),
        );
    }
}
