<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\ViewHelpers;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * This class works like the `translate` view helper from Fluid with these two differences:
 *
 * - It will automatically use the localized labels from the "seminars" extension.
 * - It supports salutation versions of labels, depending on the `salutation` TypoScript setting.
 *
 * The salutation mode (`formal` or `informal`) is determined by the `salutation` TypoScript setting.
 *
 * The label key needs to have either the suffix `_formal` or `_informal` depending on the salutation mode.
 *
 * If you do not need salutation-specific versions of labels, you should use the `translate` view helper instead as
 * it is slightly faster than this one.
 */
class SalutationAwareTranslateViewHelper extends AbstractViewHelper
{
    private const EXTENSION_NAME = 'seminars';

    private const DEFAULT_SALUTATION = 'formal';

    /**
     * The output already is escaped. We must not escape children to avoid double encoding.
     *
     * @var bool
     */
    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'Translation key');
        $this->registerArgument('arguments', 'array', 'Arguments to be replaced in the resulting string');
    }

    public function render(): string
    {
        $key = $this->retrieveKeyFromArguments();
        $keyWithSalutation = $this->buildKeyWithSalutation();
        $translateArguments = \is_array($this->arguments['arguments']) ? $this->arguments['arguments'] : [];
        $labelWithSalutation = $this->translate($keyWithSalutation, $translateArguments);

        return ($labelWithSalutation !== $keyWithSalutation)
            ? $labelWithSalutation
            : $this->translate($key, $translateArguments);
    }

    private function buildKeyWithSalutation(): string
    {
        $salutation = $this->getSalutationMode();

        return $this->retrieveKeyFromArguments() . '_' . $salutation;
    }

    /**
     * @return non-empty-string
     */
    private function retrieveKeyFromArguments(): string
    {
        \assert(isset($this->arguments['key']));

        $result = $this->arguments['key'];
        \assert(\is_string($result) && $result !== '');

        return $result;
    }

    /**
     * @return non-empty-string
     */
    private function getSalutationMode(): string
    {
        $settings = $this->renderingContext->getVariableProvider()->get('settings');

        return (is_array($settings) && isset($settings['salutation']) && is_string($settings['salutation'])
            && $settings['salutation'] !== '')
            ? $settings['salutation']
            : self::DEFAULT_SALUTATION;
    }

    /**
     * @param array<mixed> $arguments
     */
    public function translate(string $key, array $arguments): string
    {
        $value = LocalizationUtility::translate($key, self::EXTENSION_NAME, $arguments);
        if (!is_string($value)) {
            $value = $key;
        }

        return $value;
    }
}
