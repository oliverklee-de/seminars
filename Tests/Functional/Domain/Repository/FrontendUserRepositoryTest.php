<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Repository;

use OliverKlee\FeUserExtraFields\Domain\Model\Gender;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\FrontendUser
 * @covers \OliverKlee\Seminars\Domain\Repository\FrontendUserRepository
 */
final class FrontendUserRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private FrontendUserRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(FrontendUserRepository::class);
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
            __DIR__ . '/Fixtures/FrontendUserRepository/propertyMapping/FrontendUserWithAllScalarData.csv',
        );

        $model = $this->subject->findByUid(1);

        self::assertInstanceOf(FrontendUser::class, $model);
        self::assertEquals(new \DateTime('2019-01-01 00:00:00'), $model->getCreationDate());
        self::assertEquals(new \DateTime('2023-01-01 00:00:00'), $model->getModificationDate());
        self::assertSame('max', $model->getUsername());
        self::assertSame('luif3ui4t12', $model->getPassword());
        self::assertSame('Max M. Minimau', $model->getName());
        self::assertSame('Max', $model->getFirstName());
        self::assertSame('Murri', $model->getMiddleName());
        self::assertSame('Minimau', $model->getLastName());
        self::assertSame('Near the heating 4', $model->getAddress());
        self::assertSame('+49 1111 1233456-78', $model->getTelephone());
        self::assertSame('max@example.com', $model->getEmail());
        self::assertSame('Head of fur', $model->getTitle());
        self::assertSame('01234', $model->getZip());
        self::assertSame('Kattingen', $model->getCity());
        self::assertSame('United States of CAT', $model->getCountry());
        self::assertSame('www.example.com', $model->getWww());
        self::assertSame('Cat Scans Inc.', $model->getCompany());
        self::assertSame('DE123456789', $model->getVatIn());
        self::assertEquals(new \DateTime('2022-04-02T18:00'), $model->getLastLogin());
        self::assertTrue($model->getPrivacy());
        self::assertEquals(new \DateTime('2024-04-13T09:20'), $model->getPrivacyDateOfAcceptance());
        self::assertTrue($model->hasTermsAcknowledged());
        self::assertEquals(new \DateTime('2024-05-18T02:40'), $model->getTermsDateOfAcceptance());
        self::assertSame('NRW', $model->getZone());
        self::assertSame('Welcome, Max MM!', $model->getFullSalutation());
        self::assertSame(Gender::diverse(), $model->getGender());
        self::assertEquals(new \DateTime('2022-04-02T00:00'), $model->getDateOfBirth());
        self::assertSame(FrontendUser::STATUS_JOB_SEEKING_FULL_TIME, $model->getStatus());
        self::assertSame('Here we go!', $model->getComments());
        self::assertSame(17, $model->getDefaultOrganizerUid());
        self::assertSame([1, 2], $model->getUidsOfAvailableTopicsForFrontEndEditor());
    }
}
