<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\CopyBillingAddressToRegistrationsUpgradeWizard;
use Psr\Log\LoggerAwareInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\CopyBillingAddressToRegistrationsUpgradeWizard
 */
final class CopyBillingAddressToRegistrationsUpgradeWizardTest extends UnitTestCase
{
    private CopyBillingAddressToRegistrationsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new CopyBillingAddressToRegistrationsUpgradeWizard($this->createStub(ConnectionPool::class));
    }

    /**
     * @test
     */
    public function isUpgradeWizard(): void
    {
        self::assertInstanceOf(UpgradeWizardInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function isRepeatable(): void
    {
        self::assertInstanceOf(RepeatableInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function isLoggerAware(): void
    {
        self::assertInstanceOf(LoggerAwareInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function identifierIsForCopyBillingAddressToRegistrationsMigration(): void
    {
        self::assertSame('seminars_copyBillingAddressToRegistrations', $this->subject->getIdentifier());
    }

    /**
     * @test
     */
    public function titleIsForGenerateEventSlugsMigration(): void
    {
        self::assertSame('Copy billing address to registrations', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function descriptionIsForGenerateEventSlugsMigration(): void
    {
        $expected = 'Copies the billing address for all registrations from the FE users.';
        self::assertSame($expected, $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function requiresUpToDateDatabase(): void
    {
        self::assertSame([DatabaseUpdatedPrerequisite::class], $this->subject->getPrerequisites());
    }
}
