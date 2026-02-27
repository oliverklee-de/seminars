<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\LegacyRegistration
 */
final class LegacyRegistrationTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected array $coreExtensionsToLoad = ['typo3/cms-extensionmanager'];

    private LegacyRegistration $subject;

    private TestingFramework $testingFramework;

    private int $seminarUid = 0;

    private ConnectionPool $connectionPool;

    private DummyConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));

        $this->testingFramework = new TestingFramework('tx_seminars');
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);

        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $currenciesConnection = $this->connectionPool->getConnectionForTable('static_currencies');
        if ($currenciesConnection->count('*', 'static_currencies', []) === 0) {
            $currenciesConnection->insert(
                'static_currencies',
                [
                    'uid' => 49,
                    'cu_iso_3' => 'EUR',
                    'cu_iso_nr' => 978,
                    'cu_name_en' => 'Euro',
                    'cu_symbol_left' => '€',
                    'cu_thousands_point' => '.',
                    'cu_decimal_point' => ',',
                    'cu_decimal_digits' => 2,
                    'cu_sub_divisor' => 100,
                ],
            );
        }

        $this->configuration = new DummyConfiguration();
        $this->configuration->setAsString('templateFile', 'EXT:seminars/Resources/Private/Templates/Mail/e-mail.html');
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $this->configuration);

        $this->getLanguageService();

        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'test organizer',
                'email' => 'mail@example.com',
            ],
        );

        $this->seminarUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1, 'title' => 'foo_event'],
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $this->seminarUid,
            $organizerUid,
        );

        $feUserUid = $this->testingFramework->createFrontEndUser(
            '',
            [
                'name' => 'foo_user',
                'email' => 'foo@bar.com',
            ],
        );
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'title' => 'test title',
                'seminar' => $this->seminarUid,
                'interests' => 'nothing',
                'expectations' => '',
                'background_knowledge' => "foo\nbar",
                'known_from' => "foo\rbar",
                'user' => $feUserUid,
            ],
        );

        $this->subject = new LegacyRegistration($registrationUid);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    // Utility functions.

    /**
     * Inserts a payment method record into the database and creates a relation
     * to it from the fixture.
     *
     * @param array $paymentMethodData data of the payment method to add, may be empty
     *
     * @return int the UID of the created record, will always be > 0
     */
    private function setPaymentMethodRelation(array $paymentMethodData): int
    {
        $uid = $this->testingFramework->createRecord('tx_seminars_payment_methods', $paymentMethodData);

        $this->subject->setMethodOfPaymentUid($uid);

        return $uid;
    }

    // Tests for the utility functions.

    /**
     * @test
     */
    public function setPaymentMethodRelationReturnsUid(): void
    {
        self::assertTrue(
            $this->setPaymentMethodRelation([]) > 0,
        );
    }

    /**
     * @test
     */
    public function setPaymentMethodRelationCreatesNewUid(): void
    {
        self::assertNotEquals(
            $this->setPaymentMethodRelation([]),
            $this->setPaymentMethodRelation([]),
        );
    }

    /**
     * @test
     */
    public function isOk(): void
    {
        self::assertTrue($this->subject->isOk());
    }

    // Tests regarding the status.

    /**
     * @test
     */
    public function getStatusInitiallyReturnsRegular(): void
    {
        self::assertSame('regular', $this->subject->getStatus());
    }

    /**
     * @return array<string, array{0: Registration::STATUS_*, 1: non-empty-string}>
     */
    public static function statusDataProvider(): array
    {
        return [
            'regular' => [Registration::STATUS_REGULAR, 'regular'],
            'waiting list' => [Registration::STATUS_WAITING_LIST, 'waiting list'],
            'nonbinding reservation' => [Registration::STATUS_NONBINDING_RESERVATION, 'nonbinding reservation'],
        ];
    }

    /**
     * @test
     *
     * @param Registration::STATUS_* $status
     * @dataProvider statusDataProvider
     */
    public function getStatusReturnsLabelForStatus(int $status, string $label): void
    {
        $this->subject->setStatus($status);

        self::assertSame($label, $this->subject->getStatus());
    }

    // Tests regarding getting the registration data.

    /**
     * @test
     */
    public function getRegistrationDataWithKeyMethodOfPaymentReturnsMethodOfPayment(): void
    {
        $title = 'Test payment method';
        $this->setPaymentMethodRelation(['title' => $title]);

        self::assertStringContainsString(
            $title,
            $this->subject->getRegistrationData('method_of_payment'),
        );
    }

    /**
     * @test
     */
    public function getRegistrationDataForRegisteredThemselvesFalseReturnsLabelNo(): void
    {
        $this->subject->setRegisteredThemselves(false);

        self::assertSame(
            $this->translate('label_no'),
            $this->subject->getRegistrationData('registered_themselves'),
        );
    }

    /**
     * @test
     */
    public function getRegistrationDataForRegisteredThemselvesTrueReturnsLabelYes(): void
    {
        $this->subject->setRegisteredThemselves(true);

        self::assertSame(
            $this->translate('label_yes'),
            $this->subject->getRegistrationData('registered_themselves'),
        );
    }

    // Tests concerning dumpAttendanceValues

    /**
     * @test
     */
    public function dumpAttendanceValuesCanContainUid(): void
    {
        self::assertStringContainsString(
            (string)$this->subject->getUid(),
            $this->subject->dumpAttendanceValues('uid'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesContainsInterestsIfRequested(): void
    {
        self::assertStringContainsString(
            'nothing',
            $this->subject->dumpAttendanceValues('interests'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesContainsInterestsIfRequestedEvenForSpaceAfterCommaInKeyList(): void
    {
        self::assertStringContainsString(
            'nothing',
            $this->subject->dumpAttendanceValues('email, interests'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesContainsInterestsIfRequestedEvenForSpaceBeforeCommaInKeyList(): void
    {
        self::assertStringContainsString(
            'nothing',
            $this->subject->dumpAttendanceValues('interests ,email'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesContainsLabelForInterestsIfRequested(): void
    {
        self::assertStringContainsString(
            $this->translate('label_interests'),
            $this->subject->dumpAttendanceValues('interests'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesContainsLabelEvenForSpaceAfterCommaInKeyList(): void
    {
        self::assertStringContainsString(
            $this->translate('label_interests'),
            $this->subject->dumpAttendanceValues('interests, expectations'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesContainsLabelEvenForSpaceBeforeCommaInKeyList(): void
    {
        self::assertStringContainsString(
            $this->translate('label_interests'),
            $this->subject->dumpAttendanceValues('interests ,expectations'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesForDataWithLineFeedStartsDataOnNewLine(): void
    {
        self::assertStringContainsString(
            "\nfoo\nbar",
            $this->subject->dumpAttendanceValues('background_knowledge'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesForDataWithCarriageReturnStartsDataOnNewLine(): void
    {
        self::assertStringContainsString(
            "\nfoo\nbar",
            $this->subject->dumpAttendanceValues('known_from'),
        );
    }

    /**
     * @test
     */
    public function dumpAttendanceValuesCanContainNonRegisteredField(): void
    {
        self::assertStringContainsString(
            'label_pid: 0',
            $this->subject->dumpAttendanceValues('pid'),
        );
    }

    /**
     * @return string[][]
     */
    public function dumpableRegistrationFieldsDataProvider(): array
    {
        $fields = [
            'uid',
            'interests',
            'expectations',
            'background_knowledge',
            'lodgings',
            'accommodation',
            'foods',
            'food',
            'known_from',
            'notes',
            'checkboxes',
            'price',
            'seats',
            'total_price',
            'attendees_names',
            'kids',
            'method_of_payment',
            'company',
            'gender',
            'name',
            'address',
            'zip',
            'city',
            'country',
            'telephone',
            'email',
        ];

        $result = [];
        foreach ($fields as $field) {
            $result[$field] = [$field];
        }

        return $result;
    }

    /**
     * @test
     *
     * @param string $fieldName
     *
     * @dataProvider dumpableRegistrationFieldsDataProvider
     */
    public function dumpAttendanceValuesCreatesNoDoubleColonsAfterLabel(string $fieldName): void
    {
        $subject = LegacyRegistration::fromData([$fieldName => '1234 some value', 'seminar' => 1]);

        $result = $subject->dumpAttendanceValues($fieldName);

        self::assertStringNotContainsString('::', $result);
    }

    // Tests for setting and getting the user data

    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function instantiationWithoutLoggedInUserDoesNotThrowException(): void
    {
        $this->testingFramework->logoutFrontEndUser();

        new LegacyRegistration(
            $this->testingFramework->createRecord(
                'tx_seminars_attendances',
                ['seminar' => $this->seminarUid],
            ),
        );
    }

    // Tests regarding hasExistingFrontEndUser().

    /**
     * @test
     */
    public function hasExistingFrontEndUserWithExistingFrontEndUserReturnsTrue(): void
    {
        self::assertTrue(
            $this->subject->hasExistingFrontEndUser(),
        );
    }

    /**
     * @test
     */
    public function hasExistingFrontEndUserWithInexistentFrontEndUserReturnsFalse(): void
    {
        $userUid = $this->subject->getUser();
        \assert($userUid > 0);

        $this->testingFramework->changeRecord(
            'fe_users',
            $userUid,
            ['deleted' => 1],
        );

        self::assertFalse(
            $this->subject->hasExistingFrontEndUser(),
        );
    }

    /**
     * @test
     */
    public function hasExistingFrontEndUserWithZeroFrontEndUserUidReturnsFalse(): void
    {
        $this->subject->setFrontEndUserUid(0);

        self::assertFalse(
            $this->subject->hasExistingFrontEndUser(),
        );
    }

    // Tests regarding the total price.

    /**
     * @test
     */
    public function getTotalPriceWithoutTotalPriceReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->subject->getTotalPrice(),
        );
    }

    /**
     * @test
     */
    public function setTotalPriceWithTotalPriceSetsTotalPrice(): void
    {
        $this->configuration->setAsString('currency', 'EUR');
        $this->subject->setTotalPrice('42.42');

        self::assertSame(
            '€ 42,42',
            $this->subject->getTotalPrice(),
        );
    }

    /**
     * @test
     */
    public function hasTotalPriceWithoutTotalPriceReturnsFalse(): void
    {
        self::assertFalse(
            $this->subject->hasTotalPrice(),
        );
    }

    /**
     * @test
     */
    public function hasTotalPriceWithTotalPriceReturnsTrue(): void
    {
        $this->subject->setTotalPrice('42.42');

        self::assertTrue(
            $this->subject->hasTotalPrice(),
        );
    }

    // Tests concerning getEnumeratedAttendeeNames

    /**
     * @test
     */
    public function getEnumeratedAttendeeNamesWithUseHtmlSeparatesAttendeesNamesWithListItems(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => "foo\nbar"]);

        self::assertSame(
            '<ol><li>foo</li><li>bar</li></ol>',
            $subject->getEnumeratedAttendeeNames(true),
        );
    }

    /**
     * @test
     */
    public function getEnumeratedAttendeeNamesWithUseHtmlAndEmptyAttendeesNamesReturnsEmptyString(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => '']);

        self::assertSame(
            '',
            $subject->getEnumeratedAttendeeNames(true),
        );
    }

    /**
     * @test
     */
    public function getEnumeratedAttendeeNamesWithUsePlainTextSeparatesAttendeesNamesWithLineFeed(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => "foo\nbar"]);

        self::assertSame(
            "1. foo\n2. bar",
            $subject->getEnumeratedAttendeeNames(),
        );
    }

    /**
     * @test
     */
    public function getEnumeratedAttendeeNamesWithUsePlainTextAndEmptyAttendeesNamesReturnsEmptyString(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => '']);

        self::assertSame(
            '',
            $subject->getEnumeratedAttendeeNames(),
        );
    }

    /**
     * @test
     */
    public function getEnumeratedAttendeeNamesForSelfRegisteredUserAndNoAttendeeNamesReturnsUsersName(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => '']);
        $user = MapperRegistry::get(FrontEndUserMapper::class)->getLoadedTestingModel(['name' => 'foo_user']);
        $subject->setFrontEndUser($user);
        $subject->setRegisteredThemselves(true);

        self::assertSame(
            '1. foo_user',
            $subject->getEnumeratedAttendeeNames(),
        );
    }

    /**
     * @test
     */
    public function getEnumeratedAttendeeNamesForSelfRegisteredUserAndAttendeeNamesReturnsUserInFirstPosition(): void
    {
        $subject = LegacyRegistration::fromData(['attendees_names' => 'foo']);
        $user = MapperRegistry::get(FrontEndUserMapper::class)->getLoadedTestingModel(['name' => 'foo_user']);
        $subject->setFrontEndUser($user);
        $subject->setRegisteredThemselves(true);

        self::assertSame(
            "1. foo_user\n2. foo",
            $subject->getEnumeratedAttendeeNames(),
        );
    }
}
