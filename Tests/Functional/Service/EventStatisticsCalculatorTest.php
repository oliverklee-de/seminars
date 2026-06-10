<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Service;

use OliverKlee\Seminars\Service\EventStatisticsCalculator;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\EventStatisticsCalculator
 */
final class EventStatisticsCalculatorTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

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
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(EventStatisticsCalculator::class, $this->get(EventStatisticsCalculator::class));
    }
}
