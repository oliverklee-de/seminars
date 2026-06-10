<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\BagBuilder;

use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\BagBuilder\EventBagBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\EventBagBuilder
 */
final class EventBagBuilderTest extends FunctionalTestCase
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

    private EventBagBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new EventBagBuilder();
    }

    private static function assertBagContainsUid(int $uid, AbstractBag $bag): void
    {
        $uids = GeneralUtility::intExplode(',', $bag->getUids(), true);
        self::assertContains($uid, $uids);
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithVacanciesAndOnlyOfflineAttendeesFindsThisEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToEventsWithVacanciesForEventWithOneVacancyFindsThisEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Events.csv');

        $this->subject->limitToEventsWithVacancies();
        $bag = $this->subject->build();

        self::assertBagContainsUid(2, $bag);
    }

    // Tests for limitToCategories

    /**
     * @test
     */
    public function limitToCategoriesWithEmptyStringsFindsEventWithoutCategories(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithoutCategories.csv');

        $this->subject->limitToCategories('');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesWithEmptyStringsFindsEventWithCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesWithEmptyStringResetsPreviousCategoryFilter(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithoutCategories.csv');

        $this->subject->limitToCategories('2');
        $this->subject->limitToCategories('');

        $bag = $this->subject->build();

        self::assertFalse($bag->isEmpty());
    }

    /**
     * @test
     */
    public function limitToCategoriesWithUidOfExistingCategoryFindsEventWithTheGivenCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('1');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesWithUidOfExistingAndInexistentCategoryFindsEventWithExistingCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('1,999');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public function nonPositiveIntegerDataProvider(): array
    {
        return [
            'zero' => [0],
            'negative int' => [-1],
        ];
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public function sqlStringCharacterDataProvider(): array
    {
        return [
            ';' => [';'],
            ',' => [','],
            '(' => ['('],
            ')' => [')'],
            'double quote' => ['"'],
            'single quote' => ["'"],
            'some random string' => ['There is no spoon.'],
        ];
    }

    /**
     * @test
     *
     * @param int|non-empty-string $invalidUid
     *
     * @dataProvider nonPositiveIntegerDataProvider
     * @dataProvider sqlStringCharacterDataProvider
     */
    public function limitToCategoriesSilentlyIgnoresInvalidUids($invalidUid): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('1,' . $invalidUid);

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesWithUidOfExistingCategoryIgnoresEventOnlyWithOtherCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('2');

        $bag = $this->subject->build();

        self::assertTrue($bag->isEmpty());
    }

    /**
     * @test
     */
    public function limitToCategoriesWithInexistentCategoryUidIgnoresWithCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('15');

        $bag = $this->subject->build();

        self::assertTrue($bag->isEmpty());
    }

    /**
     * @test
     */
    public function limitToCategoriesWithUidOfExistingCategoryIgnoresEventWithoutCategories(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithoutCategories.csv');

        $this->subject->limitToCategories('2');

        $bag = $this->subject->build();

        self::assertTrue($bag->isEmpty());
    }

    /**
     * @test
     */
    public function limitToCategoriesWithUidOfTwoExistingCategoriesFindsEventWithOneGivenCategory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithOneCategory.csv');

        $this->subject->limitToCategories('1,2');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesWithUidOfTwoExistingCategoriesFindsEventWithBothCategories(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/EventWithTwoCategories.csv');

        $this->subject->limitToCategories('1,2');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesFindsMatchingTopic(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/TopicWithOneCategory.csv');

        $this->subject->limitToCategories('1');

        $bag = $this->subject->build();

        self::assertBagContainsUid(1, $bag);
    }

    /**
     * @test
     */
    public function limitToCategoriesFindsDateOfMatchingTopic(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EventBagBuilder/TopicWithOneCategory.csv');

        $this->subject->limitToCategories('1');

        $bag = $this->subject->build();

        self::assertBagContainsUid(2, $bag);
    }
}
