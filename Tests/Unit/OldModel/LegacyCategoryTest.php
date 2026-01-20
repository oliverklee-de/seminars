<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\OldModel;

use OliverKlee\Seminars\OldModel\AbstractModel;
use OliverKlee\Seminars\OldModel\LegacyCategory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyCategory
 */
final class LegacyCategoryTest extends UnitTestCase
{
    private LegacyCategory $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new LegacyCategory();
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
    public function fromDataCreatesCategoryInstance(): void
    {
        $result = LegacyCategory::fromData([]);

        self::assertInstanceOf(LegacyCategory::class, $result);
    }

    /**
     * @test
     */
    public function getTitleReturnsTitle(): void
    {
        $title = 'Test category';
        $subject = LegacyCategory::fromData(['title' => $title]);

        self::assertSame($title, $subject->getTitle());
    }
}
