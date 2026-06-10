<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\BagBuilder;

use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\Tests\Functional\BagBuilder\Fixtures\TestingBagBuilder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\AbstractBagBuilder
 */
final class AbstractBagBuilderTest extends FunctionalTestCase
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

    private TestingBagBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TestingBagBuilder();
    }

    private static function assertBagHasUid(AbstractBag $bag, int $uid): void
    {
        self::assertTrue(self::bagHasUid($bag, $uid), 'The bag does not have this UID: ' . $uid);
    }

    private static function assertBagNotHasUid(AbstractBag $bag, int $uid): void
    {
        self::assertFalse(self::bagHasUid($bag, $uid), 'The bag has this UID, but was expected not to: ' . $uid);
    }

    private static function bagHasUid(AbstractBag $bag, int $uid): bool
    {
        $found = false;

        foreach ($bag as $element) {
            if ($element->getUid() === $uid) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * @test
     */
    public function findsVisibleRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $bag = $this->subject->build();

        self::assertBagHasUid($bag, 1);
    }

    /**
     * @test
     */
    public function byDefaultIgnoresHiddenRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $bag = $this->subject->build();

        self::assertBagNotHasUid($bag, 2);
    }

    /**
     * @test
     */
    public function inBackEndModeFindsHiddenRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $this->subject->setBackEndMode();
        $bag = $this->subject->build();

        self::assertBagHasUid($bag, 2);
    }

    /**
     * @test
     */
    public function byDefaultIgnoresTimedRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $bag = $this->subject->build();

        self::assertBagNotHasUid($bag, 4);
    }

    /**
     * @test
     */
    public function inBackEndModeFindsTimedRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $this->subject->setBackEndMode();
        $bag = $this->subject->build();

        self::assertBagHasUid($bag, 4);
    }

    /**
     * @test
     */
    public function byDefaultIgnoresDeletedRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $bag = $this->subject->build();

        self::assertBagNotHasUid($bag, 3);
    }

    /**
     * @test
     */
    public function inBackEndModeIgnoresDeletedRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $this->subject->setBackEndMode();
        $bag = $this->subject->build();

        self::assertBagNotHasUid($bag, 3);
    }

    /**
     * @test
     */
    public function limitToTitleFindRecordWithMatchingTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $this->subject->limitToTitle('visible');
        $bag = $this->subject->build();

        self::assertBagHasUid($bag, 1);
    }

    /**
     * @test
     */
    public function limitToTitleIgnoresRecordWithNonMatchingTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Testing.csv');

        $this->subject->limitToTitle('some other title');
        $bag = $this->subject->build();

        self::assertBagNotHasUid($bag, 1);
    }
}
