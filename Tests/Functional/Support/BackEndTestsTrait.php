<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Support;

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
    use BackendLanguageTrait;

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
     * seminars extension settings, sets the header proxy to test mode, and sets a fixed `SIM_EXEC_TIME`.
     *
     * If you use this method, make sure to call `restoreOriginalEnvironment()` in `tearDown()`.
     */
    private function unifyTestingEnvironment(): void
    {
        $now = new \DateTimeImmutable('2018-04-26 12:42:23');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect($now));
        $nowAsUnixTimestamp = $now->getTimestamp();
        \assert($nowAsUnixTimestamp > 0);
        $this->now = $nowAsUnixTimestamp;

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
        $mockBackEndUser = $this
            ->getMockBuilder(BackendUserAuthentication::class)
            ->onlyMethods(['check', 'doesUserHaveAccess', 'setAndSaveSessionData', 'writeUC'])
            ->getMock();
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
        $configurationRegistry = $this->get(ConfigurationRegistry::class);
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
        \assert($label !== '');

        return $label;
    }
}
