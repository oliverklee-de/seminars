<?php

use TYPO3\CMS\Core\Information\Typo3Version;

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/Venue.svg',
        'searchFields' => 'title, contact_person, email_address',
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'address' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.address',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
                'eval' => 'trim',
            ],
        ],
        'city' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.city',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'homepage' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.homepage',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 15,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'directions' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.directions',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'cols' => 30,
                'rows' => 5,
                'eval' => 'trim',
            ],
        ],
        'contact_person' => [
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.contact_person',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'email_address' => [
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.email_address',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'email, trim',
            ],
        ],
        'phone_number' => [
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.phone_number',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'notes' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_sites.notes',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
                'eval' => 'trim',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'title, address, city, homepage, directions, '
                . 'contact_person, email_address, phone_number, notes',
        ],
    ],
];

if ((new Typo3Version())->getMajorVersion() < 12) {
    $legacyTca = [
        'columns' => [
            'title' => [
                'config' => [
                    'eval' => 'required,trim',
                ],
            ],
            'city' => [
                'config' => [
                    'eval' => 'required,trim',
                ],
            ],
        ],
    ];

    $tca = \array_replace_recursive($tca, $legacyTca);
}

return $tca;
