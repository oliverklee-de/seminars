<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Mapper;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Mapper\CategoryMapper;
use OliverKlee\Seminars\Model\Category;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Mapper\CategoryMapper
 */
final class CategoryMapperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private CategoryMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->subject = new CategoryMapper();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    // Tests concerning find

    /**
     * @test
     */
    public function findWithUidReturnsCategoryInstance(): void
    {
        self::assertInstanceOf(Category::class, $this->subject->find(1));
    }

    /**
     * @test
     */
    public function findWithUidOfExistingRecordReturnsRecordAsModel(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Lecture'],
        );
        $model = $this->subject->find($uid);

        self::assertInstanceOf(Category::class, $model);
    }
}
