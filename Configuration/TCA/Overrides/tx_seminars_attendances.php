<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
    ->get('seminars');

if (($extensionConfiguration['record_user_agent_on_registration'] ?? null) === '1') {
    ExtensionManagementUtility::addToAllTCAtypes(
        'tx_seminars_attendances',
        'user_agent',
        '',
        'after:order_reference'
    );
}
