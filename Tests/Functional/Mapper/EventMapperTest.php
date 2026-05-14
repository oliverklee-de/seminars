<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Mapper;

use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Tests\Functional\Traits\CollectionHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class EventMapperTest extends FunctionalTestCase
{
    use CollectionHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private EventMapper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(MapperRegistry::class)->getByClassName(EventMapper::class);
    }

    protected function tearDown(): void
    {
        MapperRegistry::purgeInstance();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function findWithUidReturnsEventInstance(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->find(1);

        self::assertInstanceOf(Event::class, $result);
    }

    /**
     * @test
     */
    public function findWithUidOfExistingRecordReturnsRecordAsModel(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->find(1);

        self::assertSame('a complete event', $result->getTitle());
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithoutRegistrationsWithoutDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 1);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithoutRegistrationsWithDigestDateInPast(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 2);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationAndWithoutDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 3);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailSortsEventsByBeginDateInAscendingOrder(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();
        self::assertContainsModelWithUid($result, 3);
        self::assertContainsModelWithUid($result, 4);

        $uids = GeneralUtility::intExplode(',', $result->getUids(), true);
        $indexOfLaterEvent = \array_search(3, $uids, true);
        $indexOfEarlierEvent = \array_search(4, $uids, true);

        self::assertTrue($indexOfEarlierEvent < $indexOfLaterEvent);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationAfterDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 4);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithRegistrationOnlyBeforeDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 5);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationsBeforeAndAfterDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 8);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsDateWithRegistrationAfterDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 9);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresTopicWithRegistrationAfterDigestDate(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 10);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresHiddenEvent(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 11);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresDeletedEvent(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 7);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresDeletedRegistration(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 6);
    }
}
