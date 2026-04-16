<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\Seminars\Domain\Model\Speaker;
use OliverKlee\Seminars\Domain\Repository\SpeakerRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Speaker
 * @covers \OliverKlee\Seminars\Domain\Repository\SpeakerRepository
 */
final class SpeakerRepositoryTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/SpeakerRepository';

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private SpeakerRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(SpeakerRepository::class);
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
        $this->importCSVDataSet(self::FIXTURES_PATH . '/propertyMapping/SpeakerWithAllFields.csv');

        $result = $this->subject->findByUid(1);

        self::assertInstanceOf(Speaker::class, $result);
        self::assertSame('Dan Chase', $result->getName());
        self::assertSame('dan@example.com', $result->getEmailAddress());
        self::assertSame('Danish Institute for Theoretical Simulation', $result->getOrganization());
        self::assertSame('https://www.example.com/', $result->getHomepage());
    }

    /**
     * @test
     */
    public function findsRecordOnPages(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findAll/SpeakerOnPage.csv');

        $result = $this->subject->findAll();

        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function sortsRecordsByNameInAscendingOrder(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . '/findAll/TwoSpeakersInReverseOrder.csv');

        $result = $this->subject->findAll();

        self::assertCount(2, $result);
        $first = $result->getFirst();
        self::assertInstanceOf(Speaker::class, $first);
        self::assertSame('Earlier', $first->getName());
    }
}
