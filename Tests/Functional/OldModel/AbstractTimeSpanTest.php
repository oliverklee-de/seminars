<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\OldModel;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Tests\Unit\OldModel\Fixtures\TestingTimeSpan;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\OldModel\AbstractModel
 * @covers \OliverKlee\Seminars\OldModel\AbstractTimeSpan
 */
final class AbstractTimeSpanTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingTimeSpan $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TestingTimeSpan();
        $this->get(ConfigurationRegistry::class)->set('plugin.tx_seminars', new DummyConfiguration());

        if ((new Typo3Version())->getMajorVersion() <= 11) {
            $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
            $GLOBALS['LANG'] = $this
                ->get(LanguageServiceFactory::class)
                ->createFromUserPreferences($this->setUpBackendUser(1));
        }
    }

    // Test for getting the time.

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnSameDayReturnsBothTimesWithEnDashByDefault(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 1, 2010));

        $hoursLabel = LocalizationUtility::translate('label_hours', 'seminars');
        self::assertIsString($hoursLabel);
        self::assertSame('09:50–18:30 ' . $hoursLabel, $this->subject->getTime());
    }

    /**
     * @test
     */
    public function getTimeForBeginTimeAndEndTimeOnDifferentDaysReturnsBothTimesWithEnDashByDefault(): void
    {
        $this->subject->setBeginDateAndTime(\mktime(9, 50, 0, 1, 1, 2010));
        $this->subject->setEndDateAndTime(\mktime(18, 30, 0, 1, 2, 2010));

        $hoursLabel = LocalizationUtility::translate('label_hours', 'seminars');
        self::assertIsString($hoursLabel);
        self::assertSame('09:50–18:30 ' . $hoursLabel, $this->subject->getTime());
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
            '2010-01-01–2010-01-03',
            $this->subject->getDate(),
        );
    }
}
