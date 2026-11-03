<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Model;

use OliverKlee\Oelib\Model\AbstractModel;
use OliverKlee\Seminars\Model\EventType;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\EventType
 */
final class EventTypeTest extends UnitTestCase
{
    private EventType $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new EventType();
    }

    /**
     * @test
     */
    public function isModel(): void
    {
        self::assertInstanceOf(AbstractModel::class, $this->subject);
    }
}
