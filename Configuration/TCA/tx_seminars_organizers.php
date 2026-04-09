<?php

use TYPO3\CMS\Core\Information\Typo3Version;

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_organizers',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/Organizer.svg',
        'searchFields' => 'title',
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_organizers.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_organizers.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'homepage' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_organizers.homepage',
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['page', 'url'],
                'size' => 15,
                'max' => 255,
            ],
        ],
        'email' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_organizers.email',
            'config' => [
                'type' => 'email',
                'size' => 30,
                'required' => true,
            ],
        ],
        'email_footer' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_organizers.email_footer',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title, description, homepage, email, email_footer'],
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
            'homepage' => [
                'config' => [
                    'type' => 'input',
                    'renderType' => 'inputLink',
                    'eval' => 'trim',
                ],
            ],
            'email' => [
                'config' => [
                    'type' => 'input',
                    'eval' => 'email, required, trim',
                ],
            ],
        ],
    ];

    $tca = \array_replace_recursive($tca, $legacyTca);
}

return $tca;
