<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model;

use OliverKlee\Seminars\Domain\Model\TargetGroup;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\TargetGroup
 */
final class TargetGroupTest extends UnitTestCase
{
    private TargetGroup $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TargetGroup();
    }

    /**
     * @test
     */
    public function isAbstractEntity(): void
    {
        self::assertInstanceOf(AbstractEntity::class, $this->subject);
    }

    /**
     * @test
     */
    public function getTitleInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function setTitleSetsTitle(): void
    {
        $value = 'Methodentraining';
        $this->subject->setTitle($value);

        self::assertSame($value, $this->subject->getTitle());
    }
}
