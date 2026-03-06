<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\RemoveDuplicateEventVenueRelationsUpgradeWizard;
use Psr\Log\LoggerAwareInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\RemoveDuplicateEventVenueRelationsUpgradeWizard
 */
final class RemoveDuplicateEventVenueRelationsUpgradeWizardTest extends UnitTestCase
{
    private RemoveDuplicateEventVenueRelationsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new RemoveDuplicateEventVenueRelationsUpgradeWizard($this->createStub(ConnectionPool::class));
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
    public function identifierIsForRemoveDuplicateEventVenueRelationsMigration(): void
    {
        self::assertSame('seminars_removeDuplicateEventVenueRelations', $this->subject->getIdentifier());
    }

    /**
     * @test
     */
    public function titleIsForRemoveDuplicateEventVenueRelationsMigration(): void
    {
        self::assertSame('Remove duplicate event-venue relations', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function descriptionIsForRemoveDuplicateEventVenueRelationsMigration(): void
    {
        $expected = 'Removes extraneous event-venue relations created by a bug in old seminars versions.';
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
