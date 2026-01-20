<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Hooks\Interfaces\DateTimeSpan;
use OliverKlee\Seminars\Tests\Support\LanguageHelper;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingTimeSpan;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 */
final class AbstractTimeSpanTest extends FunctionalTestCase
{
    use LanguageHelper;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingTimeSpan $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeBackEndLanguage();

        $this->subject = new TestingTimeSpan();
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', new DummyConfiguration());
    }

    protected function tearDown(): void
    {
        ConfigurationRegistry::purgeInstance();

        parent::tearDown();
    }

    // Test for getting the time.

    /**
     * @test
     */
    public function getTimeForNoTimeReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTime());
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeOnlyReturnsBeginTime(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));

        self::assertSame(
            '09:50' . ' ' . $this->translate('label_hours'),
            $this->subject->getTime(),
        );
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnSameDayReturnsBothTimesWithMDashByDefault(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 1, 2010));

        self::assertSame(
            '09:50&#8211;18:30' . ' ' . $this->translate('label_hours'),
            $this->subject->getTime(),
        );
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnSameDayReturnsBothTimesWithProvidedDash(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 1, 2010));

        self::assertSame(
            '09:50-18:30' . ' ' . $this->translate('label_hours'),
            $this->subject->getTime('-'),
        );
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnDifferentDaysReturnsBothTimesWithMDashByDefault(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 2, 2010));

        self::assertSame(
            '09:50&#8211;18:30' . ' ' . $this->translate('label_hours'),
            $this->subject->getTime(),
        );
    }

    /**
     * @test
     */
    public function getTimeForNoTimeNotCallsHook(): void
    {
        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getTime();
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeOnlyNotCallsHook(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getTime();
    }

    /**
     * @test
     */
    public function getTimeForEndTimeOnlyNotCallsHook(): void
    {
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 1, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getTime();
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnSameTimeNotCallsHook(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(9, 50, 0, 1, 3, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getTime();
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnDifferentTimesCallsHookAndUsesModifiedValue(): void
    {
        $modifiedValue = 'modified';

        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 1, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook
            ->expects(self::once())->method('modifyTimeSpan')
            ->with('09:50&#8211;18:30', $this->subject, '&#8211;')
            ->willReturn($modifiedValue);

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        self::assertSame(
            $modifiedValue . ' ' . $this->translate('label_hours'),
            $this->subject->getTime(),
        );
    }

    // Test for getting the date.

    /**
     * @test
     */
    public function getDateForNoDateReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getDate());
    }

    /**
     * @test
     */
    public function getDateForBeginDateReturnsBeginDate(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        self::assertSame(
            '2010-01-01',
            $this->subject->getDate(),
        );
    }

    /**
     * @test
     */
    public function getDateForEndDateOnlyWithoutBeginDateReturnsEmptyString(): void
    {
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        self::assertSame('', $this->subject->getDate());
    }

    /**
     * @test
     */
    public function getDateForBeginDateAndEndDateOnSameDayReturnsBeginDate(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        self::assertSame(
            '2010-01-01',
            $this->subject->getDate(),
        );
    }

    /**
     * @test
     */
    public function getDateForBeginDateAndEndDateOnDifferentDaysReturnsDateSpan(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 3, 2010));

        self::assertSame(
            '2010-01-01&#8211;2010-01-03',
            $this->subject->getDate(),
        );
    }

    /**
     * @test
     */
    public function getDateForNoDateNotCallsHook(): void
    {
        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getDate();
    }

    /**
     * @test
     */
    public function getDateForBeginDateNotCallsHook(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getDate();
    }

    /**
     * @test
     */
    public function getDateForEndDateNotCallsHook(): void
    {
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getDate();
    }

    /**
     * @test
     */
    public function getDateForBeginDateAndEndDateOnSameDayNotCallsHook(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook->expects(self::never())->method('modifyDateSpan');
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->subject->getDate();
    }

    /**
     * @test
     */
    public function getDateForBeginDateAndEndDateOnDifferentDaysCallsHookAndUsesModifiedValue(): void
    {
        $modifiedValue = 'modified';

        $this->subject->setBeginDateAndTime(\mktime(0, 0, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(0, 0, 0, 1, 3, 2010));

        $hook = $this->createMock(DateTimeSpan::class);
        $hook
            ->expects(self::once())->method('modifyDateSpan')
            ->with('2010-01-01&#8211;2010-01-03', $this->subject, '&#8211;')
            ->willReturn($modifiedValue);
        $hook->expects(self::never())->method('modifyTimeSpan');

        $hookClass = \get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars'][DateTimeSpan::class][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        self::assertSame($modifiedValue, $this->subject->getDate());
    }
}
