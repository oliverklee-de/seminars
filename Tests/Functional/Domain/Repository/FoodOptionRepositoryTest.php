<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\FoodOption;
use OliverKlee\Seminars\Domain\Repository\FoodOptionRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\FoodOption
 * @covers \OliverKlee\Seminars\Domain\Repository\FoodOptionRepository
 */
final class FoodOptionRepositoryTest extends FunctionalTestCase
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

    private FoodOptionRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(FoodOptionRepository::class);
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FoodOptionRepository/FoodOptionWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(FoodOption::class, $result);
        self::assertSame('vegetarian', $result->getTitle());
    }
}
