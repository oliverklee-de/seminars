<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\Category;
use OliverKlee\Seminars\Domain\Repository\CategoryRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Category
 * @covers \OliverKlee\Seminars\Domain\Repository\CategoryRepository
 */
final class CategoryRepositoryTest extends FunctionalTestCase
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

    private CategoryRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(CategoryRepository::class);
    }

    /**
     * @test
     */
    public function isRepository(): void
    {
        self::assertInstanceOf(Repository::class, $this->subject);
    }

    /**
     * @test
     */
    public function mapsAllModelFields(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryRepository/propertyMapping/CategoryWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(Category::class, $result);
        self::assertSame('cooking', $result->getTitle());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryRepository/CategoryOnPage.csv');

        $result = $this->subject->findAll();

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function sortsRecordsByTitleInAscendingOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryRepository/TwoCategoriesInReverseOrder.csv');

        $result = $this->subject->findAll();

        self::assertCount(2, $result);
        $first = $result->getFirst();
        self::assertInstanceOf(Category::class, $first);
        self::assertSame('cooking', $first->getTitle());
    }
}
