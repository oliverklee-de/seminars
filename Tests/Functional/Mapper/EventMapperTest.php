<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Mapper;

use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Model\Event;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Mapper\EventMapper
 */
final class EventMapperTest extends FunctionalTestCase
{
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

    /**
     * @param positive-int $uid
     */
    private static function assertContainsModelWithUid(Collection $models, int $uid): void
    {
        self::assertTrue($models->hasUid($uid));
    }

    /**
     * @param positive-int $uid
     */
    private static function assertNotContainsModelWithUid(Collection $models, int $uid): void
    {
        self::assertFalse($models->hasUid($uid));
    }

    /**
     * @test
     */
    public function findWithUidReturnsEventInstance(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->find(1);

        self::assertInstanceOf(Event::class, $result);
    }

    /**
     * @test
     */
    public function findWithUidOfExistingRecordReturnsRecordAsModel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->find(1);

        self::assertSame('a complete event', $result->getTitle());
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithoutRegistrationsWithoutDigestDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 1);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithoutRegistrationsWithDigestDateInPast(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 2);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationAndWithoutDigestDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 3);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailSortsEventsByBeginDateInAscendingOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 4);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithRegistrationOnlyBeforeDigestDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 5);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationsBeforeAndAfterDigestDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 8);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsDateWithRegistrationAfterDigestDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertContainsModelWithUid($result, 9);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresTopicWithRegistrationAfterDigestDate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 10);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresHiddenEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 11);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresDeletedEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 7);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresDeletedRegistration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $result = $this->subject->findForRegistrationDigestEmail();

        self::assertNotContainsModelWithUid($result, 6);
    }
}
