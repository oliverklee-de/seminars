<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\BackEnd;

use OliverKlee\Seminars\BackEnd\Permissions;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\BackEnd\Permissions
 */
final class PermissionsTest extends FunctionalTestCase
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
    public function isAvailableViaContainer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Permissions/BackEndUser.csv');
        $this->setUpBackendUser(1);

        self::assertInstanceOf(Permissions::class, $this->get(Permissions::class));
    }
}
