<?php

use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;

defined('TYPO3') or die();

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY crdate DESC',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/Registration.svg',
        'searchFields' => 'title, order_reference, invoice_number, customer_number',
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'crdate' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.crdate',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 8,
                'eval' => 'datetime, int',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.uid',
            'config' => [
                'type' => 'none',
                'readOnly' => 1,
            ],
        ],
        'seminar' => [
            'exclude' => false,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.seminar',
            'config' => [
                'type' => 'group',
                'renderType' => 'eventDetails',
                'allowed' => 'tx_seminars_seminars',
                'default' => 0,
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'user' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.user',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'default' => 0,
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'been_there' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.been_there',
            'displayCond' => 'FIELD:registration_queue:=:' . Registration::STATUS_REGULAR,
            'config' => [
                'type' => 'check',
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
        'attendance_mode' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.attendance_mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => Registration::ATTENDANCE_MODE_NOT_SET,
                'items' => [
                    ['', Registration::ATTENDANCE_MODE_NOT_SET],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.attendance_mode.onSite',
                        Registration::ATTENDANCE_MODE_ON_SITE,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.attendance_mode.online',
                        Registration::ATTENDANCE_MODE_ONLINE,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.attendance_mode.hybrid',
                        Registration::ATTENDANCE_MODE_HYBRID,
                    ],
                ],
            ],
        ],
        'registration_queue' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.registration_queue',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => Registration::STATUS_REGULAR,
                'items' => [
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.registration_queue.regular',
                        Registration::STATUS_REGULAR,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.registration_queue.waitingList',
                        Registration::STATUS_WAITING_LIST,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.registration_queue.nonbindingReservation',
                        Registration::STATUS_NONBINDING_RESERVATION,
                    ],
                ],
            ],
        ],
        'seats' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.seats',
            'config' => [
                'type' => 'input',
                'size' => 3,
                'max' => 3,
                'eval' => 'int',
                'range' => [
                    'upper' => 999,
                    'lower' => 0,
                ],
                'default' => 1,
            ],
        ],
        'registered_themselves' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.registered_themselves',
            'config' => [
                'type' => 'check',
                'default' => 1,
            ],
        ],
        'price' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.price',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'price_code' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.price_code',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', ''],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.price_code.standard',
                        Price::PRICE_STANDARD,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.price_code.special',
                        Price::PRICE_SPECIAL,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.price_code.earlyBird',
                        Price::PRICE_EARLY_BIRD,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.price_code.specialEarlyBird',
                        Price::PRICE_SPECIAL_EARLY_BIRD,
                    ],
                ],
            ],
        ],
        'total_price' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.total_price',
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
        'attendees_names' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.attendees_names',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'additional_persons' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.additional_persons',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'fe_users',
                'foreign_field' => 'tx_seminars_registration',
                'foreign_default_sortby' => 'name',
                'maxitems' => 999,
                'appearance' => [
                    'levelLinksPosition' => 'bottom',
                    'expandSingle' => 1,
                ],
            ],
        ],
        'kids' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.kids',
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
        'foods' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.foods',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_foods',
                'foreign_table_where' => 'ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_attendances_foods_mm',
            ],
        ],
        'food' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.food',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'lodgings' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.lodgings',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_lodgings',
                'foreign_table_where' => 'ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_attendances_lodgings_mm',
            ],
        ],
        'accommodation' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.accommodation',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'checkboxes' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.checkboxes',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_seminars_checkboxes',
                'foreign_table_where' => 'ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_attendances_checkboxes_mm',
            ],
        ],
        'order_reference' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.order_reference',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 50,
                'eval' => 'trim',
            ],
        ],
        'interests' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.interests',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'expectations' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.expectations',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'background_knowledge' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.background_knowledge',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'known_from' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.known_from',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'notes' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.notes',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
        'invoice_date' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.invoice_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime, int',
                'size' => 12,
                'default' => 0,
            ],
        ],
        'datepaid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.datepaid',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 8,
                'eval' => 'date, int',
                'default' => 0,
            ],
        ],
        'invoice_number' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.invoice_number',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'eval' => 'num, null',
                'default' => null,
            ],
        ],
        'customer_number' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.customer_number',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'eval' => 'num, null',
                'default' => null,
            ],
        ],
        'method_of_payment' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.method_of_payment',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_seminars_payment_methods',
                'foreign_table_where' => 'ORDER BY title',
                'default' => 0,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'items' => [['', '0']],
            ],
        ],
        'company' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.company',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 80,
                'eval' => 'trim',
            ],
        ],
        'gender' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.gender',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.gender.I.0',
                        0,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.gender.I.1',
                        1,
                    ],
                    [
                        'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.gender.I.2',
                        2,
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 80,
                'eval' => 'trim',
            ],
        ],
        'address' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.address',
            'config' => [
                'type' => 'text',
                'cols' => 20,
                'rows' => 3,
                'max' => 40,
                'eval' => 'trim',
            ],
        ],
        'zip' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.zip',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'max' => 10,
                'eval' => 'trim',
            ],
        ],
        'city' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.city',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 40,
                'eval' => 'trim',
            ],
        ],
        'country' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.country',
            'config' => [
                'type' => 'input',
                'size' => 16,
                'max' => 40,
                'eval' => 'trim',
            ],
        ],
        'telephone' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.telephone',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 40,
                'eval' => 'trim',
            ],
        ],
        'email' => [
            'exclude' => true,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.email',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 50,
                'eval' => 'email, trim',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.divLabelOverview, title, crdate, uid, seminar, user, hidden, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.divLabelBookingInformation, registration_queue, attendance_mode, registered_themselves, seats, price, price_code, total_price, attendees_names, additional_persons, kids, foods, food, lodgings, accommodation, checkboxes, order_reference, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.divLabelRegistrationComments, interests, expectations, background_knowledge, known_from, notes, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.divLabelCertificateOfAttendance, been_there, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.divLabelPaymentInformation, customer_number, invoice_number, invoice_date, datepaid, method_of_payment, ' .
                '--div--;LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_attendances.divLabelBillingAddress, company, gender, name, address, zip, city, country, telephone, email',
        ],
    ],
];

return $tca;
