<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\CopyBillingAddressToRegistrationsUpgradeWizard;
use Psr\Log\NullLogger;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\CopyBillingAddressToRegistrationsUpgradeWizard
 */
class CopyBillingAddressToRegistrationsUpgradeWizardTest extends FunctionalTestCase
{
    private const FIXTURES_PATH = __DIR__ . '/Fixtures/CopyBillingAddressToRegistrationsUpgradeWizard/';
    private const ASSERTIONS_PATH = __DIR__ . '/Assertions/CopyBillingAddressToRegistrationsUpgradeWizard/';

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

    private CopyBillingAddressToRegistrationsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(CopyBillingAddressToRegistrationsUpgradeWizard::class);
        $this->subject->setLogger(new NullLogger());
    }

    /**
     * @test
     */
    public function updateNecessaryForEmptyDatabaseReturnsFalse(): void
    {
        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithSeparateBillingAddressReturnsFalse(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithSeparateBillingAddress.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithUserReturnsTrue(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithUser.csv',
        );

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithDeletedUserReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithDeletedUser.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithHiddenUserReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithHiddenUser.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithMissingReferenceUserReturnsTrue(
    ): void {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithMissingReferencedUser.csv',
        );

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithoutUserReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithoutUser.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForHiddenRegistrationWithoutSeparateBillingAddressWithoutUserReturnsTrue(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'HiddenRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForDeletedRegistrationWithoutSeparateBillingAddressWithoutUserReturnsFalse(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'DeletedRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function executeUpdateKeepsExistingSeparateBillingAddressFlag(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithSeparateBillingAddress.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForRegistrationWithSeparateBillingAddressAndUserKeepsBillingEmailUnchanged(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithSeparateBillingAddressAndUserAndDifferentEmails.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressAndUserAndDifferentEmails.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateFlipsSeparateBillingAddressFlagToYes(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithUser.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithUser.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForDeletedUserKeepsSeparateBillingAddressFlagOnNo(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'DeletedRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'DeletedRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateForNoUserFlipsSeparateBillingAddressFlagToYes(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithoutUser.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithoutUser.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyCompanyFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithCompany.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCompany.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyFullNameFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithFullName.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithFullName.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForUserWithEmptyFullNameCopiesFirstAndLastNameFromUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithFirstAndLastName.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithFullName.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyStreetAddressFromUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithStreetAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithStreetAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyZipCodeFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithZipCode.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithZipCode.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyCityFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithCity.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCity.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyCountryFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithCountry.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCountry.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyPhoneNumberFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithPhoneNumber.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithPhoneNumber.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyEmailAddressFromUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithEmailAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyDataFromHiddenUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithEmailAddressInHiddenUser.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithEmailAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateDoesNotCopyDataFromDeletedUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithEmailAddressInDeletedUser.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithEmptyEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCanUpdateHiddenRegistration(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'HiddenRegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'HiddenRegistrationWithSeparateBillingAddressWithEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateKeepsDeletedRegistrationUnchanged(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'DeletedRegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'DeletedRegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCropsFullName(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithLongFullName.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCroppedFullName.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateForUserWithEmptyFullNameCropsFirstAndLastName(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithLongFirstAndLastName.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCroppedFullNameFromFirstAndLast.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCropsStreetAddress(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithLongStreetAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCroppedStreetAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCropsCity(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithLongCity.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCroppedCity.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCropsEmailAddress(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PATH . 'RegistrationWithoutSeparateBillingAddressWithLongEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::ASSERTIONS_PATH . 'RegistrationWithSeparateBillingAddressWithCroppedEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateForNoChangesReturnsTrue(): void
    {
        self::assertTrue($this->subject->executeUpdate());
    }

    /**
     * @test
     */
    public function executeUpdateForMultipleChangesReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PATH . 'RegistrationWithSeparateBillingAddress.csv');

        self::assertTrue($this->subject->executeUpdate());
    }
}
