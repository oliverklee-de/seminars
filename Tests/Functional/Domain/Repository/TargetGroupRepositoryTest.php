<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\TargetGroup;
use OliverKlee\Seminars\Domain\Repository\TargetGroupRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\TargetGroup
 * @covers \OliverKlee\Seminars\Domain\Repository\TargetGroupRepository
 */
final class TargetGroupRepositoryTest extends FunctionalTestCase
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

    private TargetGroupRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(TargetGroupRepository::class);
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TargetGroupRepository/propertyMapping/TargetGroupWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(TargetGroup::class, $result);
        self::assertSame('Profis', $result->getTitle());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TargetGroupRepository/TargetGroupOnPage.csv');

        $result = $this->subject->findAll();

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function sortsRecordsByTitleInAscendingOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TargetGroupRepository/TwoTargetGroupsInReverseOrder.csv');

        $result = $this->subject->findAll();

        self::assertCount(2, $result);
        $first = $result->getFirst();
        self::assertInstanceOf(TargetGroup::class, $first);
        self::assertSame('Anfänger', $first->getTitle());
    }
}
