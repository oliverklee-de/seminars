<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\GenerateEventSlugsUpgradeWizard;
use Psr\Log\LoggerAwareInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\GenerateEventSlugsUpgradeWizard
 */
final class GenerateEventSlugsUpgradeWizardTest extends UnitTestCase
{
    private GenerateEventSlugsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new GenerateEventSlugsUpgradeWizard($this->createStub(ConnectionPool::class));
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
    public function identifierIsForGenerateEventSlugsMigration(): void
    {
        self::assertSame('seminars_generateEventSlugs', $this->subject->getIdentifier());
    }

    /**
     * @test
     */
    public function titleIsForGenerateEventSlugsMigration(): void
    {
        self::assertSame('Generates slugs for all events that do not have a slug yet.', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function descriptionIsForGenerateEventSlugsMigration(): void
    {
        $expected = 'Automatically generates the slugs for all events using their titles and UIDs.';
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
