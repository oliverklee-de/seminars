<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Repository\PaymentMethodRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\PaymentMethod
 * @covers \OliverKlee\Seminars\Domain\Repository\PaymentMethodRepository
 */
final class PaymentMethodRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private PaymentMethodRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(PaymentMethodRepository::class);
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PaymentMethodRepository/PaymentMethodWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(PaymentMethod::class, $result);
        self::assertSame('invoice', $result->getTitle());
    }
}
