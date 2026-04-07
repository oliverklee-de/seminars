<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Templating;

use OliverKlee\Oelib\Configuration\ConfigurationProxy;
use OliverKlee\Oelib\Exception\NotFoundException;
use OliverKlee\Oelib\Templating\Template;
use OliverKlee\Oelib\Templating\TemplateRegistry;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * This utility class provides some commonly-used functions for handling templates.
 *
 * @internal
 */
abstract class TemplateHelper
{
    private const LABEL_PATTERN = '/###(LABEL_([A-Z\\d_]+))###/';

    /**
     * @var list<false|''|0|'0'|null>
     */
    private const FALSY_VALUES = [null, false, '', 0, '0'];

    protected ?ContentObjectRenderer $cObj = null;

    /**
     * This is the incoming array by name `$this->prefixId` merged between POST and GET, POST taking precedence.
     * Eg. if the class name is `tx_myext`,
     * then the content of this array will be whatever comes into `&tx_myext[...]=...`
     *
     * @var array<string, string|int|float|array<mixed>>
     */
    public array $piVars = [
        'pointer' => '',
        // Used as a pointer for lists
        'mode' => '',
        // List mode
        'sword' => '',
        // Search word
        'sort' => '',
    ];

    /**
     * Local pointer variable array.
     *
     * Holds pointer information for the MVC-like approach Kasper initially proposed.
     *
     * @var array{
     *        descFlag: bool,
     *        maxPages: int,
     *        orderBy?: string,
     *        res_count: int<0, max>,
     *        results_at_a_time: int
     *      }
     */
    public array $internal = [
        'descFlag' => false,
        'maxPages' => 10,
        'res_count' => 0,
        'results_at_a_time' => 20,
    ];

    /**
     * Local Language content
     *
     * @var array<string, array<string, array<int, array<string, string>>>>
     */
    private array $LOCAL_LANG = [];

    /**
     * Flag that tells if the locallang file has been fetch (or tried to
     * be fetched) already.
     */
    private bool $LOCAL_LANG_loaded = false;

    /**
     * Pointer to the language to use.
     */
    private string $LLkey = '';

    /**
     * Pointer to alternative fall-back language to use.
     */
    private string $altLLkey = '';

    /**
     * Should normally be set in the main function with the TypoScript content passed to the method.
     *
     * $conf[LOCAL_LANG][_key_] is reserved for Local Language overrides.
     * $conf[userFunc] reserved for setting up the USER / USER_INT object. See TSref
     */
    public array $conf = [];

    /**
     * Property for accessing TypoScriptFrontendController centrally
     */
    protected TypoScriptFrontendController $frontendController;

    /**
     * @var non-empty-string the prefix used for CSS classes
     */
    protected string $prefixId = 'tx_seminars_pi1';

    /**
     * faking `$this->scriptRelPath` so the `locallang.xlf` file is found
     *
     * @var non-empty-string
     */
    private string $scriptRelPath = 'Resources/Private/Language/locallang.xlf';

    /**
     * @var non-empty-string the extension key
     */
    private string $extKey = 'seminars';

    /**
     * @var bool whether `init()` already has been called (in order to avoid duplicate calls)
     */
    private bool $isInitialized = false;

    /**
     * @var string the file name of the template set via TypoScript or FlexForms
     */
    private string $templateFileName = '';

    /**
     * @var Template|null this object's (only) template
     */
    private ?Template $template = null;

    /**
     * A list of language keys for which the localizations have been loaded
     * (or NULL if the list has not been compiled yet).
     *
     * @var list<string>|null
     */
    private ?array $availableLanguages = null;

    /**
     * An ordered list of language label suffixes that should be tried to get
     * localizations in the preferred order of formality (or NULL if the list
     * has not been compiled yet).
     *
     * @var list<'_formal'|'_informal'|''>|null
     */
    private ?array $suffixesToTry = null;

    /**
     * @var array<non-empty-string, string>
     */
    private array $translationCache = [];

    /**
     * Class Constructor (true constructor)
     * Initializes $this->piVars if $this->prefixId is set to any value
     * Will also set $this->LLkey based on the config.language setting.
     *
     * @param null $_ unused,
     */
    public function __construct($_ = null, ?TypoScriptFrontendController $frontendController = null)
    {
        if ($frontendController instanceof TypoScriptFrontendController) {
            $this->frontendController = $frontendController;
        } else {
            $realFrontEndController = $this->getFrontEndController();
            if ($realFrontEndController instanceof TypoScriptFrontendController) {
                $this->frontendController = $realFrontEndController;
            }
        }
        $this->extractPiVars();
        $this->LLkey = $this->frontendController->getLanguage()->getTypo3Language();

        $locales = GeneralUtility::makeInstance(Locales::class);
        if (\in_array($this->LLkey, $locales->getLocales(), true)) {
            foreach ($locales->getLocaleDependencies($this->LLkey) as $language) {
                $this->altLLkey .= $language . ',';
            }
            $this->altLLkey = \rtrim($this->altLLkey, ',');
        }
    }

    /**
     * This is a workaround for TYPO3 <= 11LTS where `ContentObjectRenderer` sets `$this->cObj` directly.
     * (TYPO3 12LTS uses the proper setter.)
     *
     * @param non-empty-string $name
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        if ($name !== 'cObj') {
            throw new \InvalidArgumentException(
                'Cannot set other properties than `cObj` via a magic setter.',
                1727698230,
            );
        }
        if (!$value instanceof ContentObjectRenderer) {
            throw new \InvalidArgumentException(
                'Can only set `cObj` to an instance of `ContentObjectRenderer`.',
                1727698270,
            );
        }

        $this->cObj = $value;
    }

    /**
     * Sets `$this->piVars` from `$_POST` and `$_GET`.
     */
    private function extractPiVars(): void
    {
        $prefixId = $this->prefixId;

        $postParameter = isset($_POST[$prefixId]) && \is_array($_POST[$prefixId]) ? $_POST[$prefixId] : [];
        $getParameter = isset($_GET[$prefixId]) && \is_array($_GET[$prefixId]) ? $_GET[$prefixId] : [];
        $mergedParameters = $getParameter;
        ArrayUtility::mergeRecursiveWithOverrule($mergedParameters, $postParameter);

        $this->piVars = $mergedParameters;
    }

    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer): void
    {
        $this->cObj = $contentObjectRenderer;
    }

    /**
     * Initializes the FE plugin stuff and reads the configuration.
     *
     * It is harmless if this function gets called multiple times as it
     * recognizes this and ignores all calls but the first one.
     *
     * This is merely a convenience function.
     *
     * If the parameter is omitted, the configuration for `plugin.tx_[extkey]` is
     * used instead, e.g., `plugin.tx_seminars`.
     *
     * @param array<string, mixed>|null $configuration TypoScript configuration for the plugin
     */
    public function init(?array $configuration = null): void
    {
        if ($this->isInitialized) {
            return;
        }

        if (\is_array($configuration)) {
            $this->conf = $configuration;
        }
        $this->ensureContentObject();

        $this->isInitialized = true;
    }

    /**
     * @internal
     */
    public function getContentObjectRenderer(): ?ContentObjectRenderer
    {
        return $this->cObj;
    }

    protected function isConfigurationCheckEnabled(): bool
    {
        return ConfigurationProxy::getInstance($this->extKey)->getAsBoolean('enableConfigCheck');
    }

    /**
     * Ensures that $this->cObj points to a valid content object.
     *
     * If this object already has a valid cObj, this function does nothing.
     *
     * If there is a front end and this object does not have a cObj yet, the cObj from the front end is used.
     *
     * If this object has no cObj and there is no front end, this function will do nothing.
     */
    protected function ensureContentObject(): void
    {
        if ($this->cObj instanceof ContentObjectRenderer) {
            return;
        }

        $frontEnd = $this->getFrontEndController();
        if ($frontEnd instanceof TypoScriptFrontendController) {
            $this->cObj = $frontEnd->cObj;
        }
    }

    /**
     * Checks that this object is properly initialized.
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * Gets a value from flexforms or TypoScript setup.
     * The priority lies on flexforms; if nothing is found there, the value
     * from TypoScript setup is returned. If there is no field with that name in TypoScript setup,
     * an empty string is returned.
     *
     * @param string $fieldName field name to extract
     * @param string $sheet sheet pointer, eg. "sDEF"
     * @param bool $ignoreFlexform whether to ignore the flexform values and just get the settings from TypoScript,
     *        may be empty
     *
     * @return string the value of the corresponding flexforms or TypoScript setup entry (may be empty)
     */
    private function getConfValue(
        string $fieldName,
        string $sheet = 'sDEF',
        bool $ignoreFlexform = false
    ): string {
        $configurationValueFromTypoScript = (string)($this->conf[$fieldName] ?? '');
        $contentObject = $this->cObj;
        if (!$contentObject instanceof ContentObjectRenderer) {
            return $configurationValueFromTypoScript;
        }

        $flexFormsData = $contentObject->data['pi_flexform'] ?? null;
        if (!$ignoreFlexform && \is_array($flexFormsData)) {
            $flexFormsValue = $this->pi_getFFvalue($contentObject->data['pi_flexform'], $fieldName, $sheet);
        } else {
            $flexFormsValue = null;
        }

        return !\in_array($flexFormsValue, self::FALSY_VALUES, true)
            ? $flexFormsValue : $configurationValueFromTypoScript;
    }

    /**
     * Gets a trimmed string value from flexforms or TypoScript setup.
     * The priority lies on flexforms; if nothing is found there, the value
     * from TypoScript setup is returned. If there is no field with that name in TS
     * setup, an empty string is returned.
     *
     * @param string $fieldName field name to extract
     * @param string $sheet sheet pointer, eg. "sDEF"
     * @param bool $ignoreFlexform
     *        whether to ignore the flexform values and just get the settings from TypoScript, may be empty
     *
     * @return string the trimmed value of the corresponding flexforms or TypoScript setup entry (may be empty)
     */
    public function getConfValueString(string $fieldName, string $sheet = 'sDEF', bool $ignoreFlexform = false): string
    {
        return \trim($this->getConfValue($fieldName, $sheet, $ignoreFlexform));
    }

    /**
     * Checks whether a string value from flexforms or TypoScript setup is set.
     * The priority lies on flexforms; if nothing is found there, the value
     * from TypoScript setup is checked. If there is no field with that name in TS
     * setup, FALSE is returned.
     *
     * @param string $fieldName field name to extract
     * @param string $sheet sheet pointer, eg. "sDEF"
     * @param bool $ignoreFlexform
     *        whether to ignore the flexform values and just get the settings from TypoScript, may be empty
     *
     * @return bool whether there is a non-empty value in the
     *                 corresponding flexforms or TypoScript setup entry
     */
    public function hasConfValueString(
        string $fieldName,
        string $sheet = 'sDEF',
        bool $ignoreFlexform = false
    ): bool {
        return $this->getConfValueString($fieldName, $sheet, $ignoreFlexform) !== '';
    }

    /**
     * Gets an integer value from flexforms or TypoScript setup.
     * The priority lies on flexforms; if nothing is found there, the value
     * from TypoScript setup is returned. If there is no field with that name in TS
     * setup, zero is returned.
     *
     * @param string $fieldName field name to extract
     * @param string $sheet sheet pointer, eg. "sDEF"
     *
     * @return int the int value of the corresponding flexforms or TypoScript setup entry
     */
    public function getConfValueInteger(string $fieldName, string $sheet = 'sDEF'): int
    {
        return (int)$this->getConfValue($fieldName, $sheet);
    }

    /**
     * Checks whether an integer value from flexforms or TypoScript setup is set and
     * non-zero. The priority lies on flexforms; if nothing is found there, the
     * value from TypoScript setup is checked. If there is no field with that name in
     * TypoScript setup, FALSE is returned.
     *
     * @param string $fieldName field name to extract
     * @param string $sheet sheet pointer, eg. "sDEF"
     *
     * @return bool whether there is a non-zero value in the
     *                 corresponding flexforms or TypoScript setup entry
     */
    public function hasConfValueInteger(string $fieldName, string $sheet = 'sDEF'): bool
    {
        return (bool)$this->getConfValueInteger($fieldName, $sheet);
    }

    /**
     * Gets a boolean value from flexforms or TypoScript setup.
     * The priority lies on flexforms; if nothing is found there, the value
     * from TypoScript setup is returned. If there is no field with that name in TS
     * setup, FALSE is returned.
     *
     * @param string $fieldName field name to extract
     * @param string $sheet sheet pointer, eg. "sDEF"
     *
     * @return bool the boolean value of the corresponding flexforms or
     *                 TypoScript setup entry
     */
    public function getConfValueBoolean(string $fieldName, string $sheet = 'sDEF'): bool
    {
        return (bool)$this->getConfValue($fieldName, $sheet);
    }

    /**
     * Sets a configuration value.
     *
     * This function is intended to be used for testing purposes only.
     *
     * @param non-empty-string $key key of the configuration property to set
     * @param mixed $value value of the configuration property, may be empty or zero
     */
    public function setConfigurationValue(string $key, $value): void
    {
        // @phpstan-ignore-next-line We are explicitly testing for a contract violation here.
        if ($key === '') {
            throw new \InvalidArgumentException('$key must not be empty', 1331489491);
        }

        $this->conf[$key] = $value;
    }

    /**
     * Gets the configuration.
     *
     * @return array<string, mixed> configuration array, might be empty
     */
    public function getConfiguration(): array
    {
        return $this->conf;
    }

    /**
     * Retrieves the plugin template file set in `$this->conf['templateFile']`
     * (or also via flexforms if TYPO3 mode is FE) and writes it to `$this->templateCode`.
     * The subparts will be written to $this->templateCache.
     *
     * @param bool $ignoreFlexform whether the settings in the Flexform should be ignored
     */
    public function getTemplateCode(bool $ignoreFlexform = false): void
    {
        // Trying to fetch the template code via `$this->cObj` in BE mode leads to
        // a non-catchable error in the `ContentObjectRenderer` class because the `cObj`
        // configuration array is not initialized properly.
        // As flexforms can be used in FE mode only, `$ignoreFlexform` is set true if we are in the BE mode.
        // By this, `$this->cObj->fileResource` can be sheltered from being called.
        if (!$this->getFrontEndController() instanceof TypoScriptFrontendController) {
            $ignoreFlexform = true;
        }

        $templateFileName = $this->getConfValueString('templateFile', 's_template_special', $ignoreFlexform);

        if (!$ignoreFlexform) {
            $templateFileName = GeneralUtility::getFileAbsFileName($templateFileName);
        }

        $this->templateFileName = $templateFileName;
    }

    /**
     * Returns the template object from the template registry for the file name
     * in $this->templateFileName.
     *
     * @return Template the template object for the template file name in `$this->templateFileName`
     */
    protected function getTemplate(): Template
    {
        if (!$this->template instanceof Template) {
            $this->template = TemplateRegistry::get($this->templateFileName);
        }

        return $this->template;
    }

    /**
     * Stores the given HTML template and retrieves all subparts, writing them
     * to $this->templateCache.
     *
     * The subpart names are automatically retrieved from $templateCode and
     * are used as array keys. For this, the ### are removed, but the names stay
     * uppercase.
     *
     * Example: The subpart ###MY_SUBPART### will be stored with the array key
     * 'MY_SUBPART'.
     *
     * @param string $templateCode the content of the HTML template
     */
    public function processTemplate(string $templateCode): void
    {
        $this->getTemplate()->processTemplate($templateCode);
    }

    /**
     * Sets a marker's content.
     *
     * Example: If the prefix is "field" and the marker name is "one", the
     * marker "###FIELD_ONE###" will be written.
     *
     * If the prefix is empty and the marker name is "one", the marker
     * "###ONE###" will be written.
     *
     * @param non-empty-string $markerName the marker's name without the ### signs, case-insensitive,
     *        will get uppercased
     * @param mixed $content the marker's content, may be empty
     * @param string $prefix prefix to the marker name (may be empty, case-insensitive, will get uppercased)
     */
    public function setMarker(string $markerName, $content, string $prefix = ''): void
    {
        $this->getTemplate()->setMarker($markerName, $content, $prefix);
    }

    /**
     * Gets a marker's content.
     *
     * @param non-empty-string $markerName the marker's name without the ### signs, case-insensitive,
     *        will get uppercased
     *
     * @return string the marker's content or an empty string if the marker has not been set before
     */
    public function getMarker(string $markerName): string
    {
        return $this->getTemplate()->getMarker($markerName);
    }

    /**
     * Sets a subpart's content.
     *
     * Example: If the prefix is "field" and the subpart name is "one", the
     * subpart "###FIELD_ONE###" will be written.
     *
     * If the prefix is empty and the subpart name is "one", the subpart
     * "###ONE###" will be written.
     *
     * @param non-empty-string $subpartName name without the ### signs, case-insensitive, will get uppercased
     * @param mixed $content the subpart's content, may be empty
     * @param string $prefix prefix to the subpart name (may be empty, case-insensitive, will get uppercased)
     *
     * @throws NotFoundException
     */
    public function setSubpart(string $subpartName, $content, string $prefix = ''): void
    {
        $this->getTemplate()->setSubpart($subpartName, $content, $prefix);
    }

    /**
     * Sets a marker based on whether the int content is non-zero.
     *
     * If (int)$content is non-zero, this function sets the marker's content, working
     * exactly like setMarker($markerName, $content, $markerPrefix).
     *
     * @param non-empty-string $markerName the marker's name without the ### signs, case-insensitive,
     *        will get uppercased
     * @param mixed $content content with which the marker will be filled, may be empty
     * @param string $markerPrefix to the marker name for setting (may be empty, case-insensitive, will get uppercased)
     *
     * @return bool TRUE if the marker content has been set, FALSE otherwise
     */
    public function setMarkerIfNotZero(string $markerName, $content, string $markerPrefix = ''): bool
    {
        return $this->getTemplate()->setMarkerIfNotZero($markerName, $content, $markerPrefix);
    }

    /**
     * Sets a marker based on whether the (string) content is non-empty.
     * If $content is non-empty, this function sets the marker's content,
     * working exactly like setMarker($markerName, $content, $markerPrefix).
     *
     * @param non-empty-string $markerName the marker's name without the ### signs, case-insensitive,
     *        will get uppercased
     * @param mixed $content content with which the marker will be filled, may be empty
     * @param string $markerPrefix prefix to the marker name for setting
     *        (may be empty, case-insensitive, will get uppercased)
     *
     * @return bool TRUE if the marker content has been set, FALSE otherwise
     */
    public function setMarkerIfNotEmpty(string $markerName, $content, string $markerPrefix = ''): bool
    {
        return $this->getTemplate()->setMarkerIfNotEmpty($markerName, $content, $markerPrefix);
    }

    /**
     * Checks whether a subpart is visible.
     *
     * Note: If the subpart to check does not exist, this function will return false.
     *
     * @param string $subpartName name of the subpart to check (without the ###)
     *
     * @return bool TRUE if the subpart is visible, FALSE otherwise
     */
    public function isSubpartVisible(string $subpartName): bool
    {
        return $this->getTemplate()->isSubpartVisible($subpartName);
    }

    /**
     * Takes a comma-separated list of subpart names and sets them to hidden. In
     * the process, the names are changed from 'aname' to '###BLA_ANAME###' and
     * used as keys.
     *
     * Example: If the prefix is "field" and the list is "one,two", the subparts
     * "###FIELD_ONE###" and "###FIELD_TWO###" will be hidden.
     *
     * If the prefix is empty and the list is "one,two", the subparts
     * "###ONE###" and "###TWO###" will be hidden.
     *
     * @param string $subparts comma-separated list of the subparts to hide
     *        (case-insensitive, will get uppercased)
     * @param string $prefix prefix to the subpart names (may be empty, case-insensitive, will get uppercased)
     */
    public function hideSubparts(string $subparts, string $prefix = ''): void
    {
        $this->getTemplate()->hideSubparts($subparts, $prefix);
    }

    /**
     * Takes an array of subpart names and sets them to hidden. In the process,
     * the names are changed from 'aname' to '###BLA_ANAME###' and used as keys.
     *
     * Example: If the prefix is "field" and the array has two elements "one"
     * and "two", the subparts "###FIELD_ONE###" and "###FIELD_TWO###" will be
     * hidden.
     *
     * If the prefix is empty and the array has two elements "one" and "two",
     * the subparts "###ONE###" and "###TWO###" will be hidden.
     *
     * @param array<string|int, non-empty-string> $subparts subpart names to hide
     *        (may be empty, case-insensitive, will get uppercased)
     * @param string $prefix prefix to the subpart names (may be empty, case-insensitive, will get uppercased)
     */
    public function hideSubpartsArray(array $subparts, string $prefix = ''): void
    {
        $this->getTemplate()->hideSubpartsArray($subparts, $prefix);
    }

    /**
     * Takes a comma-separated list of subpart names and unhides them if they
     * have been hidden beforehand.
     *
     * Note: All subpartNames that are provided with the second parameter will
     * not be unhidden. This is to avoid unhiding subparts that are hidden by
     * the configuration.
     *
     * In the process, the names are changed from 'aname' to '###BLA_ANAME###'.
     *
     * Example: If the prefix is "field" and the list is "one,two", the subparts
     * "###FIELD_ONE###" and "###FIELD_TWO###" will be unhidden.
     *
     * If the prefix is empty and the list is "one,two", the subparts
     * "###ONE###" and "###TWO###" will be unhidden.
     *
     * @param non-empty-string $subparts comma-separated list of subpart names to unhide
     *        (case-insensitive, will get uppercased)
     * @param string $permanentlyHiddenSubparts comma-separated list of subpart names that shouldn't get unhidden
     * @param string $prefix prefix to the subpart names (may be empty, case-insensitive, will get uppercased)
     */
    public function unhideSubparts(
        string $subparts,
        string $permanentlyHiddenSubparts = '',
        string $prefix = ''
    ): void {
        $this->getTemplate()->unhideSubparts(
            $subparts,
            $permanentlyHiddenSubparts,
            $prefix,
        );
    }

    /**
     * Takes an array of subpart names and unhides them if they have been hidden
     * beforehand.
     *
     * Note: All subpartNames that are provided with the second parameter will
     * not be unhidden. This is to avoid unhiding subparts that are hidden by
     * the configuration.
     *
     * In the process, the names are changed from 'aname' to '###BLA_ANAME###'.
     *
     * Example: If the prefix is "field" and the array has two elements "one"
     * and "two", the subparts "###FIELD_ONE###" and "###FIELD_TWO###" will be
     * unhidden.
     *
     * If the prefix is empty and the array has two elements "one" and "two",
     * the subparts "###ONE###" and "###TWO###" will be unhidden.
     *
     * @param array<non-empty-string> $subparts $subparts subpart names to unhide
     *       (may be empty, case-insensitive, will get uppercased)
     * @param string[] $permanentlyHiddenSubparts subpart names that shouldn't get unhidden
     * @param string $prefix prefix to the subpart names (may be empty, case-insensitive, will get uppercased)
     */
    public function unhideSubpartsArray(
        array $subparts,
        array $permanentlyHiddenSubparts = [],
        string $prefix = ''
    ): void {
        $this->getTemplate()->unhideSubpartsArray($subparts, $permanentlyHiddenSubparts, $prefix);
    }

    /**
     * Sets or hides a marker based on $condition.
     * If $condition is TRUE, this function sets the marker's content, working
     * exactly like setMarker($markerName, $content, $markerPrefix).
     * If $condition is FALSE, this function removes the wrapping subpart,
     * working exactly like hideSubparts($markerName, $wrapperPrefix).
     *
     * @param non-empty-string $markerName the marker's name without the ### signs,
     *        case-insensitive, will get uppercased
     * @param bool $condition if this is TRUE, the marker will be filled, otherwise the wrapped marker will be hidden
     * @param mixed $content content with which the marker will be filled, may be empty
     * @param string $markerPrefix prefix to the marker name for setting
     *        (may be empty, case-insensitive, will get uppercased)
     * @param string $wrapperPrefix prefix to the subpart name for hiding
     *       (may be empty, case-insensitive, will get uppercased)
     *
     * @return bool TRUE if the marker content has been set, FALSE if the subpart has been hidden
     */
    public function setOrDeleteMarker(
        string $markerName,
        bool $condition,
        $content,
        string $markerPrefix = '',
        string $wrapperPrefix = ''
    ): bool {
        return $this->getTemplate()->setOrDeleteMarker(
            $markerName,
            $condition,
            $content,
            $markerPrefix,
            $wrapperPrefix,
        );
    }

    /**
     * Sets or hides a marker based on whether the int content is non-zero.
     *
     * If (int)$content is non-zero, this function sets the marker's content,
     * working exactly like setMarker($markerName, $content,
     * $markerPrefix).
     * If (int)$condition is zero, this function removes the wrapping
     * subpart, working exactly like hideSubparts($markerName, $wrapperPrefix).
     *
     * @param non-empty-string $markerName the marker's name without the ### signs, case-insensitive,
     *        will get uppercased
     * @param mixed $content content with which the marker will be filled, may be empty
     * @param string $markerPrefix prefix to the marker name for setting
     *        (may be empty, case-insensitive, will get uppercased)
     * @param string $wrapperPrefix prefix to the subpart name for hiding
     *        (may be empty, case-insensitive, will get uppercased)
     *
     * @return bool TRUE if the marker content has been set, FALSE if the subpart has been hidden
     */
    public function setOrDeleteMarkerIfNotZero(
        string $markerName,
        $content,
        string $markerPrefix = '',
        string $wrapperPrefix = ''
    ): bool {
        return $this->getTemplate()->setOrDeleteMarkerIfNotZero(
            $markerName,
            $content,
            $markerPrefix,
            $wrapperPrefix,
        );
    }

    /**
     * Sets or hides a marker based on whether the (string) content is
     * non-empty.
     * If $content is non-empty, this function sets the marker's content,
     * working exactly like setMarker($markerName, $content,
     * $markerPrefix).
     * If $condition is empty, this function removes the wrapping subpart,
     * working exactly like hideSubparts($markerName, $wrapperPrefix).
     *
     * @param non-empty-string $markerName the marker's name without the ### signs, case-insensitive,
     *        will get uppercased
     * @param mixed $content content with which the marker will be filled, may be empty
     * @param string $markerPrefix prefix to the marker name for setting
     *        (may be empty, case-insensitive, will get uppercased)
     * @param string $wrapperPrefix prefix to the subpart name for hiding
     *        (may be empty, case-insensitive, will get uppercased)
     *
     * @return bool TRUE if the marker content has been set, FALSE if the subpart has been hidden
     */
    public function setOrDeleteMarkerIfNotEmpty(
        string $markerName,
        $content,
        string $markerPrefix = '',
        string $wrapperPrefix = ''
    ): bool {
        return $this->getTemplate()->setOrDeleteMarkerIfNotEmpty(
            $markerName,
            $content,
            $markerPrefix,
            $wrapperPrefix,
        );
    }

    /**
     * Retrieves a named subpart, recursively filling in its inner subparts
     * and markers. Inner subparts that are marked to be hidden will be
     * substituted with empty strings.
     *
     * This function either works on the subpart with the name $key or the
     * complete HTML template if $key is an empty string.
     *
     * @param string $key
     *        key of an existing subpart, for example 'LIST_ITEM' (without the ###),
     *        or an empty string to use the complete HTML template
     *
     * @return string the subpart content or an empty string if the
     *                subpart is hidden or the subpart name is missing
     */
    public function getSubpart(string $key = ''): string
    {
        return $this->getTemplate()->getSubpart($key);
    }

    /**
     * Retrieves a named subpart, recursively filling in its inner subparts
     * and markers. Inner subparts that are marked to be hidden will be
     * substituted with empty strings.
     *
     * This function either works on the subpart with the name $key or the
     * complete HTML template if $key is an empty string.
     *
     * All label markers in the rendered subpart are automatically replaced with their corresponding localized labels,
     * removing the need use the very expensive setLabels method.
     *
     * @param string $subpartKey
     *        key of an existing subpart, for example 'LIST_ITEM' (without the ###),
     *        or an empty string to use the complete HTML template
     *
     * @return string the subpart content or an empty string if the subpart is hidden or the subpart name is missing
     */
    public function getSubpartWithLabels(string $subpartKey = ''): string
    {
        $renderedSubpart = $this->getSubpart($subpartKey);

        $translator = $this;
        return (string)\preg_replace_callback(
            self::LABEL_PATTERN,
            static function (array $matches) use ($translator): string {
                /** @var non-falsy-string $key */
                $key = \strtolower($matches[1]);
                return $translator->translate($key);
            },
            $renderedSubpart,
        );
    }

    /**
     * Writes all localized labels for the current template into their corresponding template markers.
     *
     * For this, the label markers in the template must be prefixed with
     * "LABEL_" (e.g., "###LABEL_FOO###"), and the corresponding localization
     * entry must have the same key, but lowercased and without the ###
     * (e.g., "label_foo").
     */
    public function setLabels(): void
    {
        $template = $this->getTemplate();
        foreach ($template->getLabelMarkerNames() as $label) {
            $template->setMarker($label, $this->translate($label));
        }
    }

    /**
     * Intvals all piVars that are supposed to be integers. These are the keys
     * showUid, pointer and mode and the keys provided in $additionalPiVars.
     *
     * If some piVars are not set or no piVars array is defined yet, this
     * function will set the not yet existing piVars to zero.
     *
     * @param array<array-key, string> $additionalPiVars keys for $this->piVars that will be ensured to exist
     *        as integers in `$this->piVars` as well
     */
    protected function ensureIntegerPiVars(array $additionalPiVars = []): void
    {
        foreach (\array_merge(['showUid', 'pointer', 'mode'], $additionalPiVars) as $key) {
            if (isset($this->piVars[$key])) {
                $this->piVars[$key] = (int)$this->piVars[$key];
            } else {
                $this->piVars[$key] = 0;
            }
        }
    }

    /**
     * Extracts a value within listView.
     *
     * @param non-empty-string $fieldName TypoScript setup field name to extract (within listView.)
     *
     * @return string the contents of that field within listView., may be empty
     */
    private function getListViewConfigurationValue(string $fieldName): string
    {
        // @phpstan-ignore-next-line We are explicitly testing for a contract violation here.
        if ($fieldName === '') {
            throw new \InvalidArgumentException('$fieldName must not be empty.', 1331489528);
        }

        return isset($this->conf['listView.'][$fieldName]) ? (string)$this->conf['listView.'][$fieldName] : '';
    }

    /**
     * Returns a string value within listView.
     *
     * @param non-empty-string $fieldName TypoScript setup field name to extract (within listView.)
     *
     * @return string the trimmed contents of that field within listView.
     *                or an empty string if the value was not set
     */
    public function getListViewConfValueString(string $fieldName): string
    {
        return \trim($this->getListViewConfigurationValue($fieldName));
    }

    /**
     * Returns an integer value within listView.
     *
     * @param non-empty-string $fieldName TypoScript setup field name to extract (within listView.)
     *
     * @return int the integer value of that field within listView, or zero if the value was not set
     */
    public function getListViewConfValueInteger(string $fieldName): int
    {
        return (int)$this->getListViewConfigurationValue($fieldName);
    }

    /**
     * Returns a boolean value within listView.
     *
     * @param non-empty-string $fieldName TypoScript setup field name to extract (within listView.)
     *
     * @return bool the boolean value of that field within listView., FALSE if no value was set
     */
    public function getListViewConfValueBoolean(string $fieldName): bool
    {
        return (bool)$this->getListViewConfigurationValue($fieldName);
    }

    /**
     * Makes this object serializable.
     *
     * @return list<non-empty-string>
     */
    public function __sleep(): array
    {
        $propertyNames = [];
        foreach ((new \ReflectionClass($this))->getProperties() as $property) {
            $propertyName = $property->getName();
            if ($propertyName === 'frontendController') {
                continue;
            }
            $propertyNames[] = $property->isPrivate() ? (static::class . ':' . $propertyName) : $propertyName;
        }

        return $propertyNames;
    }

    /**
     * Restores data that got lost during the serialization.
     */
    public function __wakeup(): void
    {
        $controller = $this->getFrontEndController();
        if ($controller instanceof TypoScriptFrontendController) {
            $this->frontendController = $controller;
        }
    }

    /**
     * Retrieves the localized string for the local language key $key.
     *
     * This function checks whether the FE or BE localization functions are
     * available and then uses the appropriate method.
     *
     * In $this->conf['salutation'], a suffix to the key may be set (which may
     * be either 'formal' or 'informal'). If a corresponding key exists, the
     * formal/informal localized string is used instead.
     * If the formal/informal key doesn't exist, this function just uses the
     * regular string.
     *
     * Example: key = 'greeting', suffix = 'informal'. If the key
     * 'greeting_informal' exists, that string is used.
     * If it doesn't exist, this functions tries to use the string with the key
     * 'greeting'.
     *
     * @param non-empty-string $key the local language key for which to return the value
     *
     * @return string the requested local language key, might be empty
     */
    public function translate(string $key): string
    {
        // @phpstan-ignore-next-line We are explicitly checking for a contract violation here.
        if ($key === '') {
            throw new \InvalidArgumentException('$key must not be empty.', 1331489025);
        }
        if (isset($this->translationCache[$key])) {
            return $this->translationCache[$key];
        }

        $this->pi_loadLL();
        if ($this->getFrontEndController() instanceof TypoScriptFrontendController) {
            $result = $this->translateInFrontEnd($key);
        } elseif ($this->getLanguageService() instanceof LanguageService) {
            $result = $this->translateInBackEnd($key);
        } else {
            $result = $key;
        }

        $this->translationCache[$key] = $result;

        return $result;
    }

    /**
     * Retrieves the localized string for the local language key $key, using the
     * BE localization methods.
     *
     * @param non-empty-string $key the local language key for which to return the value
     *
     * @return string the requested local language key, might be empty
     */
    private function translateInBackEnd(string $key): string
    {
        $languageService = $this->getLanguageService();

        if (!$languageService instanceof LanguageService) {
            throw new \RuntimeException('No initialized language service.', 1646321243);
        }

        return $languageService->getLL($key);
    }

    /**
     * Retrieves the localized string for the local language key $key, using the
     * FE localization methods.
     *
     * In $this->conf['salutation'], a suffix to the key may be set (which may
     * be either 'formal' or 'informal'). If a corresponding key exists, the
     * formal/informal localized string is used instead.
     * If the formal/informal key doesn't exist, this function just uses the
     * regular string.
     *
     * Example: key = 'greeting', suffix = 'informal'. If the key
     * 'greeting_informal' exists, that string is used.
     * If it doesn't exist, this functions tries to use the string with the key
     * 'greeting'.
     *
     * @param non-empty-string $key the local language key for which to return the value
     *
     * @return string the requested local language key, might be empty
     */
    private function translateInFrontEnd(string $key): string
    {
        $hasFoundATranslation = false;
        $result = '';

        $availableLanguages = $this->getAvailableLanguages();
        foreach ($this->getSuffixesToTry() as $suffix) {
            $completeKey = $key . $suffix;
            foreach ($availableLanguages as $language) {
                if (isset($this->LOCAL_LANG[$language][$completeKey])) {
                    $result = $this->pi_getLL($completeKey);
                    $hasFoundATranslation = true;
                    break 2;
                }
            }
        }

        if (!$hasFoundATranslation) {
            $result = $key;
        }

        return $result;
    }

    /**
     * Compiles a list of language keys for which localizations have been loaded.
     *
     * @return list<string> a list of language keys
     */
    private function getAvailableLanguages(): array
    {
        if ($this->availableLanguages === null) {
            $this->availableLanguages = [];

            if ($this->LLkey !== '') {
                $this->availableLanguages[] = $this->LLkey;
            }
            // The key for English is "default", not "en".
            $this->availableLanguages = \str_replace('en', 'default', $this->availableLanguages);
            // Remove duplicates in case the default language is the same as the fall-back language.
            $this->availableLanguages = \array_unique($this->availableLanguages);

            // Now check that we only keep languages for which we have translations.
            foreach ($this->availableLanguages as $index => $code) {
                if (!isset($this->LOCAL_LANG[$code])) {
                    unset($this->availableLanguages[$index]);
                }
            }
        }

        return $this->availableLanguages;
    }

    /**
     * Gets an ordered list of language label suffixes that should be tried to
     * get localizations in the preferred order of formality.
     *
     * @return list<'_formal'|'_informal'|''> ordered list of suffixes, will not be empty
     */
    private function getSuffixesToTry(): array
    {
        if ($this->suffixesToTry === null) {
            $this->suffixesToTry = [];

            if (isset($this->conf['salutation'])) {
                if ($this->conf['salutation'] === 'informal') {
                    $this->suffixesToTry[] = '_informal';
                }
                $this->suffixesToTry[] = '_formal';
            }
            $this->suffixesToTry[] = '';
        }

        return $this->suffixesToTry;
    }

    protected function getFrontEndController(): ?TypoScriptFrontendController
    {
        $controller = $GLOBALS['TSFE'] ?? null;

        return $controller instanceof TypoScriptFrontendController ? $controller : null;
    }

    /**
     * Returns $GLOBALS['LANG'].
     */
    protected function getLanguageService(): ?LanguageService
    {
        $languageService = $GLOBALS['LANG'] ?? null;

        return $languageService instanceof LanguageService ? $languageService : null;
    }

    /**
     * Returns the localized label of the LOCAL_LANG key, $key
     *
     * @param string $key The key from the LOCAL_LANG array for which to return the value.
     * @param string $alternativeLabel Alternative string to return IF no value is found set for the key,
     *        neither for the local language nor the default.
     */
    // phpcs:disable
    private function pi_getLL(string $key, string $alternativeLabel = ''): string
    {
        $word = null;
        if (\is_string($this->LOCAL_LANG[$this->LLkey][$key][0]['target'] ?? null)) {
            $word = $this->LOCAL_LANG[$this->LLkey][$key][0]['target'];
        } elseif ($this->altLLkey !== '') {
            $alternativeLanguageKeys = GeneralUtility::trimExplode(',', $this->altLLkey, true);
            $alternativeLanguageKeys = \array_reverse($alternativeLanguageKeys);
            foreach ($alternativeLanguageKeys as $languageKey) {
                if (\is_string($this->LOCAL_LANG[$languageKey][$key][0]['target'] ?? null)) {
                    // Alternative language translation for key exists
                    $word = $this->LOCAL_LANG[$languageKey][$key][0]['target'];
                    break;
                }
            }
        }

        if (!\is_string($word)) {
            if (\is_string($this->LOCAL_LANG['default'][$key][0]['target'] ?? null)) {
                // Get default translation (without charset conversion, english)
                $word = $this->LOCAL_LANG['default'][$key][0]['target'];
            } else {
                $word = $alternativeLabel;
            }
        }

        return $word;
    }

    /**
     * Loads local-language values from the file passed as a parameter or
     * by looking for a "locallang" file in the
     * plugin class directory ($this->scriptRelPath).
     * Also locallang values set in the TypoScript property "_LOCAL_LANG" are
     * merged onto the values found in the "locallang" file.
     * Supported file extensions xlf
     */
    // phpcs:disable
    private function pi_loadLL(): void
    {
        if ($this->LOCAL_LANG_loaded) {
            return;
        }

        $languageFilePath = 'EXT:' . $this->extKey . '/'
            . PathUtility::dirname($this->scriptRelPath) . '/locallang.xlf';
        $languageFactory = GeneralUtility::makeInstance(LocalizationFactory::class);
        $this->LOCAL_LANG = $languageFactory->getParsedData($languageFilePath, $this->LLkey);
        $alternativeLanguageKeys = GeneralUtility::trimExplode(',', $this->altLLkey, true);
        foreach ($alternativeLanguageKeys as $languageKey) {
            $tempLL = $languageFactory->getParsedData($languageFilePath, $languageKey);
            if ($this->LLkey !== 'default' && isset($tempLL[$languageKey])) {
                $this->LOCAL_LANG[$languageKey] = $tempLL[$languageKey];
            }
        }

        // Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
        if (isset($this->conf['_LOCAL_LANG.'])) {
            foreach ($this->conf['_LOCAL_LANG.'] as $languageKey => $languageArray) {
                // Remove the dot after the language key
                $languageKey = \substr($languageKey, 0, -1);
                // Don't process label if the language is not loaded
                if (\is_array($languageArray) && isset($this->LOCAL_LANG[$languageKey])) {
                    foreach ($languageArray as $labelKey => $labelValue) {
                        if (!\is_array($labelValue)) {
                            $this->LOCAL_LANG[$languageKey][$labelKey][0]['target'] = $labelValue;
                        }
                    }
                }
            }
        }

        $this->LOCAL_LANG_loaded = true;
    }

    /**
     * Return value from somewhere inside a FlexForm structure
     *
     * @param array $T3FlexForm_array FlexForm data
     * @param string $fieldName Field name to extract. Can be given like "test/el/2/test/el/field_templateObject" where
     *     each part will dig a level deeper in the FlexForm data.
     * @param string $sheet Sheet pointer, eg. "sDEF"
     */
    // phpcs:disable
    private function pi_getFFvalue(array $T3FlexForm_array, string $fieldName, string $sheet = 'sDEF'): ?string
    {
        $sheetArray = $T3FlexForm_array['data'][$sheet]['lDEF'] ?? null;
        if (\is_array($sheetArray)) {
            return $this->pi_getFFvalueFromSheetArray($sheetArray, \explode('/', $fieldName), 'vDEF');
        }

        return null;
    }

    /**
     * Returns part of $sheetArray pointed to by the keys in $fieldNameArray
     *
     * @param array $sheetArray Multidimensional array, typically FlexForm contents
     * @param array $fieldNameArr Array where each value points to a key in the FlexForms content - the input array
     *     will have the value returned pointed to by these keys. All integer keys will not take their integer
     *     counterparts, but rather traverse the current position in the array and return element number X (whether
     *     this is right behavior is not settled yet...)
     * @param string $value Value for outermost key, typ. "vDEF" depending on language.
     *
     * @internal
     */
    // phpcs:disable
    private function pi_getFFvalueFromSheetArray(array $sheetArray, array $fieldNameArr, string $value): string
    {
        $tempArr = $sheetArray;
        foreach ($fieldNameArr as $v) {
            if (MathUtility::canBeInterpretedAsInteger($v)) {
                $integerValue = (int)$v;
                if (\is_array($tempArr)) {
                    $c = 0;
                    foreach ($tempArr as $values) {
                        if ($c === $integerValue) {
                            $tempArr = $values;
                            break;
                        }
                        $c++;
                    }
                }
            } elseif (isset($tempArr[$v])) {
                $tempArr = $tempArr[$v];
            }
        }
        return $tempArr[$value] ?? '';
    }

    /**
     * Converts $this->cObj->data['pi_flexform'] from XML string to flexForm array.
     */
    // phpcs:disable
    protected function pi_initPIflexForm(): void
    {
        if (!$this->cObj instanceof ContentObjectRenderer) {
            throw new \RuntimeException('No cObj.', 1703017462);
        }

        // Converting flexform data into array
        $field = 'pi_flexform';
        $fieldData = $this->cObj->data[$field] ?? null;
        if (!\is_array($fieldData) && $fieldData) {
            $this->cObj->data[$field] = GeneralUtility::xml2array((string)$fieldData);
            if (!\is_array($this->cObj->data[$field])) {
                $this->cObj->data[$field] = [];
            }
        }
    }

    /**
     * Returns a class-name prefixed with $this->prefixId and with all underscores substituted to dashes (-)
     *
     * @param non-empty-string $class The class name (or the END of it since it will be prefixed by $this->prefixId.'-')
     *
     * @return non-empty-string The combined class name (with the correct prefix)
     */
    // phpcs:disable
    protected function pi_getClassName(string $class): string
    {
        return 'tx-seminars-pi1-' . $class;
    }

    /**
     * Link string to the current page.
     * Returns the $str wrapped in <a>-tags with a link to the CURRENT page, but with $urlParameters set as extra
     * parameters for the page.
     *
     * @param string $str The content string to wrap in <a> tags
     * @param array $urlParameters Array with URL parameters as key/value pairs. They will be "imploded" and added to
     *     the list of parameters defined in the plugins TypoScript property "parent.addParams".
     * @param bool $cache If $cache is set (0/1), the page is asked to be cached by a &cHash value (unless the current
     *     plugin using this class is a USER_INT). Otherwise the no_cache-parameter will be a part of the link.
     * @param int<0, max> $altPageId Alternative page ID for the link. (By default this function links to the SAME
     *     page!)
     *
     * @return string The input string wrapped in <a> tags
     */
    // phpcs:disable
    protected function pi_linkTP(
        string $str,
        array $urlParameters = [],
        bool $cache = false,
        int $altPageId = 0,
        bool $addNoFollow = false
    ): string {
        if (!$this->cObj instanceof ContentObjectRenderer) {
            throw new \RuntimeException('No cObj.', 1703017453);
        }

        $conf = [];
        if (!$cache) {
            $conf['no_cache'] = true;
        }
        $conf['parameter'] = $altPageId > 0 ? $altPageId : 'current';
        $conf['additionalParams'] = ($this->conf['parent.']['addParams'] ?? '')
            . HttpUtility::buildQueryString($urlParameters, '&', true);
        if ($addNoFollow) {
            $conf['ATagParams'] = 'rel="nofollow"';
        }

        return $this->cObj->typoLink($str, $conf);
    }

    /**
     * Returns a results browser. This means a bar of page numbers plus a "previous" and "next" link. For each entry in
     * the bar the piVars "pointer" will be pointing to the "result page" to show. Using $this->piVars['pointer'] as
     * pointer to the page to display. Using $this->internal['maxPages'] for the max number of pages to include in the
     * browse bar. Using $this->internal['res_count'] for count number Using $this->internal['results_at_a_time'] for
     * how many results to show
     *
     * @return string Output HTML-Table, wrapped in <div>-tags with a class attribute
     */
    // phpcs:disable
    protected function pi_list_browseresults(): string
    {
        if (!$this->cObj instanceof ContentObjectRenderer) {
            throw new \RuntimeException('No cObj.', 1703017658);
        }

        // Initializing variables:
        $pointer = (int)($this->piVars['pointer'] ?? 0);
        $count = $this->internal['res_count'];
        $results_at_a_time = MathUtility::forceIntegerInRange($this->internal['results_at_a_time'], 1, 1000);
        $totalPages = (int)\ceil($count / $results_at_a_time);
        $maxPages = MathUtility::forceIntegerInRange($this->internal['maxPages'], 1, 100);
        $pi_isOnlyFields = $this->pi_isOnlyFields();
        if ($count <= $results_at_a_time) {
            return '';
        }

        // Default values for "traditional" wrapping with a table.
        $wrapper = [];
        $wrapper['disabledLinkWrap'] = '<td class="nowrap"><p>|</p></td>';
        $wrapper['inactiveLinkWrap'] = '<td class="nowrap"><p>|</p></td>';
        $wrapper['activeLinkWrap'] = '<td' . $this->pi_classParam('browsebox-SCell') . ' class="nowrap"><p>|</p></td>';
        $wrapper['browseLinksWrap'] = '<table><tr>|</tr></table>';
        $wrapper['showResultsWrap'] = '<p>|</p>';
        $wrapper['browseBoxWrap'] = '
            <!--
                List browsing box:
            -->
            <div ' . $this->pi_classParam('browsebox') . '>
                |
            </div>';

        // Show page browser
        $firstPage = 0;
        $lastPage = MathUtility::forceIntegerInRange($totalPages, 1, $maxPages);
        $links = [];
        // Make browse-table/links:
        // Link to previous page
        if ($pointer > 0) {
            $label = $this->pi_getLL('pi_list_browseresults_prev', '< Previous');
            $links[] = $this->cObj->wrap(
                $this->pi_linkTP_keepPIvars(
                    \htmlspecialchars($label, ENT_QUOTES | ENT_HTML5),
                    ['pointer' => ($pointer - 1) > 0 ? ($pointer - 1) : ''],
                    $pi_isOnlyFields,
                ),
                $wrapper['inactiveLinkWrap'],
            );
        }
        // Links to pages
        for ($a = $firstPage; $a < $lastPage; $a++) {
            $label = $this->pi_getLL('pi_list_browseresults_page', 'Page');
            $pageText = \trim(\htmlspecialchars($label, ENT_QUOTES | ENT_HTML5) . ' ' . ($a + 1));
            // Current page
            if ($pointer === $a) {
                $links[] = $this->cObj->wrap(
                    $this->pi_linkTP_keepPIvars(
                        $pageText,
                        ['pointer' => $a > 0 ? $a : ''],
                        $pi_isOnlyFields,
                    ),
                    $wrapper['activeLinkWrap'],
                );
            } else {
                $links[] = $this->cObj->wrap(
                    $this->pi_linkTP_keepPIvars(
                        $pageText,
                        ['pointer' => $a > 0 ? $a : ''],
                        $pi_isOnlyFields,
                    ),
                    $wrapper['inactiveLinkWrap'],
                );
            }
        }
        if ($pointer < $totalPages - 1) {
            // Link to next page
            if ($pointer >= $totalPages - 1) {
                $label = $this->pi_getLL('pi_list_browseresults_next', 'Next >');
                $links[] = $this->cObj->wrap(
                    \htmlspecialchars($label, ENT_QUOTES | ENT_HTML5),
                    $wrapper['disabledLinkWrap'],
                );
            } else {
                $label = $this->pi_getLL('pi_list_browseresults_next', 'Next >');
                $links[] = $this->cObj->wrap(
                    $this->pi_linkTP_keepPIvars(
                        \htmlspecialchars($label, ENT_QUOTES | ENT_HTML5),
                        ['pointer' => $pointer + 1],
                        $pi_isOnlyFields,
                    ),
                    $wrapper['inactiveLinkWrap'],
                );
            }
        }
        $theLinks = $this->cObj->wrap(\implode(LF, $links), $wrapper['browseLinksWrap']);

        $pR1 = $pointer * $results_at_a_time + 1;
        $pR2 = $pointer * $results_at_a_time + $results_at_a_time;
        // Render the result count in the "traditional" way using sprintf
        $resultCountMsg = \sprintf(
            \str_replace(
                '###SPAN_BEGIN###',
                '<span' . $this->pi_classParam('browsebox-strong') . '>',
                $this->pi_getLL(
                    'pi_list_browseresults_displays',
                    'Displaying results ###SPAN_BEGIN###%s to %s</span> out of ###SPAN_BEGIN###%s</span>',
                ),
            ),
            $count > 0 ? $pR1 : 0,
            \min($count, $pR2),
            $count,
        );
        $resultCountMsg = $this->cObj->wrap($resultCountMsg, $wrapper['showResultsWrap']);

        return $this->cObj->wrap($resultCountMsg . $theLinks, $wrapper['browseBoxWrap']);
    }

    /**
     * Wraps the input string in a <div> tag with the class attribute set to the prefixId.
     * All content returned from your plugins should be returned through this function so all content from your plugin
     * is encapsulated in a <div>-tag nicely identifying the content of your plugin.
     *
     * @param string $str HTML content to wrap in the div-tags with the "main class" of the plugin
     *
     * @return non-empty-string HTML content wrapped, ready to return to the parent object.
     */
    // phpcs:disable
    protected function pi_wrapInBaseClass(string $str): string
    {
        $content = '<div class="' . \str_replace('_', '-', $this->prefixId) . '">
            ' . $str . '
                </div>
            ';
        if (!($this->frontendController->config['config']['disablePrefixComment'] ?? false)) {
            $content = '


            <!--

                BEGIN: Content of extension "' . $this->extKey . '", plugin "' . $this->prefixId . '"

            -->
            ' . $content . '
                <!-- END: Content of extension "' . $this->extKey . '", plugin "' . $this->prefixId . '" -->

            ';
        }

        return $content;
    }

    /**
     * Returns TRUE if the piVars array has ONLY those fields entered that is set in the $fList (commalist) AND if none
     * of those fields value is greater than $lowerThan field if they are integers. Notice that this function will only
     * work as long as values are integers.
     */
    // phpcs:disable
    private function pi_isOnlyFields(): bool
    {
        $explodedList = ['mode', 'pointer'];
        $tempPiVars = $this->piVars;
        foreach ($explodedList as $k) {
            if (isset($tempPiVars[$k])
                && (!MathUtility::canBeInterpretedAsInteger($tempPiVars[$k])
                    || $tempPiVars[$k] < 5)
            ) {
                unset($tempPiVars[$k]);
            }
        }

        return $tempPiVars === [];
    }

    /**
     * Returns the class-attribute with the correctly prefixed classname
     * Using pi_getClassName()
     *
     * @param non-empty-string $class The class name (suffix)
     *
     * @return non-empty-string A "class" attribute with value and a single space char before it.
     */
    // phpcs:disable
    private function pi_classParam(string $class): string
    {
        return ' class="' . $this->pi_getClassName($class) . '"';
    }

    /**
     * Link a string to the current page while keeping currently set values in piVars.
     * Like pi_linkTP, but $urlParameters is by default set to $this->piVars with $overrulePIvars overlaid.
     * This means any current entries from this->piVars are passed on (except the key "DATA" which will be unset
     * before!) and entries in $overrulePIvars will OVERRULE the current in the link.
     *
     * @param string $str The content string to wrap in <a> tags
     * @param array $overrulePIvars Array of values to override in the current piVars. Contrary to pi_linkTP the keys
     *     in this array must correspond to the real piVars array and therefore NOT be prefixed with the
     *     $this->prefixId string. Further, if a value is a blank string it means the piVar key will not be a part of
     *     the link (unset)
     * @param bool $cache If $cache is set, the page is asked to be cached by a &cHash value (unless the current plugin
     *     using this class is a USER_INT). Otherwise the no_cache-parameter will be a part of the link.
     *
     * @return string The input string wrapped in <a> tags
     */
    // phpcs:disable
    protected function pi_linkTP_keepPIvars(
        string $str,
        array $overrulePIvars = [],
        bool $cache = false,
        bool $addNoFollow = false
    ): string {
        $piVars = $this->piVars;
        unset($piVars['DATA']);
        ArrayUtility::mergeRecursiveWithOverrule($piVars, $overrulePIvars);
        $overrulePIvars = $piVars;

        return $this->pi_linkTP($str, [$this->prefixId => $overrulePIvars], $cache, 0, $addNoFollow);
    }
}
