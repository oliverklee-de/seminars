<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\OldModel;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingModel;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 */
final class AbstractModelTest extends FunctionalTestCase
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

    private TestingModel $subject;

    private TestingFramework $testingFramework;

    /**
     * @var positive-int
     */
    private int $subjectUid;

    protected function setUp(): void
    {
        parent::setUp();

        $now = new \DateTimeImmutable('2018-04-26 12:42:23');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect($now));
        $nowAsUnixTimestamp = $now->getTimestamp();
        self::assertGreaterThan(0, $nowAsUnixTimestamp);

        $this->testingFramework = $this->get(TestingFramework::class);
        $systemFolderUid = $this->testingFramework->createSystemFolder();
        $this->testingFramework->createRecord(
            'sys_template',
            [
                'pid' => $systemFolderUid,
                'tstamp' => $nowAsUnixTimestamp,
                'sorting' => 256,
                'crdate' => $nowAsUnixTimestamp,
                'title' => 'TEST',
                'root' => 1,
                'clear' => 3,
                'include_static_file' => 'EXT:seminars/Configuration/TypoScript/',
            ],
        );
        $this->subjectUid = $this->testingFramework->createRecord(
            'tx_seminars_test',
            [
                'pid' => $systemFolderUid,
                'title' => 'Test',
            ],
        );
        $this->subject = new TestingModel($this->subjectUid);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    //////////////////////////////////
    // Tests for getting attributes.
    //////////////////////////////////

    /**
     * @test
     */
    public function getUid(): void
    {
        self::assertEquals(
            $this->subjectUid,
            $this->subject->getUid(),
        );
    }

    /**
     * @test
     */
    public function hasUidIsTrueForObjectsWithAUid(): void
    {
        self::assertNotEquals(
            0,
            $this->subjectUid,
        );
        self::assertTrue(
            $this->subject->hasUid(),
        );
    }

    /**
     * @test
     */
    public function hasUidIsFalseForObjectsWithoutUid(): void
    {
        $virginFixture = new TestingModel();

        self::assertEquals(
            0,
            $virginFixture->getUid(),
        );
        self::assertFalse(
            $virginFixture->hasUid(),
        );
    }

    /**
     * @test
     */
    public function getTitle(): void
    {
        self::assertEquals(
            'Test',
            $this->subject->getTitle(),
        );
    }

    //////////////////////////////////
    // Tests for setting attributes.
    //////////////////////////////////

    /**
     * @test
     */
    public function setAndGetRecordBooleanTest(): void
    {
        self::assertFalse(
            $this->subject->getBooleanTest(),
        );

        $this->subject->setBooleanTest(true);
        self::assertTrue(
            $this->subject->getBooleanTest(),
        );
    }

    /**
     * @test
     */
    public function setAndGetTitle(): void
    {
        $title = 'Test';
        $this->subject->setTitle($title);

        self::assertEquals(
            $title,
            $this->subject->getTitle(),
        );
    }

    // Tests concerning getPageUid

    /**
     * @test
     */
    public function getPageUidCanReturnRecordsPageUid(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_test',
            $this->subjectUid,
            ['pid' => 42],
        );
        $subject = new TestingModel($this->subjectUid);

        self::assertEquals(
            42,
            $subject->getPageUid(),
        );
    }

    /**
     * @test
     */
    public function getPageUidForRecordWithPageUidZeroReturnsZero(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_test',
            $this->subjectUid,
            ['pid' => 0],
        );
        $subject = new TestingModel($this->subjectUid);

        self::assertEquals(
            0,
            $subject->getPageUid(),
        );
    }
}
