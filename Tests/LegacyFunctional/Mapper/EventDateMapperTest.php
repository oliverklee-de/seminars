<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Mapper;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This test case holds all tests specific to event dates.
 *
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class EventDateMapperTest extends FunctionalTestCase
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

    private EventMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = $this->get(TestingFramework::class);

        $mapperRegistry = $this->get(MapperRegistry::class);

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
}
