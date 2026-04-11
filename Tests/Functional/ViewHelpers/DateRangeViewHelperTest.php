<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\ViewHelpers;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Model\AbstractTimeSpan;
use OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper
 */
final class DateRangeViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    /**
     * some random date (2001-01-01 00:00:00 UTC)
     */
    private const BEGIN_DATE = 978307200;

    private DateRangeViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        // Make sure that the test results do not depend on the machine's PHP time zone.
        \date_default_timezone_set('UTC');

        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', new DummyConfiguration());

        $this->subject = new DateRangeViewHelper();
    }

    /**
     * @test
     */
    public function renderWithNoDatesReturnsEmptyString(): void
    {
        $timeSpan = $this->getMockForAbstractClass(AbstractTimeSpan::class);
        $timeSpan->setData([]);

        self::assertSame('', $this->subject->render($timeSpan));
    }

    /**
     * @test
     */
    public function renderWithBeginDateOnlyRendersOnlyBeginDate(): void
    {
        $timeSpan = $this->getMockForAbstractClass(AbstractTimeSpan::class);
        $timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);

        self::assertSame(
            \date('Y-m-d', self::BEGIN_DATE),
            $this->subject->render($timeSpan),
        );
    }

    /**
     * @test
     */
    public function renderWithEqualBeginAndEndDateReturnsOnlyBeginDate(): void
    {
        $timeSpan = $this->getMockForAbstractClass(AbstractTimeSpan::class);
        $timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
        $timeSpan->setEndDateAsUnixTimeStamp(self::BEGIN_DATE);

        self::assertSame(
            \date('Y-m-d', self::BEGIN_DATE),
            $this->subject->render($timeSpan),
        );
    }

    /**
     * @test
     */
    public function renderWithBeginAndEndDateOnSameDayReturnsOnlyBeginDate(): void
    {
        $timeSpan = $this->getMockForAbstractClass(AbstractTimeSpan::class);
        $timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
        $timeSpan->setEndDateAsUnixTimeStamp(self::BEGIN_DATE + 3600);

        self::assertSame(
            \date('Y-m-d', self::BEGIN_DATE),
            $this->subject->render($timeSpan),
        );
    }

    /**
     * @test
     */
    public function renderWithBeginAndEndDateOnDifferentDaysReturnsBothFullDatesSeparatedByDash(): void
    {
        $timeSpan = $this->getMockForAbstractClass(AbstractTimeSpan::class);
        $timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
        $endDate = self::BEGIN_DATE + (2 * 86400);
        $timeSpan->setEndDateAsUnixTimeStamp($endDate);

        self::assertSame(
            \date('Y-m-d', self::BEGIN_DATE) . '&#8211;' . \date('Y-m-d', $endDate),
            $this->subject->render($timeSpan),
        );
    }

    /**
     * @test
     */
    public function renderWithBeginAndEndDateOnDifferentDaysReturnsBothFullDatesSeparatedBySpecifiedDash(): void
    {
        $dash = '#DASH#';

        $timeSpan = $this->getMockForAbstractClass(AbstractTimeSpan::class);
        $timeSpan->setBeginDateAsUnixTimeStamp(self::BEGIN_DATE);
        $endDate = self::BEGIN_DATE + (2 * 86400);
        $timeSpan->setEndDateAsUnixTimeStamp($endDate);

        self::assertSame(
            \date('Y-m-d', self::BEGIN_DATE) . $dash . \date('Y-m-d', $endDate),
            $this->subject->render($timeSpan, $dash),
        );
    }
}
