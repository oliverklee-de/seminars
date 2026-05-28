<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\FallbackConfiguration;
use OliverKlee\Oelib\Configuration\FlexformsConfiguration;
use OliverKlee\Oelib\Interfaces\Configuration;
use OliverKlee\Seminars\Templating\TemplateHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * This class represents a basic view.
 *
 * @internal
 */
abstract class AbstractView extends TemplateHelper
{
    /**
     * @var 'seminar_list'|'my_events'|'my_vip_events'|'list_registrations'
     */
    protected string $whatToDisplay;

    private ?Configuration $configuration = null;

    /**
     * The constructor. Initializes the TypoScript configuration, initializes
     * the flex forms, gets the template HTML code, sets the localized labels
     * and set the CSS classes from TypoScript.
     *
     * @param ContentObjectRenderer $contentObjectRenderer the parent cObj content, needed for the flexforms
     */
    public function __construct(array $configuration, ContentObjectRenderer $contentObjectRenderer)
    {
        parent::__construct(null, $GLOBALS['TSFE'] ?? null);

        $this->cObj = $contentObjectRenderer;
        $this->init($configuration);
        $this->pi_initPIflexForm();

        $this->getTemplateCode();
        $this->setLabels();
    }

    /**
     * Renders the view and returns its content.
     *
     * @return string the view's content
     */
    abstract public function render(): string;

    protected function getConfigurationWithFlexForms(): Configuration
    {
        if ($this->configuration instanceof Configuration) {
            return $this->configuration;
        }

        $typoScriptConfiguration = GeneralUtility::makeInstance(ConfigurationRegistry::class)
            ->getByNamespace('plugin.tx_seminars_pi1');
        if (!$this->cObj instanceof ContentObjectRenderer) {
            $this->configuration = $typoScriptConfiguration;
            return $this->configuration;
        }

        $flexFormsConfiguration = new FlexformsConfiguration($this->cObj);
        $this->configuration = new FallbackConfiguration($flexFormsConfiguration, $typoScriptConfiguration);

        return $this->configuration;
    }
}
