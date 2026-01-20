<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\OldModel\LegacyOrganizer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyOrganizer
 */
final class LegacyOrganizerTest extends UnitTestCase
{
    private LegacyOrganizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new LegacyOrganizer();
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
        $result = LegacyOrganizer::fromData([]);

        self::assertInstanceOf(LegacyOrganizer::class, $result);
    }
}
