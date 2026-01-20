<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\OldModel\LegacyTimeSlot;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyTimeSlot
 */
final class LegacyTimeSlotTest extends UnitTestCase
{
    private LegacyTimeSlot $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new LegacyTimeSlot();
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
        $result = LegacyTimeSlot::fromData([]);

        self::assertInstanceOf(LegacyTimeSlot::class, $result);
    }
}
