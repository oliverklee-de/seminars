<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\OldModel;

use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingModel;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 */
final class AbstractModelTest extends FunctionalTestCase
{
    /**
     * @var positive-int
     */
    private int $now;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private Connection $testTableConnection;

    private Connection $mmTableConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $connectionPool = $this->get(ConnectionPool::class);
        $this->testTableConnection = $connectionPool->getConnectionForTable('tx_seminars_test');
        $this->mmTableConnection = $connectionPool->getConnectionForTable('tx_seminars_test_test_mm');

        $context = $this->get(Context::class);
        $context->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));
        $this->now = (int)$context->getPropertyFromAspect('date', 'timestamp');
    }

    /**
     * @test
     */
    public function fromUidWithZeroReturnsNull(): void
    {
        $result = TestingModel::fromUid(0);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidForNonExistentRecordReturnsNull(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(99);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultIgnoresHiddenRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(2);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultIgnoresNotStartedRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(4);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultIgnoresExpiredRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(5);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultIgnoresDeletedRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(3);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultForHiddenAllowedFindsHiddenRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(2, true);

        self::assertInstanceOf(TestingModel::class, $result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultForHiddenAllowedFindsNotStartedRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(4, true);

        self::assertInstanceOf(TestingModel::class, $result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultForHiddenAllowedFindsExpiredRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(5, true);

        self::assertInstanceOf(TestingModel::class, $result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultForHiddenAllowedFindsVisibleRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(1, true);

        self::assertInstanceOf(TestingModel::class, $result);
    }

    /**
     * @test
     */
    public function fromUidByDefaultForHiddenAllowedIgnoresDeletedRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(3, true);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function fromUidForExistingRecordCreatesInstanceOfSubclass(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = TestingModel::fromUid(1);

        self::assertInstanceOf(TestingModel::class, $result);
    }

    /**
     * @test
     */
    public function fromUidMapsDataFromDatabase(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        /** @var TestingModel $result */
        $result = TestingModel::fromUid(1);

        self::assertSame('the first one', $result->getTitle());
    }

    /**
     * @test
     */
    public function constructionByUidForNonExistentRecordReturnsModelWithoutUid(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = new TestingModel(99);

        self::assertFalse($result->hasUid());
    }

    /**
     * @test
     */
    public function constructionByUidByDefaultIgnoresHiddenRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = new TestingModel(2);

        self::assertFalse($result->hasUid());
    }

    /**
     * @test
     */
    public function constructionByUidByDefaultIgnoresDeletedRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = new TestingModel(3);

        self::assertFalse($result->hasUid());
    }

    /**
     * @test
     */
    public function constructionByUidByDefaultForHiddenAllowedFindsHiddenRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = new TestingModel(2, true);

        self::assertTrue($result->hasUid());
    }

    /**
     * @test
     */
    public function constructionByUidByDefaultForHiddenAllowedFindsVisibleRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = new TestingModel(1, true);

        self::assertTrue($result->hasUid());
    }

    /**
     * @test
     */
    public function constructionByUidByDefaultForHiddenAllowedIgnoresDeletedRecords(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $result = new TestingModel(3, true);

        self::assertFalse($result->hasUid());
    }

    /**
     * @test
     */
    public function constructionByUidSetsDataFromDatabase(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        /** @var TestingModel $result */
        $result = new TestingModel(1);

        self::assertSame('the first one', $result->getTitle());
    }

    /**
     * @test
     */
    public function commitToDatabaseCanCreateNewRecord(): void
    {
        $title = 'There is no spoon.';
        $model = new TestingModel();
        $model->setTitle($title);

        $model->commitToDatabase();

        self::assertSame(
            1,
            $this->testTableConnection->count('*', 'tx_seminars_test', ['title' => $title]),
        );
    }

    /**
     * @test
     */
    public function commitToDatabaseForNewRecordReturnsTrue(): void
    {
        $title = 'There is no spoon.';
        $model = new TestingModel();
        $model->setTitle($title);

        $result = $model->commitToDatabase();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function commitToDatabaseProvidesNewRecordWithUid(): void
    {
        $model = new TestingModel();
        $model->setTitle('There is no spoon.');

        $model->commitToDatabase();

        self::assertGreaterThan(0, $model->getUid());
    }

    /**
     * @test
     */
    public function commitToDatabaseSetsCreationDateOfNewRecordToNow(): void
    {
        $model = new TestingModel();
        $model->setTitle('There is no spoon.');

        $model->commitToDatabase();

        $result = $this->testTableConnection->select(['*'], 'tx_seminars_test', ['uid' => $model->getUid()]);
        $recordInDatabase = $result->fetchAssociative();
        self::assertSame($this->now, (int)$recordInDatabase['crdate']);
    }

    /**
     * @test
     */
    public function commitToDatabaseSetsTimestampOfNewRecordToNow(): void
    {
        $model = new TestingModel();
        $model->setTitle('There is no spoon.');

        $model->commitToDatabase();

        $result = $this->testTableConnection->select(['*'], 'tx_seminars_test', ['uid' => $model->getUid()]);
        $recordInDatabase = $result->fetchAssociative();

        self::assertSame($this->now, (int)$recordInDatabase['tstamp']);
    }

    /**
     * @test
     */
    public function commitToDatabaseNotPersistsEmptyRecord(): void
    {
        $model = new TestingModel();

        $model->commitToDatabase();

        self::assertSame(
            0,
            $this->testTableConnection->count('*', 'tx_seminars_test', []),
        );
    }

    /**
     * @test
     */
    public function commitToDatabaseForEmptyRecordReturnsFalse(): void
    {
        $model = new TestingModel();

        $result = $model->commitToDatabase();

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function commitToDatabaseCanUpdateExistingRecord(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $model = TestingModel::fromUid(1);
        $newTitle = 'new title';
        $model->setTitle($newTitle);

        $model->commitToDatabase();

        self::assertSame(
            1,
            $this->testTableConnection->count('*', 'tx_seminars_test', ['title' => $newTitle]),
        );
    }

    /**
     * @test
     */
    public function commitToDatabaseForExistingRecordReturnsTrue(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $model = TestingModel::fromUid(1);
        $newTitle = 'new title';
        $model->setTitle($newTitle);

        $result = $model->commitToDatabase();

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function commitToDatabaseKeepsCreationDateOfExistingRecordUnchanged(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');

        $model = TestingModel::fromUid(1);
        self::assertInstanceOf(TestingModel::class, $model);
        $newTitle = 'new title';
        $model->setTitle($newTitle);

        $model->commitToDatabase();

        $result = $this->testTableConnection->select(['*'], 'tx_seminars_test', ['uid' => $model->getUid()]);
        $recordInDatabase = $result->fetchAssociative();
        self::assertSame(1574714377, (int)$recordInDatabase['crdate']);
    }

    /**
     * @test
     */
    public function commitToDatabaseSetsTimestampOfExistingRecordToNow(): void
    {
        $model = new TestingModel();
        $model->setTitle('There is no spoon.');

        $model->commitToDatabase();

        $result = $this->testTableConnection->select(['*'], 'tx_seminars_test', ['uid' => $model->getUid()]);
        $recordInDatabase = $result->fetchAssociative();
        self::assertSame($this->now, (int)$recordInDatabase['tstamp']);
    }

    /**
     * @test
     */
    public function createMmRecordsWithEmptyReferencesReturnsZero(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $result = $subject->createMmRecords('tx_seminars_test_test_mm', []);

        self::assertSame(0, $result);
    }

    /**
     * @test
     */
    public function createMmRecordsWithOneReferenceReturnsOne(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $result = $subject->createMmRecords('tx_seminars_test_test_mm', [42]);

        self::assertSame(1, $result);
    }

    /**
     * @test
     */
    public function createMmRecordsWithTwoReferencesReturnsTwo(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $result = $subject->createMmRecords('tx_seminars_test_test_mm', [42, 31]);

        self::assertSame(2, $result);
    }

    /**
     * @test
     */
    public function createMmRecordsNotCountsZeroReferences(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $result = $subject->createMmRecords('tx_seminars_test_test_mm', [0]);

        self::assertSame(0, $result);
    }

    /**
     * @test
     */
    public function createMmRecordsCreatesRecordWithLocalAndForeignUid(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $subject->createMmRecords('tx_seminars_test_test_mm', [42]);

        $recordCount =
            $this->mmTableConnection->count('*', 'tx_seminars_test_test_mm', ['uid_local' => 1, 'uid_foreign' => 42]);
        self::assertSame(1, $recordCount);
    }

    /**
     * @test
     */
    public function createMmRecordsNotCreatesRecordForZeroReference(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $subject->createMmRecords('tx_seminars_test_test_mm', [0]);

        $recordCount = $this->mmTableConnection->count('*', 'tx_seminars_test_test_mm', ['uid_local' => 1]);
        self::assertSame(0, $recordCount);
    }

    /**
     * @test
     */
    public function createMmRecordsCreatesIncreasingSortingInReferenceOrder(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Test.xml');
        $subject = TestingModel::fromUid(1);

        $subject->createMmRecords('tx_seminars_test_test_mm', [42, 31]);

        $statement = $this->mmTableConnection->select(['sorting'], 'tx_seminars_test_test_mm', ['uid_local' => 1]);
        $recordInDatabase = $statement->fetchAllAssociative();

        self::assertSame([['sorting' => 1], ['sorting' => 2]], $recordInDatabase);
    }
}
