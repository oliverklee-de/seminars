<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\UpgradeWizards;

use OliverKlee\Seminars\UpgradeWizards\CopyBillingAddressToRegistrationsUpgradeWizard;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\CopyBillingAddressToRegistrationsUpgradeWizard
 */
class CopyBillingAddressToRegistrationsUpgradeWizardTest extends FunctionalTestCase
{
    private const FIXTURES_PREFIX = __DIR__ . '/Fixtures/CopyBillingAddressToRegistrationsUpgradeWizard/';

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private CopyBillingAddressToRegistrationsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = GeneralUtility::makeInstance(CopyBillingAddressToRegistrationsUpgradeWizard::class);
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
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddress.csv');

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithUserReturnsTrue(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithUser.csv',
        );

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithDeletedUserReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithDeletedUser.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithHiddenUserReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithHiddenUser.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithMissingReferenceUserReturnsTrue(
    ): void {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithMissingReferencedUser.csv',
        );

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForRegistrationWithoutSeparateBillingAddressWithoutUserReturnsTrue(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithoutUser.csv');

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForHiddenRegistrationWithoutSeparateBillingAddressWithoutUserReturnsTrue(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'HiddenRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );

        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function updateNecessaryForDeletedRegistrationWithoutSeparateBillingAddressWithoutUserReturnsFalse(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'DeletedRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );

        self::assertFalse($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function executeUpdateKeepsExistingSeparateBillingAddressFlag(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddress.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForRegistrationWithSeparateBillingAddressAndUserKeepsBillingEmailUnchanged(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressAndUserAndDifferentEmails.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressAndUserAndDifferentEmails.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateFlipsSeparateBillingAddressFlagToYes(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithUser.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithUser.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForDeletedUserKeepsSeparateBillingAddressFlagOnNo(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'DeletedRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'DeletedRegistrationWithoutSeparateBillingAddressWithoutUser.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateForNoUserFlipsSeparateBillingAddressFlagToYes(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithoutUser.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithoutUser.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyCompanyFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithCompany.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCompany.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyFullNameFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithFullName.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithFullName.csv');
    }

    /**
     * @test
     */
    public function executeUpdateForUserWithEmptyFullNameCopiesFirstAndLastNameFromUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithFirstAndLastName.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithFullName.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyStreetAddressFromUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithStreetAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithStreetAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyZipCodeFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithZipCode.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithZipCode.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyCityFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithCity.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCity.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyCountryFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithCountry.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCountry.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyPhoneNumberFromUser(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithPhoneNumber.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithPhoneNumber.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyEmailAddressFromUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithEmailAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCanCopyDataFromHiddenUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithEmailAddressInHiddenUser.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithEmailAddress.csv');
    }

    /**
     * @test
     */
    public function executeUpdateDoesNotCopyDataFromDeletedUser(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithEmailAddressInDeletedUser.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithEmptyEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCanUpdateHiddenRegistration(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'HiddenRegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'HiddenRegistrationWithSeparateBillingAddressWithEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateKeepsDeletedRegistrationUnchanged(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'DeletedRegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'DeletedRegistrationWithoutSeparateBillingAddressWithEmailAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCropsFullName(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithLongFullName.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCroppedFullName.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateForUserWithEmptyFullNameCropsFirstAndLastName(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithLongFirstAndLastName.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCroppedFullNameFromFirstAndLast.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCropsStreetAddress(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithLongStreetAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCroppedStreetAddress.csv',
        );
    }

    /**
     * @test
     */
    public function executeUpdateCropsCity(): void
    {
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithLongCity.csv');

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCroppedCity.csv');
    }

    /**
     * @test
     */
    public function executeUpdateCropsEmailAddress(): void
    {
        $this->importCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithoutSeparateBillingAddressWithLongEmailAddress.csv',
        );

        $this->subject->executeUpdate();

        $this->assertCSVDataSet(
            self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddressWithCroppedEmailAddress.csv',
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
        $this->importCSVDataSet(self::FIXTURES_PREFIX . 'RegistrationWithSeparateBillingAddress.csv');

        self::assertTrue($this->subject->executeUpdate());
    }
}
