<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Seo;

use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Seo\SlugGenerator;
use OliverKlee\Seminars\Tests\Unit\Seo\Fixtures\TestingSlugEventDispatcher;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Seo\SlugGenerator
 */
final class SlugGeneratorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private SlugGenerator $subject;

    private TestingSlugEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = new TestingSlugEventDispatcher();

        $this->subject = new SlugGenerator($this->eventDispatcher, $this->get(ConnectionPool::class));
    }

    /**
     * @test
     */
    public function canBeConstructedWithMakeInstanceWithoutArguments(): void
    {
        $subject = GeneralUtility::makeInstance(SlugGenerator::class);

        self::assertInstanceOf(SlugGenerator::class, $subject);
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(SlugGenerator::class, $this->get(SlugGenerator::class));
    }

    /**
     * @test
     */
    public function getPrefixAlwaysReturnsAnEmptyString(): void
    {
        self::assertSame('', $this->subject->getPrefix());
    }

    /**
     * @test
     */
    public function instanceCreatedWithMakeInstanceCanGenerateSlug(): void
    {
        $subject = GeneralUtility::makeInstance(SlugGenerator::class);
        $record = ['uid' => 1234, 'object_type' => EventInterface::TYPE_SINGLE_EVENT, 'title' => 'There will be cake!'];

        $result = $subject->generateSlug(['record' => $record]);

        self::assertSame('there-will-be-cake/1234', $result);
    }

    /**
     * @test
     */
    public function generateSlugForEmptyRecordReturnsEmptyString(): void
    {
        $result = $this->subject->generateSlug(['record' => []]);

        self::assertSame('', $result);
    }

    /**
     * @return array<string,array{0: EventInterface::TYPE_*}>
     */
    public static function nonDateEventTypeDataProvider(): array
    {
        return [
            'single event' => [EventInterface::TYPE_SINGLE_EVENT],
            'event topic' => [EventInterface::TYPE_EVENT_TOPIC],
        ];
    }

    /**
     * @test
     *
     * @param EventInterface::TYPE_* $type
     *
     * @dataProvider nonDateEventTypeDataProvider
     */
    public function generateSlugForNonDateEventWithEmptyTitleReturnsUidWithoutTrailingSlash(int $type): void
    {
        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => $type, 'title' => ''];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame((string)$uid, $result);
    }

    /**
     * @test
     *
     * @param EventInterface::TYPE_* $type
     *
     * @dataProvider nonDateEventTypeDataProvider
     */
    public function generateSlugForNonDateEventWithWhitespaceOnlyTitleReturnsUidWithoutTrailingSlash(int $type): void
    {
        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => $type, 'title' => " \t\n\r"];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame((string)$uid, $result);
    }

    /**
     * @test
     *
     * @param EventInterface::TYPE_* $type
     *
     * @dataProvider nonDateEventTypeDataProvider
     */
    public function generateSlugForNonDateEventWithNonEmptyTitleReturnsSlugifiedTitleAndUid(int $type): void
    {
        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => $type, 'title' => 'There will be cake!'];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('there-will-be-cake/' . $uid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTopicReturnsSlugFromTopicTitleAndEventDateUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('event-topic/' . $eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTopicWithValuesAsStringReturnsSlugFromTopicTitleAndEventDateUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => (string)EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => (string)2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('event-topic/' . $eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTopicWithEmptyTitleReturnsEventDateUidWithoutTrailingSlash(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTopicWithoutTitle.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame((string)$eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithoutTopicReturnsEventDateUidWithoutTrailingSlash(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithoutTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame((string)$eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithDeletedTopicReturnsSlugFromTopicTitleAndEventDateUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithDeletedTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('deleted-event-topic/' . $eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithHiddenTopicReturnsSlugFromTopicTitleAndEventDateUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithHiddenTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('hidden-event-topic/' . $eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTimedTopicReturnsSlugFromTopicTitleAndEventDateUid(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTimedTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('timed-event-topic/' . $eventDateUid, $result);
    }

    /**
     * @test
     */
    public function generateSlugKeepsCurrentSlugIfTheGeneratedSlugIsTheSame(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleEventWithSlug.csv');

        $record = ['uid' => 1, 'object_type' => EventInterface::TYPE_SINGLE_EVENT, 'title' => 'some-event'];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame('some-event/1', $result);
    }

    /**
     * @test
     *
     * @param EventInterface::TYPE_* $type
     *
     * @dataProvider nonDateEventTypeDataProvider
     */
    public function generateSlugForNonDateEventDispatchesAfterSlugGeneratedEventWithEventUid(int $type): void
    {
        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => $type, 'title' => ''];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame($uid, $this->eventDispatcher->getEvent()->getSlugContext()->getEventUid());
    }

    /**
     * @test
     *
     * @param EventInterface::TYPE_* $type
     *
     * @dataProvider nonDateEventTypeDataProvider
     */
    public function generateSlugForNonDateEventDispatchesSAfterSlugGeneratedEventWithEventDisplayTitle(int $type): void
    {
        $title = 'Tea tasting';
        $record = ['uid' => 1234, 'object_type' => $type, 'title' => $title];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame($title, $this->eventDispatcher->getEvent()->getSlugContext()->getDisplayTitle());
    }

    /**
     * @test
     *
     * @param EventInterface::TYPE_* $type
     *
     * @dataProvider nonDateEventTypeDataProvider
     */
    public function generateSlugForNonDateEventDispatchesAfterSlugGeneratedEventWithGeneratedSlugWithUid(
        int $type
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleEventWithSlug.csv');

        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => $type, 'title' => 'some-event'];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame('some-event/' . $uid, $this->eventDispatcher->getEvent()->getSlug());
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTopicDispatchesAfterSlugGeneratedEventGeneratedSlugFromTopic(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame('event-topic/' . $eventDateUid, $this->eventDispatcher->getEvent()->getSlug());
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTopicDispatchesAfterSlugGeneratedEventWithTitleFromTopic(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTopic.csv');

        $record = [
            'uid' => 1234,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame('Event topic', $this->eventDispatcher->getEvent()->getSlugContext()->getDisplayTitle());
    }

    /**
     * @test
     */
    public function generateSlugForEventDateWithTopicDispatchesAfterSlugGeneratedEventWithEventUidFromDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventDateWithTopic.csv');

        $eventDateUid = 1234;
        $record = [
            'uid' => $eventDateUid,
            'object_type' => EventInterface::TYPE_EVENT_DATE,
            'title' => 'Event date',
            'topic' => 2,
            'slug' => 'existing-date-slug',
        ];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame($eventDateUid, $this->eventDispatcher->getEvent()->getSlugContext()->getEventUid());
    }

    /**
     * @test
     */
    public function generateSlugPassesSlugifiedTitleToEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleEventWithSlug.csv');

        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => EventInterface::TYPE_SINGLE_EVENT, 'title' => 'Some event'];

        $this->subject->generateSlug(['record' => $record]);

        self::assertTrue($this->eventDispatcher->isDispatched());
        self::assertSame(
            'some-event',
            $this->eventDispatcher->getEvent()->getSlugContext()->getSlugifiedTitle(),
        );
    }

    /**
     * @test
     */
    public function generateSlugReturnsSlugModifiedByEvent(): void
    {
        $modifiedSlug = '42/there-is-no-spoon';
        $this->eventDispatcher->setModifiedSlug($modifiedSlug);
        $uid = 1234;
        $record = ['uid' => $uid, 'object_type' => EventInterface::TYPE_SINGLE_EVENT, 'title' => ''];

        $result = $this->subject->generateSlug(['record' => $record]);

        self::assertSame($modifiedSlug, $result);
    }
}
