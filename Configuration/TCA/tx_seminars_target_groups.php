<?php

use TYPO3\CMS\Core\Information\Typo3Version;

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_target_groups',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY title',
        'delete' => 'deleted',
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/TargetGroup.svg',
        'searchFields' => 'title',
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_target_groups.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'minimum_age' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_target_groups.minimum_age',
            'config' => [
                'type' => 'input',
                'size' => 3,
                'eval' => 'int',
                'range' => [
                    'lower' => 0,
                    'upper' => 199,
                ],
            ],
        ],
        'maximum_age' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_target_groups.maximum_age',
            'config' => [
                'type' => 'input',
                'size' => 3,
                'eval' => 'int',
                'range' => [
                    'lower' => 0,
                    'upper' => 199,
                ],
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title, minimum_age, maximum_age'],
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
