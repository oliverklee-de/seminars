<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Form\Element;

use OliverKlee\Seminars\Form\Element\EventDetailsElement;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Form\Element\GroupElement;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Form\Element\EventDetailsElement
 */
final class EventDetailsElementTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    private function getDateFormat(): string
    {
        return LocalizationUtility::translate('dateFormat', 'seminars') ?? '';
    }

    /**
     * @test
     */
    public function isAbstractFormElement(): void
    {
        $subject = new EventDetailsElement(new NodeFactory(), []);

        self::assertInstanceOf(AbstractFormElement::class, $subject);
    }

    /**
     * @test
     */
    public function isGroupElement(): void
    {
        $subject = new EventDetailsElement(new NodeFactory(), []);

        self::assertInstanceOf(GroupElement::class, $subject);
    }

    /**
     * @param array<string, mixed> $additionalData
     * @param non-empty-string $fieldName
     */
    private function renderWithData(array $additionalData = [], string $fieldName = 'seminar'): string
    {
        $fieldConfiguration = [
            'config' => [
                'type' => 'group',
                'renderType' => 'eventDetails',
                'allowed' => 'tx_seminars_seminars',
                'default' => 0,
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'clipboardElements' => [],
            ],
        ];
        $data = [
            'databaseRow' => [
                'uid' => 8,
                'title' => 'TCCD / Anna A. Attendee,    ',
                'seminar' => [
                    0 => [
                        'table' => 'tx_seminars_seminars',
                        'uid' => 5,
                        'title' => 'TCCD-Termin',
                        'row' => [
                            'uid' => 5,
                            'object_type' => 2,
                            'title' => 'TCCD-Termin',
                            'topic' => 1,
                            'begin_date' => 0,
                            'end_date' => 0,
                        ],
                    ],
                ],
            ],
            'elementBaseName' => '[tx_seminars_attendances][8][seminar]',
            'inlineStructure' => [],
            'renderType' => 'eventDetails',
            'tableName' => 'tx_seminars_attendances',
            'fieldName' => $fieldName,
            'parameterArray' => [
                'fieldConf' => $fieldConfiguration,
                'itemFormElName' => 'data[tx_seminars_attendances][8][seminar]',
                'itemFormElID' => 'data_tx_seminars_attendances_8_seminar',
                'itemFormElValue' => [],
            ],
            'processedTca' => ['columns' => [$fieldName => $fieldConfiguration]],
        ];
        ArrayUtility::mergeRecursiveWithOverrule($data, $additionalData);
        $subject = new EventDetailsElement(new NodeFactory(), $data);

        $subject->render();

        return $subject->render()['html'] ?? '';
    }

    /**
     * @test
     */
    public function renderForNonRegistrationTableThrowsException(): void
    {
        $otherTableName = 'pages';

        $this->expectException(\RuntimeException::class);
        $expectExceptionMessage = 'EventDetailsElement can only be used for the "tx_seminars_seminars" table, '
            . 'not for the "' . $otherTableName . '" table.';
        $this->expectExceptionMessage($expectExceptionMessage);
        $this->expectExceptionCode(1752769757);

        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'table' => $otherTableName,
                    ],
                ],
            ],
        ];
        $this->renderWithData($additionalData);
    }

    /**
     * @test
     */
    public function renderRendersHiddenFormItemWrapperOfOriginalGroupSelector(): void
    {
        $result = $this->renderWithData();

        self::assertStringContainsString('<div class="formengine-field-item', $result);
    }

    /**
     * @test
     */
    public function renderRendersHiddenInputOfOriginalGroupSelector(): void
    {
        $result = $this->renderWithData();

        self::assertStringContainsString(
            '<input type="hidden" name="data[tx_seminars_attendances][8][seminar]"',
            $result,
        );
    }

    /**
     * @test
     */
    public function renderWithOneEventRendersOuterWrapper(): void
    {
        $result = $this->renderWithData();

        self::assertStringContainsString('class="tx-seminars-event-details"', $result);
    }

    /**
     * @test
     */
    public function renderWithOneEventRendersInnerWrapperOnce(): void
    {
        $result = $this->renderWithData();

        $marker = 'class="tx-seminars-event-details-event"';
        self::assertSame(1, \substr_count($result, $marker));
    }

    /**
     * @test
     */
    public function renderForEventWithDateRendersDateOfEvent(): void
    {
        $timestamp = 1752771356;
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'begin_date' => $timestamp,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        $expectedDate = \date($this->getDateFormat(), $timestamp);
        self::assertStringContainsString($expectedDate, $result);
    }

    /**
     * @test
     */
    public function renderForEventWithoutDateDoesNotRenderZeroDate(): void
    {
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'begin_date' => 0,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        $expectedDate = \date($this->getDateFormat(), 0);
        self::assertStringNotContainsString($expectedDate, $result);
    }

    /**
     * @test
     */
    public function renderRendersUidOfEvent(): void
    {
        $uid = 145;
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'uid' => $uid,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        self::assertStringContainsString('[' . $uid . ']', $result);
    }

    /**
     * @test
     */
    public function renderRendersTitleOfEvent(): void
    {
        $title = 'Better unit testing with TYPO3';
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'title' => $title,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        self::assertStringContainsString($title, $result);
    }

    /**
     * @test
     */
    public function renderEncodesTitleOfEvent(): void
    {
        $title = 'Testing & quality assurance';
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'title' => $title,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        $expected = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function renderWithTwoEventsRendersOuterWrapper(): void
    {
        $title1 = 'Better unit testing with TYPO3';
        $title2 = 'And even Better unit testing with TYPO3';
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'title' => $title1,
                        ],
                    ],
                    1 => [
                        'table' => 'tx_seminars_seminars',
                        'row' => [
                            'uid' => 123,
                            'title' => $title2,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        self::assertStringContainsString('class="tx-seminars-event-details"', $result);
    }

    /**
     * @test
     */
    public function renderWithTwoEventsRendersInnerWrapperTwice(): void
    {
        $title1 = 'Better unit testing with TYPO3';
        $title2 = 'And even Better unit testing with TYPO3';
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'title' => $title1,
                        ],
                    ],
                    1 => [
                        'table' => 'tx_seminars_seminars',
                        'row' => [
                            'uid' => 123,
                            'title' => $title2,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        $marker = 'class="tx-seminars-event-details-event"';
        self::assertSame(2, \substr_count($result, $marker));
    }

    /**
     * @test
     */
    public function renderWithTwoEventsRendersTitleOfBothEvents(): void
    {
        $title1 = 'Better unit testing with TYPO3';
        $title2 = 'And even Better unit testing with TYPO3';
        $additionalData = [
            'databaseRow' => [
                'seminar' => [
                    0 => [
                        'row' => [
                            'title' => $title1,
                        ],
                    ],
                    1 => [
                        'table' => 'tx_seminars_seminars',
                        'row' => [
                            'uid' => 123,
                            'title' => $title2,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData);

        self::assertStringContainsString($title1, $result);
        self::assertStringContainsString($title2, $result);
    }

    /**
     * @test
     */
    public function renderCanRenderEventStoredWithDifferentKey(): void
    {
        $title = 'More cooking with fried rice';
        $fieldName = 'event';
        $additionalData = [
            'fieldName' => $fieldName,
            'databaseRow' => [
                $fieldName => [
                    0 => [
                        'table' => 'tx_seminars_seminars',
                        'row' => [
                            'uid' => 15,
                            'title' => $title,
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->renderWithData($additionalData, $fieldName);

        self::assertStringContainsString($title, $result);
    }

    /**
     * @test
     */
    public function renderWithoutEventDoesNotRenderOuterWrapper(): void
    {
        // We need to use a different field name as our helper function can only add data, but not remove it.
        $fieldName = 'event';
        $additionalData = [
            'fieldName' => $fieldName,
            'databaseRow' => [
                $fieldName => [],
            ],
        ];
        $result = $this->renderWithData($additionalData, $fieldName);

        self::assertStringNotContainsString('class="tx-seminars-event-details"', $result);
    }

    /**
     * @test
     */
    public function renderWithoutEventDoesNotRenderInnerWrapper(): void
    {
        // We need to use a different field name as our helper function can only add data, but not remove it.
        $fieldName = 'event';
        $additionalData = [
            'fieldName' => $fieldName,
            'databaseRow' => [
                $fieldName => [],
            ],
        ];
        $result = $this->renderWithData($additionalData, $fieldName);

        $marker = 'class="tx-seminars-event-details-event"';
        self::assertStringNotContainsString($marker, $result);
    }
}
