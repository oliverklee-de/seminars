<?php

use OliverKlee\Seminars\Controller\BackEnd\EmailController;
use OliverKlee\Seminars\Controller\BackEnd\EventController;
use OliverKlee\Seminars\Controller\BackEnd\ModuleController;
use OliverKlee\Seminars\Controller\BackEnd\RegistrationController;

return [
    'web_events' => [
        'parent' => 'web',
        'access' => 'user',
        'icon' => 'EXT:seminars/Resources/Public/Icons/BackEndModule.svg',
        'labels' => 'LLL:EXT:seminars/Resources/Private/Language/locallang.xlf',
        'extensionName' => 'Seminars',
        'controllerActions' => [
            ModuleController::class => ['overview'],
            EventController::class => ['hide', 'unhide', 'delete', 'search', 'duplicate'],
            RegistrationController::class => ['showForEvent', 'exportCsvForEvent', 'exportCsvForPageUid', 'delete'],
            EmailController::class => ['compose', 'send'],
        ],
    ],
];
