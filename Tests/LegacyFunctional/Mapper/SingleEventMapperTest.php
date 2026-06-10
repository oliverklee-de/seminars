<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Mapper;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\CategoryMapper;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Mapper\EventTypeMapper;
use OliverKlee\Seminars\Model\Category;
use OliverKlee\Seminars\Model\EventType;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This test case holds tests which are specific to single events.
 *
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class SingleEventMapperTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private CategoryMapper $categoryMapper;

    private EventTypeMapper $eventTypeMapper;

    private EventMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = $this->get(TestingFramework::class);

        $mapperRegistry = $this->get(MapperRegistry::class);
        $this->eventTypeMapper = $mapperRegistry->getByClassName(EventTypeMapper::class);
        $this->categoryMapper = $mapperRegistry->getByClassName(CategoryMapper::class);

        $this->subject = $mapperRegistry->getByClassName(EventMapper::class);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    /////////////////////////////////
    // Tests regarding getTopic().
    /////////////////////////////////

    /**
     * @test
     */
    public function getTopicForSingleRecordThrowsException(): void
    {
        $this->expectException(
            \BadMethodCallException::class,
        );
        $this->expectExceptionMessage(
            'This function may only be called for date records.',
        );

        $testingModel = $this->subject->getLoadedTestingModel(
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        $testingModel->getTopic();
    }

    // Tests regarding getCategories().

    /**
     * @test
     */
    public function getCategoriesForSingleEventReturnsListInstance(): void
    {
        $testingModel = $this->subject->getLoadedTestingModel(
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        self::assertInstanceOf(Collection::class, $testingModel->getCategories());
    }

    /**
     * @test
     */
    public function getCategoriesForSingleEventWithOneCategoryReturnsListOfCategories(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );
        $categoryUid = $this->categoryMapper->getNewGhost()->getUid();
        \assert($categoryUid > 0);
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $uid,
            $categoryUid,
            'categories',
        );

        $model = $this->subject->find($uid);
        self::assertInstanceOf(Category::class, $model->getCategories()->first());
    }

    /**
     * @test
     */
    public function getCategoriesForSingleEventWithOneCategoryReturnsOneCategory(): void
    {
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );
        $categoryUid = $this->categoryMapper->getNewGhost()->getUid();
        \assert($categoryUid > 0);
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $uid,
            $categoryUid,
            'categories',
        );

        $model = $this->subject->find($uid);
        self::assertSame(
            (string)$categoryUid,
            $model->getCategories()->getUids(),
        );
    }

    // Tests regarding getEventType().

    /**
     * @test
     */
    public function getEventTypeForSingleEventWithoutEventTypeReturnsNull(): void
    {
        $testingModel = $this->subject->getLoadedTestingModel(
            ['object_type' => EventInterface::TYPE_SINGLE_EVENT],
        );

        self::assertNull($testingModel->getEventType());
    }

    /**
     * @test
     */
    public function getEventTypeForSingleEventWithEventTypeReturnsEventTypeInstance(): void
    {
        $eventType = $this->eventTypeMapper->getLoadedTestingModel([]);
        $testingModel = $this->subject->getLoadedTestingModel(
            [
                'object_type' => EventInterface::TYPE_SINGLE_EVENT,
                'event_type' => $eventType->getUid(),
            ],
        );

        self::assertInstanceOf(EventType::class, $testingModel->getEventType());
    }
}
