<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\ViewHelpers;

use TYPO3\CMS\Fluid\ViewHelpers\TranslateViewHelper;
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
        $defaultArguments = self::buildDefaultArguments($arguments);

        $keyWithSalutation = self::buildKeyWithSalutation($defaultArguments, $renderingContext);
        $argumentsWithSalutation = self::buildArgumentsWithSalutation($defaultArguments, $keyWithSalutation);
        $labelWithSalutation = TranslateViewHelper::renderStatic(
            $argumentsWithSalutation,
            $renderChildrenClosure,
            $renderingContext,
        );

        return $labelWithSalutation !== $keyWithSalutation
            ? $labelWithSalutation
            : TranslateViewHelper::renderStatic($defaultArguments, $renderChildrenClosure, $renderingContext);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function buildDefaultArguments(array $arguments): array
    {
        $key = self::retrieveKeyFromArguments($arguments);

        $defaultArguments = $arguments;
        $defaultArguments['extensionName'] = 'seminars';
        $defaultArguments['default'] = $key;
        $defaultArguments['languageKey'] = $arguments['languageKey'] ?? null;
        $defaultArguments['key'] = $key;
        $defaultArguments['id'] = $key;
        $defaultArguments['alternativeLanguageKeys'] = $arguments['alternativeLanguageKeys'] ?? null;

        return $defaultArguments;
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

    private static function getSalutationMode(RenderingContextInterface $renderingContext): string
    {
        $settings = $renderingContext->getVariableProvider()->get('settings');
        if (!\is_array($settings)) {
            $settings = [];
        }
        return (string)($settings['salutation'] ?? 'formal');
    }

    /**
     * @param array<string, mixed> $defaultArguments
     *
     * @return array<string, mixed>
     */
    private static function buildArgumentsWithSalutation(array $defaultArguments, string $keyWithSalutation): array
    {
        $argumentsWithSalutation = $defaultArguments;
        $argumentsWithSalutation['key'] = $keyWithSalutation;
        $argumentsWithSalutation['id'] = $keyWithSalutation;
        $argumentsWithSalutation['default'] = $keyWithSalutation;

        return $argumentsWithSalutation;
    }
}
