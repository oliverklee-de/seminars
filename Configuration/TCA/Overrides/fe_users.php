<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns(
    'fe_users',
    [
        // not visible in the BE
        'tx_seminars_registration' => [
            'exclude' => true,
            'label' => 'registration (not visible in the BE)',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_seminars_event_types',
                'default' => 0,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'default_organizer' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:fe_users.default_organizer',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_seminars_organizers',
                'default' => 0,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'available_topics' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:fe_users.available_topics',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_seminars',
                'foreign_table_where' => 'AND tx_seminars_seminars.object_type = 1 ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
    ],
);

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:fe_users.divLabel.seminars, '
    . 'default_organizer, available_topics',
    '',
    'after:image',
);
