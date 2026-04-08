<?php

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
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'date, int',
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'date, int',
            ],
        ],
        'title' => [
            'exclude' => 0,
            'config' => [
                'type' => 'input',
                'eval' => 'required',
            ],
        ],
    ],
];

return $tca;
