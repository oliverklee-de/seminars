<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\RegistrationCheckbox;
use OliverKlee\Seminars\Domain\Repository\RegistrationCheckboxRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\RegistrationCheckbox
 * @covers \OliverKlee\Seminars\Domain\Repository\RegistrationCheckboxRepository
 */
final class RegistrationCheckboxRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RegistrationCheckboxRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(RegistrationCheckboxRepository::class);
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
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/RegistrationCheckboxRepository/RegistrationCheckboxWithAllFields.csv',
        );

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(RegistrationCheckbox::class, $result);
        self::assertSame('will contribute a session', $result->getTitle());
    }
}
