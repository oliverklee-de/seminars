<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Mapper;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\EventMapper;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This test case holds all tests specific to event topics.
 *
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class EventTopicMapperTest extends FunctionalTestCase
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
    public function getTopicForTopicRecordThrowsException(): void
    {
        $this->expectException(
            \BadMethodCallException::class,
        );
        $this->expectExceptionMessage(
            'This function may only be called for date records.',
        );

        $testingModel = $this->subject->getLoadedTestingModel(
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );

        $testingModel->getTopic();
    }
}
