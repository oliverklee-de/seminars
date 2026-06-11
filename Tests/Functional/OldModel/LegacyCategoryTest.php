<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\OldModel;

use OliverKlee\Seminars\OldModel\LegacyCategory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyCategory
 */
final class LegacyCategoryTest extends FunctionalTestCase
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

    /**
     * @test
     */
    public function fromUidMapsDataFromDatabase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Categories.csv');

        $subject = LegacyCategory::fromUid(1);

        self::assertSame('Remote events', $subject->getTitle());
    }
}
