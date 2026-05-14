<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\Model;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This test case holds all tests specific to event dates.
 *
 * @covers \OliverKlee\Seminars\Model\AbstractTimeSpan
 * @covers \OliverKlee\Seminars\Model\Event
 */
final class EventDateTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected bool $initializeDatabase = false;

    private EventMapper $eventMapper;

    private Event $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventMapper = $this->get(MapperRegistry::class)->getByClassName(EventMapper::class);

        $this->subject = new Event();
    }

    protected function tearDown(): void
    {
        MapperRegistry::purgeInstance();

        parent::tearDown();
    }

    // Tests concerning the title.

    /**
     * @test
     */
    public function getTitleWithNonEmptyTopicTitleReturnsTopicTitle(): void
    {
        $topic = $this->eventMapper->getLoadedTestingModel(['title' => 'Superhero']);
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
                'title' => 'Supervillain',
            ],
        );

        self::assertSame(
            'Superhero',
            $this->subject->getTitle(),
        );
    }

    /**
     * @test
     */
    public function getRawTitleWithNonEmptyTopicTitleReturnsDateTitle(): void
    {
        $topic = $this->eventMapper->getLoadedTestingModel(['title' => 'Superhero']);
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
                'title' => 'Supervillain',
            ],
        );

        self::assertSame(
            'Supervillain',
            $this->subject->getRawTitle(),
        );
    }

    // Tests regarding the teaser.

    /**
     * @test
     */
    public function getTeaserForEventDateWithoutTeaserReturnsAnEmptyString(): void
    {
        $topic = $this->eventMapper->getLoadedTestingModel([]);
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
            ],
        );

        self::assertEquals(
            '',
            $this->subject->getTeaser(),
        );
    }

    /**
     * @test
     */
    public function getTeaserForEventDateWithTeaserReturnsTeaser(): void
    {
        $topic = $this->eventMapper->getLoadedTestingModel(['teaser' => 'wow, this is teasing']);
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
            ],
        );

        self::assertEquals(
            'wow, this is teasing',
            $this->subject->getTeaser(),
        );
    }

    // Tests regarding the description.

    /**
     * @test
     */
    public function hasDescriptionForEventDateWithoutDescriptionReturnsFalse(): void
    {
        $topic = $this->eventMapper->getLoadedTestingModel([]);
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
            ],
        );

        self::assertFalse(
            $this->subject->hasDescription(),
        );
    }

    /**
     * @test
     */
    public function hasDescriptionForEventDateWithDescriptionReturnsTrue(): void
    {
        $topic = $this->eventMapper->getLoadedTestingModel(['description' => 'this is a great event.']);
        $this->subject->setData(
            [
                'object_type' => EventInterface::TYPE_EVENT_DATE,
                'topic' => $topic,
            ],
        );

        self::assertTrue(
            $this->subject->hasDescription(),
        );
    }
}
