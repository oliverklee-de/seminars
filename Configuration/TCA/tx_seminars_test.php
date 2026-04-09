<?php

use TYPO3\CMS\Core\Information\Typo3Version;

$tca = [
    'ctrl' => [
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'hideTable' => true,
        'adminOnly' => true,
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'starttime' => [
            'exclude' => 1,
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
            ],
        ],
        'title' => [
            'exclude' => 0,
            'config' => [
                'type' => 'input',
                'required' => true,
            ],
        ],
    ],
];

if ((new Typo3Version())->getMajorVersion() < 12) {
    $legacyTca = [
        'columns' => [
            'starttime' => [
                'config' => [
                    'type' => 'input',
                    'renderType' => 'inputDateTime',
                    'eval' => 'date, int',
                ],
            ],
            'endtime' => [
                'config' => [
                    'type' => 'input',
                    'renderType' => 'inputDateTime',
                    'eval' => 'date, int',
                ],
            ],
            'title' => [
                'config' => [
                    'eval' => 'required',
                ],
            ],
        ],
    ];

    $tca = \array_replace_recursive($tca, $legacyTca);
}

return $tca;
