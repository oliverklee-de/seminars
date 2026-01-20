<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Domain\Model\FrontendUser as ExtbaseFrontEndUser;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyRegistration
 */
final class LegacyRegistrationTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private LegacyRegistration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeBackEndLanguage();
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', new DummyConfiguration());

        $this->subject = new LegacyRegistration();
    }

    protected function tearDown(): void
    {
        ConfigurationRegistry::purgeInstance();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function fromUidMapsDataFromDatabase(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Registrations.xml');

        $subject = LegacyRegistration::fromUid(1);

        self::assertSame(4, $subject->getSeats());
        self::assertSame(1, $subject->getUser());
        self::assertSame(1, $subject->getSeminar());
        self::assertSame('coding', $subject->getInterests());
        self::assertSame('good coffee', $subject->getExpectations());
        self::assertSame('latte art', $subject->getKnowledge());
        self::assertSame('word of mouth', $subject->getKnownFrom());
        self::assertSame('Looking forward to it!', $subject->getNotes());
        self::assertSame('Standard: 500.23€', $subject->getPrice());
        self::assertSame('vegetarian', $subject->getFood());
        self::assertSame('at home', $subject->getAccommodation());
        self::assertSame('Max Moe', $subject->getAttendeesNames());
        self::assertSame(2, $subject->getNumberOfKids());
        self::assertTrue($subject->hasRegisteredThemselves());
    }

    /**
     * @test
     */
    public function mapsFrontEndUser(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Registrations.xml');

        $subject = LegacyRegistration::fromUid(1);

        $user = $subject->getFrontEndUser();

        self::assertInstanceOf(FrontEndUser::class, $user);
        self::assertSame(1, $user->getUid());
    }

    /**
     * @test
     */
    public function mapsEvent(): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Registrations.xml');

        $subject = LegacyRegistration::fromUid(1);

        $event = $subject->getSeminarObject();

        self::assertInstanceOf(LegacyEvent::class, $event);
        self::assertSame(1, $event->getUid());
    }

    // Tests concerning dumpUserValues

    /**
     * @test
     */
    public function dumpUserValuesCanDumpName(): void
    {
        $name = 'Max Doe';
        $userData = ['name' => $name];
        $this->subject->setUserData($userData);

        $user = new FrontEndUser();
        $user->setData($userData);
        $this->subject->setFrontEndUser($user);

        $result = $this->subject->dumpUserValues('name');

        self::assertStringContainsString($name, $result);
    }

    /**
     * @test
     */
    public function dumpUserValuesForSpaceAroundCommaCanDumpTwoFields(): void
    {
        $name = 'Max Doe';
        $email = 'max@example.com';
        $userData = ['name' => $name, 'email' => $email];
        $this->subject->setUserData($userData);

        $user = new FrontEndUser();
        $user->setData($userData);
        $this->subject->setFrontEndUser($user);

        $result = $this->subject->dumpUserValues('name , email');

        self::assertStringContainsString($name, $result);
        self::assertStringContainsString($email, $result);
    }

    /**
     * @test
     */
    public function dumpUserValuesContainsLabel(): void
    {
        $email = 'max@example.com';
        $userData = ['email' => $email];
        $this->subject->setUserData($userData);

        $result = $this->subject->dumpUserValues('email');

        self::assertStringContainsString($this->translate('label_email'), $result);
    }

    /**
     * @test
     */
    public function dumpUserValuesForSpaceAroundCommaCanHaveTwoLabels(): void
    {
        $name = 'Max Doe';
        $email = 'max@example.com';
        $userData = ['name' => $name, 'email' => $email];
        $this->subject->setUserData($userData);

        $user = new FrontEndUser();
        $user->setData($userData);
        $this->subject->setFrontEndUser($user);

        $result = $this->subject->dumpUserValues('name , email');

        self::assertStringContainsString($this->translate('label_name'), $result);
        self::assertStringContainsString($this->translate('label_email'), $result);
    }

    /**
     * @test
     */
    public function dumpUserValuesDoesNotContainRawLabelNameAsLabelForPid(): void
    {
        $this->subject->setUserData(['pid' => 1234]);

        $result = $this->subject->dumpUserValues('pid');

        self::assertStringNotContainsString('label_pid', $result);
    }

    /**
     * @test
     */
    public function dumpUserValuesCanContainNonRegisteredField(): void
    {
        $this->subject->setUserData(['pid' => 1]);

        $result = $this->subject->dumpUserValues('pid');

        self::assertStringContainsString('Pid: 1', $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function userDateAndTimeFieldsDataProvider(): array
    {
        $fields = [
            'crdate',
            'tstamp',
        ];

        return $this->expandForDataProvider($fields);
    }

    /**
     * @test
     *
     * @param string $fieldName
     *
     * @dataProvider userDateAndTimeFieldsDataProvider
     */
    public function dumpUserValuesCanDumpDateAndTimeField(string $fieldName): void
    {
        $value = 1579816569;
        $this->subject->setUserData([$fieldName => $value]);

        $result = $this->subject->dumpUserValues($fieldName);

        $expected = \date('Y-m-d', $value) . ' ' . \date('H:i', $value);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function userDateFieldsDataProvider(): array
    {
        $fields = [
            'date_of_birth',
        ];

        return $this->expandForDataProvider($fields);
    }

    /**
     * @test
     *
     * @param string $fieldName
     *
     * @dataProvider userDateFieldsDataProvider
     */
    public function dumpUserValuesCanDumpDate(string $fieldName): void
    {
        $value = 1579816569;
        $this->subject->setUserData([$fieldName => $value]);

        $result = $this->subject->dumpUserValues($fieldName);

        $expected = \date('Y-m-d', $value);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function dumpableUserFieldsDataProvider(): array
    {
        $fields = [
            'uid',
            'username',
            'name',
            'first_name',
            'middle_name',
            'last_name',
            'address',
            'telephone',
            'email',
            'crdate',
            'title',
            'zip',
            'city',
            'country',
            'www',
            'company',
            'pseudonym',
            'gender',
            'date_of_birth',
            'mobilephone',
            'comments',
        ];

        return $this->expandForDataProvider($fields);
    }

    /**
     * @param list<non-empty-string> $fields
     *
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    private function expandForDataProvider(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = [$field];
        }

        return $result;
    }

    /**
     * @test
     *
     * @param non-empty-string $fieldName
     *
     * @dataProvider dumpableUserFieldsDataProvider
     */
    public function dumpUserValuesCreatesNoDoubleColonsAfterLabel(string $fieldName): void
    {
        $userData = [$fieldName => '1234 some value'];
        $this->subject->setUserData($userData);

        $user = new FrontEndUser();
        $user->setData($userData);
        $this->subject->setFrontEndUser($user);

        $result = $this->subject->dumpUserValues($fieldName);

        self::assertStringNotContainsString('::', $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function dumpableStringUserFieldsDataProvider(): array
    {
        $fields = [
            'username',
            'name',
            'first_name',
            'middle_name',
            'last_name',
            'address',
            'telephone',
            'email',
            'title',
            'zip',
            'city',
            'country',
            'www',
            'company',
            'pseudonym',
            'mobilephone',
            'comments',
        ];

        return $this->expandForDataProvider($fields);
    }

    /**
     * @test
     *
     * @param non-empty-string $fieldName
     *
     * @dataProvider dumpableStringUserFieldsDataProvider
     */
    public function dumpUserValuesCanDumpStringValues(string $fieldName): void
    {
        $value = 'some value';
        $userData = [$fieldName => $value];
        $this->subject->setUserData($userData);

        $user = new FrontEndUser();
        $user->setData($userData);
        $this->subject->setFrontEndUser($user);

        $result = $this->subject->dumpUserValues($fieldName);

        self::assertStringContainsString($value, $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public function dumpableIntegerUserFieldsDataProvider(): array
    {
        $fields = [
            'uid',
            'pid',
        ];

        return $this->expandForDataProvider($fields);
    }

    /**
     * @test
     *
     * @param non-empty-string $fieldName
     *
     * @dataProvider dumpableIntegerUserFieldsDataProvider
     */
    public function dumpUserValuesCanDumpIntegerValues(string $fieldName): void
    {
        $value = 1234;
        $userData = [$fieldName => $value];
        $this->subject->setUserData($userData);

        $user = new FrontEndUser();
        $user->setData($userData);
        $this->subject->setFrontEndUser($user);

        $result = $this->subject->dumpUserValues($fieldName);

        self::assertStringContainsString((string)$value, $result);
    }

    /**
     * @return array<string, array{0: ExtbaseFrontEndUser::GENDER_*}>
     */
    public function genderDataProvider(): array
    {
        return [
            'male' => [ExtbaseFrontEndUser::GENDER_MALE],
            'female' => [ExtbaseFrontEndUser::GENDER_FEMALE],
            'diverse' => [ExtbaseFrontEndUser::GENDER_DIVERSE],
        ];
    }

    /**
     * @test
     *
     * @param int $value
     *
     * @dataProvider genderDataProvider
     */
    public function dumpUserValuesCanDumpGender(int $value): void
    {
        $userData = ['gender' => $value];
        $this->subject->setUserData($userData);

        $result = $this->subject->dumpUserValues('gender');

        self::assertStringContainsString($this->translate('label_gender.I.' . $value), $result);
    }

    // Tests regarding the billing address

    /**
     * @test
     */
    public function getBillingAddressWithGenderMaleContainsLabelForGenderMale(): void
    {
        $subject = LegacyRegistration::fromData(['gender' => 0]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($this->translate('label_gender.I.0'), $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithGenderFemaleContainsLabelForGenderFemale(): void
    {
        $subject = LegacyRegistration::fromData(['gender' => 1]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($this->translate('label_gender.I.1'), $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithTelephoneNumberContainsTelephoneNumber(): void
    {
        $value = '01234-56789';
        $subject = LegacyRegistration::fromData(['telephone' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getBillingAddressWithEmailAddressContainsEmailAddress(): void
    {
        $value = 'max@example.com';
        $subject = LegacyRegistration::fromData(['email' => $value]);

        $result = $subject->getBillingAddress();

        self::assertStringContainsString($value, $result);
    }

    /**
     * @test
     */
    public function getRegistrationDataWithAttendanceModeForNoModeSetReturnsEmptyString(): void
    {
        $subject = LegacyRegistration::fromData(['attendance_mode' => Registration::ATTENDANCE_MODE_NOT_SET]);

        $result = $subject->getRegistrationData('attendance_mode');

        self::assertSame('', $result);
    }

    /**
     * @test
     */
    public function getRegistrationDataWithAttendanceModeForOnSiteRegistrationReturnsOnSiteLabel(): void
    {
        $subject = LegacyRegistration::fromData(['attendance_mode' => Registration::ATTENDANCE_MODE_ON_SITE]);

        $result = $subject->getRegistrationData('attendance_mode');

        $expected = $this->translate('label_attendance_mode.onSite');
        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getRegistrationDataWithAttendanceModeForOnlineRegistrationReturnsOnlineLabel(): void
    {
        $subject = LegacyRegistration::fromData(['attendance_mode' => Registration::ATTENDANCE_MODE_ONLINE]);

        $result = $subject->getRegistrationData('attendance_mode');

        $expected = $this->translate('label_attendance_mode.online');
        self::assertSame($expected, $result);
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: string}>
     */
    public static function userDataDataProvider(): array
    {
        return [
            'uid' => ['uid', '1'],
            'gender' => ['gender', 'Ms.'],
            'status' => ['status', 'Student'],
            'crdate' => ['crdate', '2024-12-12 12:00'],
            'tstamp' => ['tstamp', '2024-12-12 13:00'],
            'date_of_birth' => ['date_of_birth', '1985-04-10'],
            'name' => ['name', 'Max Caulfield'],
            'untransformed value' => ['city', 'Arcadia Bay'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $key
     *
     * @dataProvider userDataDataProvider
     */
    public function getUserDataReturnsDataFromExistingUser(string $key, string $expected): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyRegistration/RegistrationWithUser.csv');
        $subject = LegacyRegistration::fromUid(1);
        self::assertInstanceOf(LegacyRegistration::class, $subject);

        $result = $subject->getUserData($key);

        self::assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function getUserDataWithInexistentKeyReturnsEmptyString(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyRegistration/RegistrationWithUser.csv');
        $subject = LegacyRegistration::fromUid(1);
        self::assertInstanceOf(LegacyRegistration::class, $subject);

        $result = $subject->getUserData('favorite_movie');

        self::assertSame('', $result);
    }
}
