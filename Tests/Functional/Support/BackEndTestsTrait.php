<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Support;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @phpstan-require-extends FunctionalTestCase
 */
trait BackEndTestsTrait
{
    use BackendLanguageTrait;

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

        $this->replaceBackEndUserWithMock();
        $this->unifyBackEndLanguage();
        $this->setUpExtensionConfiguration();
    }

    private function replaceBackEndUserWithMock(): void
    {
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
        $newLanguageService = $this->getLanguageService();
        $newLanguageService->lang = 'default';

        $newLanguageService->includeLLFile('EXT:core/Resources/Private/Language/locallang_general.xlf');
        $newLanguageService->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
        $newLanguageService->includeLLFile('EXT:seminars/Resources/Private/Language/locallang_db.xlf');

        $GLOBALS['LANG'] = $newLanguageService;
    }

    private function setUpExtensionConfiguration(): void
    {
        $configurationRegistry = $this->get(ConfigurationRegistry::class);
        $configurationRegistry->set('plugin', new DummyConfiguration());
        $this->configuration = new DummyConfiguration();
        $configurationRegistry->set('plugin.tx_seminars', $this->configuration);
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
