<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\BagBuilder;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\Tests\Functional\BagBuilder\Fixtures\TestingBagBuilder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\AbstractBagBuilder
 */
final class AbstractBagBuilderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingBagBuilder $subject;

    private TestingFramework $testingFramework;

    private int $dummySysFolderPid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();

        $this->subject = new TestingBagBuilder();

        $this->dummySysFolderPid = $this->testingFramework->createSystemFolder();
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
    public function builderBuildsAnObject(): void
    {
        $bag = $this->subject->build();

        self::assertIsObject($bag);
    }

    /**
     * @test
     */
    public function builderBuildsABag(): void
    {
        $bag = $this->subject->build();

        self::assertInstanceOf(AbstractBag::class, $bag);
    }

    /**
     * @test
     */
    public function builderBuildsBagSortedAscendingByUid(): void
    {
        $eventUid1 = $this->testingFramework->createRecord('tx_seminars_test');
        $eventUid2 = $this->testingFramework->createRecord('tx_seminars_test');

        $testBag = $this->subject->build();
        self::assertEquals(
            2,
            $testBag->count(),
        );

        self::assertEquals(
            $eventUid1,
            $testBag->current()->getUid(),
        );
        $testBag->next();
        self::assertEquals(
            $eventUid2,
            $testBag->current()->getUid(),
        );
    }

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function builderWithAdditionalTableNameDoesNotProduceSqlError(): void
    {
        $this->subject->addAdditionalTableName('tx_seminars_seminars');

        $this->subject->build();
    }

    ///////////////////////////////////
    // Tests concerning source pages.
    ///////////////////////////////////

    /**
     * @test
     */
    public function builderInitiallyHasNoSourcePages(): void
    {
        self::assertFalse(
            $this->subject->hasSourcePages(),
        );
    }

    /**
     * @test
     */
    public function builderHasSourcePagesWithOnePage(): void
    {
        $this->subject->setSourcePages((string)$this->dummySysFolderPid);

        self::assertTrue(
            $this->subject->hasSourcePages(),
        );
    }

    /**
     * @test
     */
    public function builderHasSourcePagesWithTwoPages(): void
    {
        $this->subject->setSourcePages($this->dummySysFolderPid . ',' . ($this->dummySysFolderPid + 1));

        self::assertTrue(
            $this->subject->hasSourcePages(),
        );
    }

    /**
     * @test
     */
    public function builderHasNoSourcePagesWithEvilSql(): void
    {
        $this->subject->setSourcePages('; DROP TABLE tx_seminars_test;');

        self::assertFalse(
            $this->subject->hasSourcePages(),
        );
    }

    /**
     * @test
     */
    public function builderSelectsRecordsFromAllPagesByDefault(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
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
    public function builderSelectsRecordsFromAllPagesWithEmptySourcePages(): void
    {
        $this->subject->setSourcePages('');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
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
    public function builderSelectsRecordsFromAllPagesWithEmptyAfterNonEmptySourcePages(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
        );

        $this->subject->setSourcePages((string)$this->dummySysFolderPid);
        $this->subject->setSourcePages('');
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderSelectsRecordsFromAllPagesWithEmptySourcePagesAndZeroRecursion(): void
    {
        $this->subject->setSourcePages('');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
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
    public function builderSelectsRecordsFromAllPagesWithEmptySourcePagesAndNonZeroRecursion(): void
    {
        $this->subject->setSourcePages('', 1);
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
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
    public function builderSelectsRecordsFromOnePage(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
        );

        $this->subject->setSourcePages((string)$this->dummySysFolderPid);
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderSelectsRecordsFromTwoPages(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid],
        );
        // Puts this record on a non-existing page. This is intentional.
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $this->dummySysFolderPid + 1],
        );

        $this->subject->setSourcePages($this->dummySysFolderPid . ',' . ($this->dummySysFolderPid + 1));
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderIgnoresRecordsOnSubpageWithoutRecursion(): void
    {
        $subPagePid = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid,
        );

        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subPagePid],
        );

        $this->subject->setSourcePages((string)$this->dummySysFolderPid);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function builderSelectsRecordsOnSubpageWithRecursion(): void
    {
        $subPagePid = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid,
        );

        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subPagePid],
        );

        $this->subject->setSourcePages((string)$this->dummySysFolderPid, 1);
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderSelectsRecordsOnTwoSubpagesWithRecursion(): void
    {
        $subPagePid1 = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid,
        );
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subPagePid1],
        );

        $subPagePid2 = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid,
        );
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subPagePid2],
        );

        $this->subject->setSourcePages((string)$this->dummySysFolderPid, 1);
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderSelectsRecordsOnSubpageFromTwoParentsWithRecursion(): void
    {
        $subPagePid1 = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid,
        );
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subPagePid1],
        );

        $parentPid2 = $this->testingFramework->createSystemFolder();
        $subPagePid2 = $this->testingFramework->createSystemFolder($parentPid2);
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subPagePid2],
        );

        $this->subject->setSourcePages($this->dummySysFolderPid . ',' . $parentPid2, 1);
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function builderIgnoresRecordsOnSubpageWithTooShallowRecursion(): void
    {
        $subPagePid = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid,
        );
        $subSubPagePid = $this->testingFramework->createSystemFolder(
            $subPagePid,
        );

        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['pid' => $subSubPagePid],
        );

        $this->subject->setSourcePages((string)$this->dummySysFolderPid, 1);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    ////////////////////////////////////////////////////////
    // Tests concerning hidden/deleted/timed etc. records.
    ////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function whereClauseInitiallyIsNotEmpty(): void
    {
        self::assertNotEquals(
            '',
            $this->subject->getWhereClause(),
        );
    }

    /**
     * @test
     */
    public function whereClauseCanSelectPids(): void
    {
        $this->subject->setSourcePages((string)$this->dummySysFolderPid);

        // We're using assertContains here because the PID in the WHERE clause
        // may be prefixed with the table name.
        self::assertStringContainsString(
            'pid IN (' . $this->dummySysFolderPid . ')',
            $this->subject->getWhereClause(),
        );
    }

    /**
     * @test
     */
    public function whereClausePartInitiallyIsEmpty(): void
    {
        self::assertSame('', $this->subject->getWhereClausePart('testpart'));
    }

    /**
     * @test
     */
    public function whereClausePartCanBeSetAndCanBeRetrieved(): void
    {
        $this->subject->setWhereClausePart('testpart', 'testpart IN (1,2,3)');

        self::assertSame('testpart IN (1,2,3)', $this->subject->getWhereClausePart('testpart'));
    }

    /**
     * @test
     */
    public function whereClausePartSettingToEmptyCompletelyRemovesIt(): void
    {
        $this->subject->setWhereClausePart('testpart', 'testpart IN (1,2,3)');
        $this->subject->setWhereClausePart('testpart', '');

        // We're using assertNotContains here because the WHERE clause always
        // contains a test-specific prefix
        self::assertStringNotContainsString(' AND ', $this->subject->getWhereClause());
    }

    /**
     * @test
     */
    public function whereClausePartCanBeSetAndGetsAddedToWhereClause(): void
    {
        $this->subject->setWhereClausePart('testpart', 'testpart IN (1,2,3)');

        // We're using assertContains here because the WHERE clause always
        // contains a test-specific prefix
        self::assertStringContainsString('testpart IN (1,2,3)', $this->subject->getWhereClause());
    }

    /////////////////////////////////
    // Test concerning limitToTitle
    /////////////////////////////////

    /**
     * @test
     */
    public function limitToTitleFindsRecordWithThatTitle(): void
    {
        $this->subject->limitToTitle('foo');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['title' => 'foo'],
        );
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToTitleIgnoresRecordWithOtherTitle(): void
    {
        $this->subject->limitToTitle('foo');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['title' => 'bar'],
        );
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    ///////////////////////////////////////////////////
    // Test concerning the combination of limitations
    ///////////////////////////////////////////////////

    /**
     * @test
     */
    public function limitToTitleAndPagesFindsRecordThatMatchesBoth(): void
    {
        $this->subject->setSourcePages((string)$this->dummySysFolderPid);
        $this->subject->limitToTitle('foo');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['title' => 'foo', 'pid' => $this->dummySysFolderPid],
        );
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
    }

    /**
     * @test
     */
    public function limitToTitleAndPagesExcludesRecordThatMatchesOnlyTheTitle(): void
    {
        $this->subject->setSourcePages((string)$this->dummySysFolderPid);
        $this->subject->limitToTitle('foo');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['title' => 'foo'],
        );
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function limitToTitleAndPagesExcludesRecordThatMatchesOnlyThePage(): void
    {
        $this->subject->setSourcePages((string)$this->dummySysFolderPid);
        $this->subject->limitToTitle('foo');
        $this->testingFramework->createRecord(
            'tx_seminars_test',
            ['title' => 'bar', 'pid' => $this->dummySysFolderPid],
        );
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty(),
        );
    }

    //////////////////////////////////////////////
    // Tests concerning addAdditionalTableName()
    //////////////////////////////////////////////

    /**
     * @test
     */
    public function addAdditionalTableNameWithTableNameAddsAdditionalTableName(): void
    {
        $this->subject->addAdditionalTableName('tx_seminars_seminars');

        self::assertContains(
            'tx_seminars_seminars',
            $this->subject->getAdditionalTableNames(),
        );
    }

    /////////////////////////////////////////////////
    // Tests concerning removeAdditionalTableName()
    /////////////////////////////////////////////////

    /**
     * @test
     */
    public function removeAdditionalTableNameWithNotSetTableNameThrowsException(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
        );
        $this->expectExceptionMessage(
            'The given additional table name does not exist in the list ' .
            'of additional table names.',
        );

        $this->subject->removeAdditionalTableName('tx_seminars_seminars');
    }

    /**
     * @test
     */
    public function removeAdditionalTableNameWithSetTableNameRemovesAdditionalTableName(): void
    {
        $this->subject->addAdditionalTableName('tx_seminars_seminars');
        $this->subject->removeAdditionalTableName('tx_seminars_seminars');

        self::assertNotContains(
            'tx_seminars_seminars',
            $this->subject->getAdditionalTableNames(),
        );
    }

    //////////////////////////////////
    // Tests concerning setOrderBy()
    //////////////////////////////////

    /**
     * @test
     */
    public function setOrderByWithOrderBySetsOrderBy(): void
    {
        $this->subject->setOrderBy('field ASC');

        self::assertEquals(
            'field ASC',
            $this->subject->getOrderBy(),
        );
    }

    /**
     * @test
     */
    public function setOrderByWithEmptyStringRemovesOrderBy(): void
    {
        $this->subject->setOrderBy('');

        self::assertEquals(
            '',
            $this->subject->getOrderBy(),
        );
    }

    /**
     * @test
     */
    public function setOrderByWithOrderByActuallySortsTheBag(): void
    {
        $this->subject->setOrderBy('uid DESC');
        $eventUid1 = $this->testingFramework->createRecord('tx_seminars_test');
        $eventUid2 = $this->testingFramework->createRecord('tx_seminars_test');

        $testBag = $this->subject->build();
        self::assertEquals(
            2,
            $testBag->count(),
        );

        self::assertEquals(
            $eventUid2,
            $testBag->current()->getUid(),
        );
        $testBag->next();
        self::assertEquals(
            $eventUid1,
            $testBag->current()->getUid(),
        );
    }

    ////////////////////////////////
    // Tests concerning setLimit()
    ////////////////////////////////

    /**
     * @test
     */
    public function setLimitWithNonEmptyLimitSetsLimit(): void
    {
        $this->subject->setLimit('0, 30');

        self::assertEquals(
            '0, 30',
            $this->subject->getLimit(),
        );
    }

    /**
     * @test
     */
    public function setLimitWithEmptyStringRemovesLimit(): void
    {
        $this->subject->setLimit('');

        self::assertEquals(
            '',
            $this->subject->getLimit(),
        );
    }

    /**
     * @test
     */
    public function setLimitWithLimitActuallyLimitsTheBag(): void
    {
        $this->testingFramework->createRecord('tx_seminars_test');
        $this->testingFramework->createRecord('tx_seminars_test');
        $this->subject->setLimit('0, 1');
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->count(),
        );
    }
}
