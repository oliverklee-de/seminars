<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\BagBuilder;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\BagBuilder\CategoryBagBuilder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\CategoryBagBuilder
 */
final class CategoryBagBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private CategoryBagBuilder $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->subject = new CategoryBagBuilder();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    ///////////////////////////////////////////
    // Tests for the basic builder functions.
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function builderBuildsABag(): void
    {
        self::assertInstanceOf(AbstractBag::class, $this->subject->build());
    }

    /**
     * @test
     */
    public function builtBagIsSortedAscendingByTitle(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Title 2'],
        );
        $this->testingFramework->createRecord(
            'tx_seminars_categories',
            ['title' => 'Title 1'],
        );

        $categoryBag = $this->subject->build();
        self::assertEquals(
            2,
            $categoryBag->count(),
        );

        self::assertEquals(
            'Title 1',
            $categoryBag->current()->getTitle(),
        );
        $categoryBag->next();
        self::assertEquals(
            'Title 2',
            $categoryBag->current()->getTitle(),
        );
    }

    ///////////////////////////////////////////////////////////////
    // Test for limiting the bag to categories of certain events.
    ///////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function skippingLimitToEventResultsInAllCategories(): void
    {
        $this->testingFramework->createRecord('tx_seminars_categories');

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function toLimitEmptyEventUidsResultsInAllCategories(): void
    {
        $this->testingFramework->createRecord('tx_seminars_categories');

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );

        $this->subject->limitToEvents('');
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWithZeroUidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('0');
    }

    /**
     * @test
     */
    public function limitToEventsWithNegativeUidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('-2');
    }

    /**
     * @test
     */
    public function limitToEventsWithZeroUidAtTheStartThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('0,1');
    }

    /**
     * @test
     */
    public function limitToEventsWithZeroUidAtTheEndThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('1,0');
    }

    /**
     * @test
     */
    public function limitToEventsWithZeroUidInTheMiddleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('1,0,2');
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public function sqlCharacterDataProvider(): array
    {
        return [
            ';' => [';'],
            ',' => [','],
            '(' => ['('],
            ')' => [')'],
            'double quote' => ['"'],
            'single quote' => ["'"],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $sqlCharacter
     *
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToEventsWithSqlRelevantCharacterOnlyThrowsException(string $sqlCharacter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents($sqlCharacter);
    }

    /**
     * @test
     *
     * @param non-empty-string $sqlCharacter
     *
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToEventsWithSqlRelevantCharacterFirstThrowsException(string $sqlCharacter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents($sqlCharacter . ',1');
    }

    /**
     * @test
     *
     * @param non-empty-string $sqlCharacter
     *
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToEventsWithSqlRelevantCharacterLastThrowsException(string $sqlCharacter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('1,' . $sqlCharacter);
    }

    /**
     * @test
     *
     * @param non-empty-string $sqlCharacter
     *
     * @dataProvider sqlCharacterDataProvider
     */
    public function limitToEventsWithSqlRelevantCharacterInTheMiddleThrowsException(string $sqlCharacter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$eventUids must be a comma-separated list of positive integers.');
        $this->expectExceptionCode(1333292640);

        $this->subject->limitToEvents('1,' . $sqlCharacter . ',2');
    }

    /**
     * @test
     */
    public function limitToEventsCanResultInOneCategory(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );

        $this->subject->limitToEvents((string)$eventUid);
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsCanResultInTwoCategoriesForOneEvent(): void
    {
        $this->testingFramework->createRecord('tx_seminars_categories');

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid1,
        );

        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid2,
        );

        $this->subject->limitToEvents((string)$eventUid);
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsCanResultInTwoCategoriesForTwoEvents(): void
    {
        $this->testingFramework->createRecord('tx_seminars_categories');

        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid1,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid2,
        );

        $this->subject->limitToEvents($eventUid1 . ',' . $eventUid2);
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWillExcludeUnassignedCategories(): void
    {
        $this->testingFramework->createRecord('tx_seminars_categories');

        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid,
            $categoryUid,
        );

        $this->subject->limitToEvents((string)$eventUid);
        $bag = $this->subject->build();

        self::assertFalse(
            $bag->isEmpty(),
        );
        self::assertEquals(
            $categoryUid,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsWillExcludeCategoriesOfOtherEvents(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid1 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid1,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid2 = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid2,
            $categoryUid2,
        );

        $this->subject->limitToEvents((string)$eventUid1);
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
        self::assertEquals(
            $categoryUid1,
            $bag->current()->getUid(),
        );
    }

    /**
     * @test
     */
    public function limitToEventsResultsInAnEmptyBagIfThereAreNoMatches(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );

        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );
        $categoryUid = $this->testingFramework->createRecord(
            'tx_seminars_categories',
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_categories_mm',
            $eventUid1,
            $categoryUid,
        );

        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
        );

        $this->subject->limitToEvents((string)$eventUid2);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    //////////////////////////////////
    // Tests for sortByRelationOrder
    //////////////////////////////////

    /**
     * @test
     */
    public function sortByRelationOrderThrowsExceptionIfLimitToEventsHasNotBeenCalledBefore(): void
    {
        $this->expectException(
            \BadMethodCallException::class,
        );
        $this->expectExceptionMessage(
            'The event UIDs were empty. This means limitToEvents has not been called. LimitToEvents has to be called before '
            .
            'calling this function.',
        );

        $this->subject->sortByRelationOrder();
    }
}
