<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Email;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Email\SalutationBuilder;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Email\SalutationBuilder
 */
final class SalutationBuilderTest extends FunctionalTestCase
{
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
        $user = new FrontendUser();
        $user->setName($fullName);

        $result = $this->subject->getSalutation($user);

        self::assertStringContainsString($fullName, $result);
    }

    /**
     * @test
     */
    public function getSalutationReturnsGenderNeutralSalutation(): void
    {
        $user = new FrontendUser();
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
        $user = new FrontendUser();
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
        $user = new FrontendUser();
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
        $user = new FrontendUser();
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

        $user = new FrontendUser();
        $user->setName('Max Minimax');

        $result = $this->subject->getSalutation($user);

        self::assertNotContainsRawLabelKey($result);
    }
}
