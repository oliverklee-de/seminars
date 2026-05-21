<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\Organizer;
use OliverKlee\Seminars\Domain\Repository\OrganizerRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Organizer
 * @covers \OliverKlee\Seminars\Domain\Repository\OrganizerRepository
 */
final class OrganizerRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private OrganizerRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(OrganizerRepository::class);
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrganizerRepository/OrganizerWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(Organizer::class, $result);
        self::assertSame('Dan Chase', $result->getName());
        self::assertSame('dan@example.com', $result->getEmailAddress());
        self::assertSame('Always the best.', $result->getEmailFooter());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrganizerRepository/OrganizerOnPage.csv');

        $result = $this->subject->findAll();

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function sortsRecordsByNameInAscendingOrder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrganizerRepository/TwoOrganizersInReverseOrder.csv');

        $result = $this->subject->findAll();

        self::assertCount(2, $result);
        $first = $result->getFirst();
        self::assertInstanceOf(Organizer::class, $first);
        self::assertSame('Earlier', $first->getName());
    }
}
