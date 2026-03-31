<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\ViewHelpers;

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

/**
 * @covers \OliverKlee\Seminars\ViewHelpers\SalutationAwareTranslateViewHelper
 */
final class SalutationAwareTranslateViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private StandardVariableProvider $variableProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $this->variableProvider = new StandardVariableProvider();
    }

    private function embedInHtmlWithNamespace(string $html): string
    {
        return '<html xmlns:s="OliverKlee\Seminars\ViewHelpers" data-namespace-typo3-fluid="true">' .
            $html . '</html>';
    }

    private function renderViewHelper(string $html): string
    {
        $view = new StandaloneView();

        $renderingContext = $view->getRenderingContext();
        $renderingContext->setVariableProvider($this->variableProvider);
        $view->setRenderingContext($renderingContext);

        $view->setTemplateSource($this->embedInHtmlWithNamespace($html));

        return $view->render();
    }

    /**
     * @test
     */
    public function renderForInexistentLabelKeyReturnsLabelKey(): void
    {
        $key = 'translation-without-match';
        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="' . $key . '" />');

        self::assertSame($key, $result);
    }

    /**
     * @test
     */
    public function renderRendersViewHelperArguments(): void
    {
        $result = $this->renderViewHelper(
            '<s:salutationAwareTranslate key="test-label-with-arguments" arguments="{0: \'Oli\'}"/>',
        );

        self::assertSame('Hello Oli!', $result);
    }

    /**
     * @test
     */
    public function renderWithoutAnySettingsWithFullLabelPathCanRenderLabelWithoutSalutationVersions(): void
    {
        $result = $this->renderViewHelper(
            '<s:salutationAwareTranslate key="LLL:EXT:seminars/Resources/Private/Language/locallang.xlf:test-label" />',
        );

        self::assertSame('This is a test label.', $result);
    }

    /**
     * @test
     */
    public function renderWithoutAnySettingsWithShortLabelPathCanRenderLabelWithoutSalutationVersions(): void
    {
        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="test-label" />');

        self::assertSame('This is a test label.', $result);
    }

    /**
     * @test
     */
    public function renderWithoutAnySettingsForLabelWithSalutationByDefaultRendersFormalLabel(): void
    {
        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="test-label-with-salutation" />');

        self::assertSame('This is a test label with a formal salutation.', $result);
    }

    /**
     * @test
     */
    public function renderWithoutAnySettingsForLabelWithSalutationAndFallbackByDefaultRendersFormalLabel(): void
    {
        $result = $this->renderViewHelper(
            '<s:salutationAwareTranslate key="test-label-with-salutation-and-fallback" />',
        );

        self::assertSame('This is a test label with a fallback and a formal salutation.', $result);
    }

    /**
     * @test
     */
    public function renderWithEmptySettingsForLabelWithSalutationByDefaultRendersFormalLabel(): void
    {
        $this->variableProvider->add('settings', []);

        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="test-label-with-salutation" />');

        self::assertSame('This is a test label with a formal salutation.', $result);
    }

    /**
     * @return array<string, array{0: 'formal'|'informal', 1: non-empty-string}>
     */
    public function salutationDataProvider(): array
    {
        return [
            'formal salutation' => ['formal', 'This is a test label with a formal salutation.'],
            'informal salutation' => ['informal', 'This is a test label with an informal salutation.'],
        ];
    }

    /**
     * @test
     *
     * @param non-empty-string $salutation
     *
     * @dataProvider salutationDataProvider
     */
    public function renderWithSalutationInSettingsForLabelWithSalutationByRendersLabelWithGivenSalutationMode(
        string $salutation,
        string $expectedResult
    ): void {
        $this->variableProvider->add('settings', ['salutation' => $salutation]);

        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="test-label-with-salutation" />');

        self::assertSame($expectedResult, $result);
    }

    /**
     * @test
     *
     * @param non-empty-string $salutation
     *
     * @dataProvider salutationDataProvider
     */
    public function renderWithSalutationInSettingsForLabelWithoutSalutationByRendersLabelWithoutSalutation(
        string $salutation
    ): void {
        $this->variableProvider->add('settings', ['salutation' => $salutation]);

        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="test-label" />');

        self::assertSame('This is a test label.', $result);
    }

    /**
     * @test
     *
     * @param non-empty-string $salutation
     *
     * @dataProvider salutationDataProvider
     */
    public function renderWithSalutationInSettingsForInexistentLabelKeyReturnsLabelKey(string $salutation): void
    {
        $this->variableProvider->add('settings', ['salutation' => $salutation]);

        $key = 'translation-without-match';
        $result = $this->renderViewHelper('<s:salutationAwareTranslate key="' . $key . '" />');

        self::assertSame($key, $result);
    }
}
