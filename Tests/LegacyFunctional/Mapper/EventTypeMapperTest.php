<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Mapper;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Mapper\EventTypeMapper;
use OliverKlee\Seminars\Model\EventType;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Mapper\EventTypeMapper
 */
final class EventTypeMapperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private EventTypeMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->subject = new EventTypeMapper();
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
    public function findWithUidReturnsEventTypeInstance(): void
    {
        self::assertInstanceOf(EventType::class, $this->subject->find(1));
    }
}
