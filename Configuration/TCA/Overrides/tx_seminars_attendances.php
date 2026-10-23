<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['seminars']['record_user_agent_on_registration'] == true) {
    ExtensionManagementUtility::addToAllTCAtypes(
        'tx_seminars_attendances',
        'user_agent',
        '',
        'after:order_reference'
    );
}
