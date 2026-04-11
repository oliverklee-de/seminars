<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Support;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @phpstan-require-extends FunctionalTestCase
 */
trait BackEndTestsTrait
{
    use LanguageHelper;

    /**
     * @var array<mixed>
     */
    private array $getBackup = [];

    /**
     * @var array<mixed>
     */
    private array $postBackup = [];

    private ?BackendUserAuthentication $backEndUserBackup = null;

    private string $languageBackup = '';

    private array $extConfBackup = [];

    private array $t3VarBackup = [];

    private DummyConfiguration $configuration;

    /**
     * @var positive-int
     */
    private int $now;

    /**
     * Replaces the current BE user with a mocked user, sets "default" as the current BE language, clears the
     * seminars extension settings, disables the automatic configuration check, sets the header proxy to test mode,
     * and sets a fixed `SIM_EXEC_TIME`.
     *
     * If you use this method, make sure to call `restoreOriginalEnvironment()` in `tearDown()`.
     */
    private function unifyTestingEnvironment(): void
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('2018-04-26 12:42:23')));
        $this->now = (int)$context->getPropertyFromAspect('date', 'timestamp');

        $this->cleanRequestVariables();
        $this->replaceBackEndUserWithMock();
        $this->unifyBackEndLanguage();
        $this->unifyExtensionSettings();
        $this->setUpExtensionConfiguration();
    }

    private function cleanRequestVariables(): void
    {
        GeneralUtility::flushInternalRuntimeCaches();
        unset($GLOBALS['TYPO3_REQUEST']);
        $this->getBackup = $_GET;
        $_GET = [];
        $this->postBackup = $_POST;
        $_POST = [];
    }

    private function replaceBackEndUserWithMock(): void
    {
        $currentBackEndUser = $GLOBALS['BE_USER'] ?? null;
        if ($currentBackEndUser instanceof BackendUserAuthentication) {
            $this->backEndUserBackup = $currentBackEndUser;
        }
        $mockBackEndUser = $this->createPartialMock(
            BackendUserAuthentication::class,
            ['check', 'doesUserHaveAccess', 'setAndSaveSessionData', 'writeUC'],
        );
        $mockBackEndUser->method('check')->willReturn(true);
        $mockBackEndUser->method('doesUserHaveAccess')->willReturn(true);
        $mockBackEndUser->user['uid'] = 1;
        $GLOBALS['BE_USER'] = $mockBackEndUser;
    }

    private function unifyBackEndLanguage(): void
    {
        $currentLanguageService = $GLOBALS['LANG'] ?? null;
        if ($currentLanguageService instanceof LanguageService) {
            $this->languageBackup = $currentLanguageService->lang;
        }

        $newLanguageService = $this->getLanguageService();
        $newLanguageService->lang = 'default';

        $newLanguageService->includeLLFile('EXT:core/Resources/Private/Language/locallang_general.xlf');
        $newLanguageService->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
        $newLanguageService->includeLLFile('EXT:seminars/Resources/Private/Language/locallang_db.xlf');

        $GLOBALS['LANG'] = $newLanguageService;
    }

    private function unifyExtensionSettings(): void
    {
        $extConf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] ?? null;
        $this->extConfBackup = \is_array($extConf) ? $extConf : [];
        $t3var = $GLOBALS['T3_VAR']['getUserObj'] ?? null;
        $this->t3VarBackup = \is_array($t3var) ? $t3var : [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'] = [];
    }

    private function setUpExtensionConfiguration(): void
    {
        $configurationRegistry = ConfigurationRegistry::getInstance();
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);
    }

    private function restoreOriginalEnvironment(): void
    {
        if ($this->backEndUserBackup !== null) {
            $GLOBALS['BE_USER'] = $this->backEndUserBackup;
        }
        if ($this->languageBackup !== '') {
            $this->getLanguageService()->lang = $this->languageBackup;
        }
        if ($this->extConfBackup !== []) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'] = $this->extConfBackup;
        }
        if ($this->t3VarBackup !== []) {
            $GLOBALS['T3_VAR']['getUserObj'] = $this->t3VarBackup;
        }
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        unset($GLOBALS['TYPO3_REQUEST']);
        GeneralUtility::flushInternalRuntimeCaches();
    }

    /**
     * @param non-empty-string $key
     */
    private function translate(string $key): string
    {
        $label = LocalizationUtility::translate($key, 'seminars');
        \assert(is_string($label));

        return $label;
    }
}
