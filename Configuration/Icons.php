<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx-seminars-backend-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:seminars/Resources/Public/Icons/BackEndModule.svg',
    ],
];
