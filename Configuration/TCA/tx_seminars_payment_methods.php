<?php

use TYPO3\CMS\Core\Information\Typo3Version;

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_payment_methods',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/PaymentMethod.svg',
        'searchFields' => 'title',
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_payment_methods.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_payment_methods.description',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 10,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title, description'],
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
        ],
    ];

    $tca = \array_replace_recursive($tca, $legacyTca);
}

return $tca;
