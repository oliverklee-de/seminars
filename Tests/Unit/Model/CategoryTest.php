<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Model;

use OliverKlee\Oelib\Model\AbstractModel;
use OliverKlee\Seminars\Model\Category;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Model\Category
 */
final class CategoryTest extends UnitTestCase
{
    private Category $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Category();
    }

    /**
     * @test
     */
    public function isModel(): void
    {
        self::assertInstanceOf(AbstractModel::class, $this->subject);
    }
}
