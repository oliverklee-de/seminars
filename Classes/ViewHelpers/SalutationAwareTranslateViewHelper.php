<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\ViewHelpers;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

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
    use CompileWithRenderStatic;

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

    /**
     * @param array<string, mixed> $arguments
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        $key = self::retrieveKeyFromArguments($arguments);
        $keyWithSalutation = self::buildKeyWithSalutation($arguments, $renderingContext);
        $translateArguments = \is_array($arguments['arguments']) ? $arguments['arguments'] : [];
        $labelWithSalutation = self::translate($keyWithSalutation, $translateArguments);

        return ($labelWithSalutation !== $keyWithSalutation)
            ? $labelWithSalutation
            : self::translate($key, $translateArguments);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function buildKeyWithSalutation(
        array $arguments,
        RenderingContextInterface $renderingContext
    ): string {
        $salutation = self::getSalutationMode($renderingContext);

        return self::retrieveKeyFromArguments($arguments) . '_' . $salutation;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return non-empty-string
     */
    private static function retrieveKeyFromArguments(array $arguments): string
    {
        \assert(isset($arguments['key']));

        $result = $arguments['key'];
        \assert(\is_string($result) && $result !== '');

        return $result;
    }

    /**
     * @return non-empty-string
     */
    private static function getSalutationMode(RenderingContextInterface $renderingContext): string
    {
        $settings = $renderingContext->getVariableProvider()->get('settings');

        return (is_array($settings) && isset($settings['salutation']) && is_string($settings['salutation'])
            && $settings['salutation'] !== '')
            ? $settings['salutation']
            : self::DEFAULT_SALUTATION;
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function translate(string $key, array $arguments): string
    {
        $value = LocalizationUtility::translate($key, self::EXTENSION_NAME, $arguments);
        if (!is_string($value)) {
            $value = $key;
        }

        return $value;
    }
}
