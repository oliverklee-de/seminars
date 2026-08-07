<?php

use OliverKlee\Seminars\Controller\BackEnd\EmailController;
use OliverKlee\Seminars\Controller\BackEnd\EventController;
use OliverKlee\Seminars\Controller\BackEnd\ModuleController;
use OliverKlee\Seminars\Controller\BackEnd\RegistrationController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die('Access denied.');

$boot = static function (): void {
    if ((new Typo3Version())->getMajorVersion() < 12) {
        ExtensionUtility::registerModule(
            'Seminars',
            'web',
            'events',
            '',
            [
                ModuleController::class => 'overview',
                EventController::class => 'hide, unhide, delete, search, duplicate',
                RegistrationController::class => 'showForEvent, exportCsvForEvent, exportCsvForPageUid, delete',
                EmailController::class => 'compose, send',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:seminars/Resources/Public/Icons/BackEndModule.svg',
                'labels' => 'LLL:EXT:seminars/Resources/Private/Language/locallang.xlf',
            ],
        );
    }
};

$boot();
unset($boot);
