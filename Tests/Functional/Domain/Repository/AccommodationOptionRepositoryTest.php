<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\AccommodationOption;
use OliverKlee\Seminars\Domain\Repository\AccommodationOptionRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\AccommodationOption
 * @covers \OliverKlee\Seminars\Domain\Repository\AccommodationOptionRepository
 */
final class AccommodationOptionRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private AccommodationOptionRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(AccommodationOptionRepository::class);
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AccommodationOptionRepository/AccommodationOptionWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(AccommodationOption::class, $result);
        self::assertSame('single room', $result->getTitle());
    }
}
