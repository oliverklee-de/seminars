<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Email;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Email\SalutationBuilder;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingLegacyEvent;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Email\SalutationBuilder
 */
final class SalutationBuilderTest extends FunctionalTestCase
{
    private const DATE_FORMAT = 'Y-m-d';
    private const TIME_FORMAT = 'H:i';
    private const SECONDS_PER_HOUR = 3600;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private DummyConfiguration $configuration;

    private SalutationBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuration = new DummyConfiguration();
        $this->get(ConfigurationRegistry::class)->set('plugin.tx_seminars', $this->configuration);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/SalutationBuilder/AdminBackendUser.csv');
        $GLOBALS['LANG'] = $this
            ->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $this->subject = $this->get(SalutationBuilder::class);
    }

    private static function assertNotContainsRawLabelKey(string $string): void
    {
        self::assertStringNotContainsString('_', $string);
        self::assertStringNotContainsString('email', $string);
        self::assertStringNotContainsString('formal', $string);
        self::assertStringNotContainsString('salutation', $string);
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(SalutationBuilder::class, $this->get(SalutationBuilder::class));
    }

    /**
     * @return array<non-empty-string, array{0: string}>
     */
    public static function salutationModeDataProvider(): array
    {
        return [
            'formal' => ['formal'],
            'informal' => ['informal'],
            'empty' => [''],
        ];
    }

    /**
     * @test
     * @dataProvider salutationModeDataProvider
     */
    public function getSalutationForAllSalutationModesReturnsFullNameOfRegisteredUser(string $salutationMode): void
    {
        $this->configuration->setAsString('salutation', $salutationMode);

        $fullName = 'Max Minimax';
        $user = new FrontEndUser();
        $user->setName($fullName);

        $result = $this->subject->getSalutation($user);

        self::assertStringContainsString($fullName, $result);
    }

    /**
     * @test
     */
    public function getSalutationReturnsGenderNeutralSalutation(): void
    {
        $user = new FrontEndUser();
        $user->setName('Max Minimax');

        $result = $this->subject->getSalutation($user);

        $expected = LocalizationUtility::translate('email_hello_formal_99', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function getSalutationForInformalSalutationModeReturnsInformalSalutation(): void
    {
        $this->configuration->setAsString('salutation', 'informal');
        $user = new FrontEndUser();
        $user->setName('Max Minimax');

        $result = $this->subject->getSalutation($user);

        $expected = LocalizationUtility::translate('email_hello_informal', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function getSalutationForFormalSalutationModeReturnsFormalSalutation(): void
    {
        $this->configuration->setAsString('salutation', 'formal');
        $user = new FrontEndUser();
        $user->setName('Max Minimax');

        $result = $this->subject->getSalutation($user);

        $expected = LocalizationUtility::translate('email_hello_formal_99', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function getSalutationForEmptySalutationModeReturnsFormalSalutation(): void
    {
        $this->configuration->setAsString('salutation', '');
        $user = new FrontEndUser();
        $user->setName('Max Minimax');

        $result = $this->subject->getSalutation($user);

        $expected = LocalizationUtility::translate('email_hello_formal_99', 'seminars');
        self::assertIsString($expected);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     * @dataProvider salutationModeDataProvider
     */
    public function getSalutationForAllSalutationModesContainsNoRawLabelKeys(string $salutationMode): void
    {
        $this->configuration->setAsString('salutation', $salutationMode);

        $user = new FrontEndUser();
        $user->setName('Max Minimax');

        $result = $this->subject->getSalutation($user);

        self::assertNotContainsRawLabelKey($result);
    }

    // Tests concerning createIntroduction

    /**
     * @test
     */
    public function createIntroductionWithEmptyBeginThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$introductionBegin must not be empty.');
        $this->expectExceptionCode(1440109640);

        $event = TestingLegacyEvent::fromData(['begin_date' => 0]);

        // @phpstan-ignore argument.type (We're checking for a contract violation here.)
        $this->subject->createIntroduction('', $event);
    }

    /**
     * @test
     */
    public function createIntroductionForEventWithDateReturnsEventsDate(): void
    {
        $beginDate = 1779529905;
        $event = TestingLegacyEvent::fromData(['begin_date' => $beginDate]);

        $result = $this->subject->createIntroduction('%s', $event);

        $expected = \date(self::DATE_FORMAT, $beginDate);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createIntroductionForEventWithBeginAndEndDateOnDifferentDaysReturnsEventsDateFromTo(): void
    {
        $beginDate = 1779529905;
        $endDate = 1779929905;
        $event = TestingLegacyEvent::fromData(['begin_date' => $beginDate, 'end_date' => $endDate]);

        $result = $this->subject->createIntroduction('%s', $event);

        $expected = \date(self::DATE_FORMAT, $beginDate) . '-' . \date(self::DATE_FORMAT, $endDate);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createIntroductionForEventWithTimeReturnsEventsTime(): void
    {
        $beginDate = 1779529905;
        $event = TestingLegacyEvent::fromData(['begin_date' => $beginDate]);

        $result = $this->subject->createIntroduction('%s', $event);

        $expected = \date(self::TIME_FORMAT, $beginDate);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createIntroductionForEventWithStartAndEndOnOneDayReturnsTimeFromTo(): void
    {
        $beginDate = 1779529905;
        $endDate = 1779529905 + self::SECONDS_PER_HOUR;
        $event = TestingLegacyEvent::fromData(['begin_date' => $beginDate, 'end_date' => $endDate]);

        $result = $this->subject->createIntroduction('%s', $event);

        $timeToWithPlaceholders = LocalizationUtility::translate('email_timeTo', 'seminars');
        self::assertIsString($timeToWithPlaceholders);
        $timeInsert = \date(self::TIME_FORMAT, $beginDate) . ' ' . $timeToWithPlaceholders . ' '
            . \date(self::TIME_FORMAT, $endDate);
        $timeFromWithPlaceholders = LocalizationUtility::translate('email_timeFrom', 'seminars');
        self::assertIsString($timeFromWithPlaceholders);
        $expected = \sprintf($timeFromWithPlaceholders, $timeInsert);

        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createIntroductionForEventWithStartAndEndOnOneDayContainsDate(): void
    {
        $beginDate = 1779529905;
        $endDate = 1779529905 + self::SECONDS_PER_HOUR;
        $event = TestingLegacyEvent::fromData(['begin_date' => $beginDate, 'end_date' => $endDate]);

        $result = $this->subject->createIntroduction('%s', $event);

        $formattedDate = \date(self::DATE_FORMAT, $beginDate);
        self::assertStringContainsString($formattedDate, $result);
    }

    /**
     * @test
     * @dataProvider salutationModeDataProvider
     */
    public function createIntroductionForAllSalutationModesContainsNoRawLabelKeys(string $salutationMode): void
    {
        $this->configuration->setAsString('salutation', $salutationMode);

        $event = TestingLegacyEvent::fromData(['begin_date' => 1779529905]);

        $result = $this->subject->createIntroduction('%s', $event);

        self::assertNotContainsRawLabelKey($result);
    }
}
