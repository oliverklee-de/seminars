<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\OldModel\LegacySpeaker;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacySpeaker
 */
final class LegacySpeakerTest extends UnitTestCase
{
    private LegacySpeaker $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new LegacySpeaker();
    }

    /**
     * @test
     */
    public function isAbstractModel(): void
    {
        self::assertInstanceOf(AbstractModel::class, $this->subject);
    }

    /**
     * @test
     */
    public function fromDataCreatesInstanceOfSubclass(): void
    {
        $result = LegacySpeaker::fromData([]);

        self::assertInstanceOf(LegacySpeaker::class, $result);
    }

    /**
     * @test
     */
    public function hasOrganizationWithNoOrganizationReturnsFalse(): void
    {
        $subject = LegacySpeaker::fromData(['organization' => '']);

        self::assertFalse($subject->hasOrganization());
    }

    /**
     * @test
     */
    public function hasOrganizationWithOrganizationReturnsTrue(): void
    {
        $organization = 'Foo inc.';
        $subject = LegacySpeaker::fromData(['organization' => $organization]);

        self::assertTrue($subject->hasOrganization());
    }

    /**
     * @test
     */
    public function hasHomepageWithNoHomepageReturnsFalse(): void
    {
        $subject = LegacySpeaker::fromData(['homepage' => '']);

        self::assertFalse($subject->hasHomepage());
    }

    /**
     * @test
     */
    public function hasHomepageWithHomepageReturnsTrue(): void
    {
        $homepage = 'Foo inc.';
        $subject = LegacySpeaker::fromData(['homepage' => $homepage]);

        self::assertTrue($subject->hasHomepage());
    }

    /**
     * @test
     */
    public function hasDescriptionWithNoDescriptionReturnsFalse(): void
    {
        $subject = LegacySpeaker::fromData(['description' => '']);

        self::assertFalse($subject->hasDescription());
    }

    /**
     * @test
     */
    public function hasDescriptionWithDescriptionReturnsTrue(): void
    {
        $description = 'Foo inc.';
        $subject = LegacySpeaker::fromData(['description' => $description]);

        self::assertTrue($subject->hasDescription());
    }

    /**
     * @test
     */
    public function hasSkillsInitiallyIsFalse(): void
    {
        $subject = new LegacySpeaker();

        self::assertFalse($subject->hasSkills());
    }

    /**
     * @test
     */
    public function getSkillsShortWithNoSkillReturnsEmptyString(): void
    {
        $subject = new LegacySpeaker();

        self::assertSame('', $subject->getSkillsShort());
    }

    /**
     * @test
     */
    public function getNumberOfSkillsReturnsNumberOfSkills(): void
    {
        $subject = LegacySpeaker::fromData(['skills' => 2]);

        self::assertSame(2, $subject->getNumberOfSkills());
    }

    /**
     * @test
     */
    public function hasCancelationPeriodWithoutCancelationPeriodReturnsFalse(): void
    {
        $subject = new LegacySpeaker();

        self::assertFalse($subject->hasCancelationPeriod());
    }

    /**
     * @test
     */
    public function hasCancelationPeriodWithCancelationPeriodReturnsTrue(): void
    {
        $subject = new LegacySpeaker();
        $subject->setCancelationPeriod(42);

        self::assertTrue($subject->hasCancelationPeriod());
    }

    /**
     * @test
     */
    public function hasImageWithoutImageReturnsFalse(): void
    {
        $subject = new LegacySpeaker();

        self::assertFalse($subject->hasImage());
    }

    /**
     * @test
     */
    public function hasImageWithImageReturnsTrue(): void
    {
        $subject = LegacySpeaker::fromData(['image' => 1]);

        self::assertTrue($subject->hasImage());
    }
}
