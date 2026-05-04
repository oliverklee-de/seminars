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
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\EventType;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This test case holds all tests specific to event dates.
 *
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class EventDateMapperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private EventMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->subject = MapperRegistry::get(EventMapper::class);
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
    public function getTopicWithoutTopicThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $model = $this->subject->getLoadedTestingModel(
            ['object_type' => EventInterface::TYPE_EVENT_DATE],
        );

        $model->getTopic();
    }

    /**
     * @test
     */
    public function getTopicWithTopicReturnsEventInstance(): void
    {
        $topicUid = $this->subject->getNewGhost()->getUid();
        \assert($topicUid > 0);

        $testingModel = $this->subject->getLoadedTestingModel(
            [
                'topic' => $topicUid,
                'object_type' => EventInterface::TYPE_EVENT_DATE,
            ],
        );

        self::assertInstanceOf(Event::class, $testingModel->getTopic());
    }

    //////////////////////////////////////
    // Tests regarding getCategories().
    //////////////////////////////////////

    /**
     * @test
     */
    public function getCategoriesForEventDateReturnsListInstance(): void
    {
        $topic = $this->subject->getLoadedTestingModel(['object_type' => EventInterface::TYPE_EVENT_TOPIC]);
        $date = $this->subject->getLoadedTestingModel(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic->getUid(),
            ],
        );

        self::assertInstanceOf(Collection::class, $date->getCategories());
    }

    /**
     * @test
     */
    public function getCategoriesForEventDateWithOneCategoryReturnsListOfCategories(): void
    {
        $topicUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $categoryUid = MapperRegistry::get(CategoryMapper::class)->getNewGhost()->getUid();
        \assert($categoryUid > 0);
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
            $categoryUid,
            'categories',
        );

        $model = $this->subject->find($uid);
        self::assertInstanceOf(Category::class, $model->getCategories()->first());
    }

    /**
     * @test
     */
    public function getCategoriesForEventDateWithOneCategoryReturnsOneCategory(): void
    {
        $topicUid = $this->testingFramework->createRecord('tx_seminars_seminars');
        $uid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );
        $categoryUid = MapperRegistry::get(CategoryMapper::class)->getNewGhost()->getUid();
        \assert($categoryUid > 0);
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $topicUid,
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
    public function getEventTypeForEventDateWithoutEventTypeReturnsNull(): void
    {
        $topicUid = MapperRegistry::get(EventMapper::class)->getLoadedTestingModel([])->getUid();
        \assert($topicUid > 0);
        $testingModel = $this->subject->getLoadedTestingModel(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );

        self::assertNull($testingModel->getEventType());
    }

    /**
     * @test
     */
    public function getEventTypeForEventDateWithEventTypeReturnsEventTypeInstance(): void
    {
        $eventType = MapperRegistry::get(EventTypeMapper::class)->getLoadedTestingModel([]);
        $topicUid = MapperRegistry::get(EventMapper::class)
            ->getLoadedTestingModel(['event_type' => $eventType->getUid()])->getUid();
        \assert($topicUid > 0);
        $testingModel = $this->subject->getLoadedTestingModel(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topicUid,
            ],
        );

        self::assertInstanceOf(EventType::class, $testingModel->getEventType());
    }
}
