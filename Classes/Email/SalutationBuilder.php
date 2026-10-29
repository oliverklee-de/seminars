<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Email;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Seminars\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class creates a salutation for emails.
 */
class SalutationBuilder
{
    private ConfigurationRegistry $configurationRegistry;

    public function __construct(ConfigurationRegistry $configurationRegistry)
    {
        $this->configurationRegistry = $configurationRegistry;
    }

    /**
     * Creates the salutation for the given user.
     *
     * The salutation is localized and contains the name of the user.
     *
     * @return non-empty-string the localized salutation with a trailing comma
     */
    public function getSalutation(FrontendUser $user): string
    {
        $salutationParts = [];

        $salutationMode = $this->configurationRegistry->getByNamespace('plugin.tx_seminars')->getAsString('salutation');
        switch ($salutationMode) {
            case 'informal':
                $label = LocalizationUtility::translate('email_hello_informal', 'seminars');
                \assert(\is_string($label));
                \assert($label !== '');
                $salutationParts['dear'] = $label;
                $firstName = $user->getFirstName();
                $salutationParts['name'] = ($firstName !== '') ? $firstName : $user->getName();
                break;
            default:
                $label = LocalizationUtility::translate('email_hello_formal_99', 'seminars');
                \assert(\is_string($label));
                \assert($label !== '');
                $salutationParts['dear'] = $label;
                $salutationParts['name'] = $user->getName();
        }

        return \implode(' ', $salutationParts) . ',';
    }
}
