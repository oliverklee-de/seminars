<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Support;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * This trait provides methods useful for initializing languages.
 *
 * @phpstan-require-extends FunctionalTestCase
 */
trait LanguageHelper
{
    private ?LanguageService $languageService = null;

    private function getLanguageService(): LanguageService
    {
        if (!$this->languageService instanceof LanguageService) {
            $languageService = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
            $languageService->includeLLFile('EXT:seminars/Resources/Private/Language/locallang.xlf');
            $this->languageService = $languageService;
            $GLOBALS['LANG'] = $languageService;
        }

        return $this->languageService;
    }

    /**
     * Sets $GLOBALS['LANG'].
     */
    private function initializeBackEndLanguage(): void
    {
        $GLOBALS['LANG'] = $this->getLanguageService();
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
