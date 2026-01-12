<?php

use OliverKlee\Seminars\Domain\Model\Event\EventDateInterface;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Seo\SlugGenerator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addToInsertRecords('tx_seminars_seminars');

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars',
        'label' => 'title',
        'type' => 'object_type',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY begin_date DESC',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/EventComplete.gif',
        'typeicon_column' => 'object_type',
        'typeicon_classes' => [
            'default' => 'tx-seminars-event-complete',
            EventInterface::TYPE_SINGLE_EVENT => 'tx-seminars-event-complete',
            EventInterface::TYPE_EVENT_TOPIC => 'tx-seminars-event-topic',
            EventInterface::TYPE_EVENT_DATE => 'tx-seminars-event-date',
        ],
        'hideAtCopy' => true,
        'searchFields' => 'title,accreditation_number',
    ],
    'columns' => [
        'object_type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.object_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => EventInterface::TYPE_SINGLE_EVENT,
                'items' => [
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.object_type.I.0',
                        EventInterface::TYPE_SINGLE_EVENT,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.object_type.I.1',
                        EventInterface::TYPE_EVENT_TOPIC,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.object_type.I.2',
                        EventInterface::TYPE_EVENT_DATE,
                    ],
                ],
            ],
        ],
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'required,trim',
            ],
        ],
        'uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.uid',
            'config' => [
                'type' => 'none',
                'readOnly' => 1,
            ],
        ],
        'topic' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.topic',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_seminars_seminars',
                'foreign_table_where' => 'AND tx_seminars_seminars.object_type = 1 ORDER BY title',
                'default' => 0,
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'slug' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.slug',
            'config' => [
                'type' => 'slug',
                'size' => 50,
                'max' => 262,
                'generatorOptions' => [
                    'fields' => ['title', 'object_type', 'topic'],
                    'fieldSeparator' => '-',
                    'postModifiers' => [
                        SlugGenerator::class . '->generateSlug',
                    ],
                ],
                'appearance' => [
                    'prefix' => SlugGenerator::class . '->getPrefix',
                ],
                'fallbackCharacter' => '-',
                'eval' => 'unique',
                'default' => '',
            ],
        ],
        'subtitle' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.subtitle',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'categories' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.categories',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_categories',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_categories_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'requirements' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.requirements',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_seminars',
                'foreign_table_where' => 'AND tx_seminars_seminars.uid <> ###THIS_UID### AND object_type = 1 ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_requirements_mm',
            ],
        ],
        'dependencies' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.dependencies',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_seminars',
                'foreign_table_where' => 'AND tx_seminars_seminars.uid <> ###THIS_UID### AND object_type = 1 ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_requirements_mm',
                'MM_opposite_field' => 'requirements',
            ],
        ],
        'teaser' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.teaser',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'description' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'event_type' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.event_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_seminars_event_types',
                'foreign_table_where' => ' ORDER BY title',
                'default' => 0,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'items' => [['', '0']],
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'accreditation_number' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.accreditation_number',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'trim',
            ],
        ],
        'credit_points' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.credit_points',
            'config' => [
                'type' => 'input',
                'size' => 3,
                'max' => 3,
                'eval' => 'int',
                'range' => [
                    'upper' => 999,
                    'lower' => 0,
                ],
                'default' => 0,
            ],
        ],
        'event_format' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.event_format',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => EventDateInterface::EVENT_FORMAT_ON_SITE,
                'items' => [
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.event_format.on-site',
                        EventDateInterface::EVENT_FORMAT_ON_SITE,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.event_format.hybrid',
                        EventDateInterface::EVENT_FORMAT_HYBRID,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.event_format.online',
                        EventDateInterface::EVENT_FORMAT_ONLINE,
                    ],
                ],
            ],
        ],
        'begin_date' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.begin_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'end_date' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.end_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'timeslots' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.timeslots',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_seminars_timeslots',
                'foreign_field' => 'seminar',
                'foreign_default_sortby' => 'tx_seminars_timeslots.begin_date',
                'maxitems' => 999,
                'appearance' => [
                    'levelLinksPosition' => 'bottom',
                    'expandSingle' => 1,
                ],
            ],
        ],
        'begin_date_registration' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.begin_date_registration',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'deadline_registration' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.deadline_registration',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'deadline_early_bird' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.deadline_early_bird',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'deadline_unregistration' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.deadline_unregistration',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'expiry' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.expiry',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 10,
                'eval' => 'date, int',
                'default' => 0,
            ],
        ],
        'details_page' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.details_page',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 15,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'place' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.place',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_sites',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_place_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'room' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.room',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'webinar_url' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.webinar_url',
            'displayCond' => 'FIELD:event_format:IN:1,2',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 15,
                'max' => 255,
                'eval' => 'trim',
                'fieldControl' => [
                    'linkPopup' => [
                        'options' => [
                            'blindLinkOptions' => ' file, folder, mail, page, telephone',
                        ],
                    ],
                ],
            ],
        ],
        'lodgings' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.lodgings',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_lodgings',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_lodgings_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'foods' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.foods',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_foods',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_foods_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'speakers' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.speakers',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_speakers',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_speakers_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'partners' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.partners',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_speakers',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_speakers_mm_partners',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'tutors' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.tutors',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_speakers',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_speakers_mm_tutors',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'leaders' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.leaders',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_speakers',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_speakers_mm_leaders',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'price_regular' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.price_regular',
            'displayCond' => 'FIELD:price_on_request:REQ:false',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'eval' => 'double2',
                'range' => [
                    'upper' => '999999.99',
                    'lower' => '0.00',
                ],
                'default' => '0.00',
            ],
        ],
        'price_regular_early' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.price_regular_early',
            'displayCond' => [
                'AND' => [
                    'FIELD:needs_registration:REQ:true',
                    'FIELD:price_on_request:REQ:false',
                ],
            ],
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'eval' => 'double2',
                'range' => [
                    'upper' => '999999.99',
                    'lower' => '0.00',
                ],
                'default' => '0.00',
            ],
        ],
        'price_special' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.price_special',
            'displayCond' => 'FIELD:price_on_request:REQ:false',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'eval' => 'double2',
                'range' => [
                    'upper' => '999999.99',
                    'lower' => '0.00',
                ],
                'default' => '0.00',
            ],
        ],
        'price_special_early' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.price_special_early',
            'displayCond' => [
                'AND' => [
                    'FIELD:needs_registration:REQ:true',
                    'FIELD:price_on_request:REQ:false',
                ],
            ],
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'eval' => 'double2',
                'range' => [
                    'upper' => '999999.99',
                    'lower' => '0.00',
                ],
                'default' => '0.00',
            ],
        ],
        'additional_information' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.additional_information',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'checkboxes' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.checkboxes',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_checkboxes',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_checkboxes_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'uses_terms_2' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.uses_terms_2',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'payment_methods' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.payment_methods',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_payment_methods',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_payment_methods_mm',
            ],
        ],
        'organizers' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.organizers',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_organizers',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 5,
                'minitems' => 1,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_organizers_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'organizing_partners' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.organizing_partners',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_organizers',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_organizing_partners_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        'event_takes_place_reminder_sent' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.event_takes_place_reminder_sent',
            'config' => [
                'type' => 'check',
                'readOnly' => 1,
            ],
        ],
        'cancelation_deadline_reminder_sent' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.cancelation_deadline_reminder_sent',
            'config' => [
                'type' => 'check',
                'readOnly' => 1,
            ],
        ],
        'needs_registration' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.needs_registration',
            'onChange' => 'reload',
            'config' => [
                'type' => 'check',
                'default' => 1,
            ],
        ],
        'allows_multiple_registrations' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.allows_multiple_registrations',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'attendees_min' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.attendees_min',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'max' => 4,
                'eval' => 'int',
                'range' => [
                    'upper' => 9999,
                    'lower' => 0,
                ],
                'default' => 0,
            ],
        ],
        'attendees_max' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.attendees_max',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'max' => 4,
                'eval' => 'int',
                'range' => [
                    'upper' => 9999,
                    'lower' => 0,
                ],
                'default' => 0,
            ],
        ],
        'queue_size' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.queue_size',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'offline_attendees' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.offline_attendees',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'size' => 3,
                'max' => 3,
                'eval' => 'int',
                'range' => [
                    'upper' => 999,
                    'lower' => 0,
                ],
                'default' => 0,
            ],
        ],
        'additional_email_text' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.additional_email_text',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
                'max' => 2048,
                'eval' => 'trim',
            ],
        ],
        'organizers_notified_about_minimum_reached' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.organizers_notified_about_minimum_reached',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'mute_notification_emails' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.mute_notification_emails',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'target_groups' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.target_groups',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_target_groups',
                'foreign_table_where' => ' ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_target_groups_mm',
                'fieldControl' => [
                    'editPopup' => ['disabled' => false],
                    'addRecord' => ['disabled' => false],
                    'listModule' => ['disabled' => false],
                ],
            ],
        ],
        // @deprecated #1324 will be removed in seminars 6.0
        'registrations' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.registrations',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_seminars_attendances',
                'foreign_field' => 'seminar',
                'foreign_default_sortby' => 'tx_seminars_attendances.crdate',
                'maxitems' => 999,
                'appearance' => [
                    'levelLinksPosition' => 'bottom',
                    'expandSingle' => 1,
                ],
                'behavior' => [
                    'disableMovingChildrenWithParent' => true,
                ],
            ],
        ],
        'cancelled' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.cancelled',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.cancelled_planned',
                        EventInterface::STATUS_PLANNED,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.cancelled_canceled',
                        EventInterface::STATUS_CANCELED,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.cancelled_confirmed',
                        EventInterface::STATUS_CONFIRMED,
                    ],
                ],

            ],
        ],
        'automatic_confirmation_cancelation' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.automatic_confirmation_cancelation',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'notes' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.notes',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'attached_files' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.attached_files',
            'config' => ExtensionManagementUtility::getFileFieldTCAConfig(
                'attached_files',
                [
                    'maxitems' => 50,
                    'appearance' => [
                        'collapseAll' => true,
                        'expandSingle' => true,
                        'useSortable' => true,
                        'enabledControls' => [
                            'sort' => true,
                            'hide' => false,
                        ],
                        'fileUploadAllowed' => true,
                    ],
                ],
            ),
        ],
        'download_start_date' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.download_start_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'starttime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:LGL.starttime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 8,
                'eval' => 'date, int',
                'default' => 0,
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:LGL.endtime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 8,
                'eval' => 'date, int',
                'default' => 0,
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038),
                    'lower' => mktime(0, 0, 0, date('m') - 1, date('d'), date('Y')),
                ],
            ],
        ],
        'owner_feuser' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:owner_feuser',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'default' => 0,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'vips' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.vips',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_seminars_feusers_mm',
            ],
        ],
        'image' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.image',
            'config' => ExtensionManagementUtility::getFileFieldTCAConfig(
                'image',
                [
                    'maxitems' => 1,
                    'appearance' => [
                        'collapseAll' => true,
                        'expandSingle' => true,
                        'useSortable' => false,
                        'enabledControls' => [
                            'sort' => false,
                            'hide' => false,
                        ],
                        'fileUploadAllowed' => true,
                    ],
                ],
            ),
        ],
        'price_on_request' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.price_on_request',
            'onChange' => 'reload',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'date_of_last_registration_digest' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.date_of_last_registration_digest',
            'displayCond' => 'FIELD:needs_registration:REQ:true',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
        'billing_start' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.billing_start',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime, int',
                'default' => 0,
            ],
        ],
    ],
    'types' => [
        EventInterface::TYPE_SINGLE_EVENT => [
            'showitem' =>
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelGeneral, object_type, title, uid, slug, subtitle, image, categories, teaser, description, event_type, accreditation_number, credit_points, details_page, additional_information, checkboxes, uses_terms_2, cancelled, automatic_confirmation_cancelation, notes, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelPlaceTime, event_format, webinar_url, begin_date, end_date, timeslots, begin_date_registration, deadline_registration, deadline_early_bird, deadline_unregistration, place, room, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelSpeakers, speakers, partners, tutors, leaders, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelOrganizers, organizers, organizing_partners, event_takes_place_reminder_sent, cancelation_deadline_reminder_sent, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelAttendees, needs_registration, allows_multiple_registrations, attendees_min, attendees_max, queue_size, offline_attendees, organizers_notified_about_minimum_reached, mute_notification_emails, target_groups, date_of_last_registration_digest, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.tab.attendeeInformation, additional_email_text, attached_files, download_start_date, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelLodging, lodgings, foods, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelPayment, price_on_request, price_regular, price_regular_early, price_special, price_special_early, payment_methods, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelBilling, billing_start, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelAccess, hidden, starttime, endtime, owner_feuser, vips',
        ],
        EventInterface::TYPE_EVENT_TOPIC => [
            'showitem' =>
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelGeneral, object_type, title, uid, slug, subtitle, image, categories, requirements, dependencies, teaser, description, event_type, credit_points, additional_information, uses_terms_2, notes, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelAttendees, allows_multiple_registrations, target_groups, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.tab.attendeeInformation, attached_files, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelPayment, price_on_request, price_regular, price_regular_early, price_special, price_special_early, payment_methods, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelAccess, hidden, starttime, endtime',
        ],
        EventInterface::TYPE_EVENT_DATE => [
            'showitem' =>
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelGeneral, object_type, title, uid, topic, slug, accreditation_number, details_page, cancelled, automatic_confirmation_cancelation, checkboxes, notes, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelPlaceTime, event_format, webinar_url, begin_date, end_date, timeslots, begin_date_registration, deadline_registration, deadline_early_bird, deadline_unregistration, expiry, place, room, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelSpeakers, speakers, partners, tutors, leaders, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelOrganizers, organizers, organizing_partners, event_takes_place_reminder_sent, cancelation_deadline_reminder_sent, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelAttendees, needs_registration, attendees_min, attendees_max, queue_size, offline_attendees, organizers_notified_about_minimum_reached, mute_notification_emails, date_of_last_registration_digest, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.tab.attendeeInformation, additional_email_text, attached_files, download_start_date, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelLodging, lodgings, foods, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelBilling, billing_start, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_seminars.divLabelAccess, hidden, owner_feuser, vips',
        ],
    ],
];

return $tca;
