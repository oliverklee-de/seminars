<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\ViewHelpers;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper
 */
final class DateRangeViewHelperTest extends FunctionalTestCase
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

    private DateRangeViewHelper $subject;

    private string $dateFormat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackEndUser.csv');
        $GLOBALS['LANG'] = $this
            ->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        // Make sure that the test results do not depend on the machine's PHP time zone.
        \date_default_timezone_set('UTC');

        $this->get(ConfigurationRegistry::class)->set('plugin.tx_seminars', new DummyConfiguration());

        $dateFormat = LocalizationUtility::translate('dateFormat', 'seminars');
        self::assertIsString($dateFormat);
        $this->dateFormat = $dateFormat;

        $this->subject = new DateRangeViewHelper();
    }

    /**
     * @test
     */
    public function renderWithNoDatesReturnsEmptyString(): void
    {
        $event = new SingleEvent();

        self::assertSame('', $this->subject->render($event));
    }

    /**
     * @test
     */
    public function renderWithBeginDateOnlyRendersOnlyBeginDate(): void
    {
        $event = new SingleEvent();
        $start = new \DateTime('2026-10-15 10:00:00');
        $event->setStart($start);

        self::assertSame(
            $start->format($this->dateFormat),
            $this->subject->render($event),
        );
    }

    /**
     * @test
     */
    public function renderWithEqualBeginAndEndDateReturnsOnlyBeginDate(): void
    {
        $event = new SingleEvent();
        $start = new \DateTime('2026-10-15 10:00:00');
        $event->setStart($start);
        $event->setEnd(new \DateTime('2026-10-15 18:00:00'));

        self::assertSame(
            $start->format($this->dateFormat),
            $this->subject->render($event),
        );
    }

    /**
     * @test
     */
    public function renderWithBeginAndEndDateOnSameDayReturnsOnlyBeginDate(): void
    {
        $event = new SingleEvent();
        $start = new \DateTime('2026-10-15 10:00:00');
        $event->setStart($start);
        $event->setEnd(new \DateTime('2026-10-15 18:00:00'));

        self::assertSame(
            $start->format($this->dateFormat),
            $this->subject->render($event),
        );
    }

    /**
     * @test
     */
    public function renderWithBeginAndEndDateOnDifferentDaysReturnsBothFullDatesSeparatedByEnDash(): void
    {
        $event = new SingleEvent();
        $start = new \DateTime('2026-10-15 10:00:00');
        $event->setStart($start);
        $end = new \DateTime('2026-10-16 18:00:00');
        $event->setEnd($end);

        self::assertSame(
            $start->format($this->dateFormat) . '–' . $end->format($this->dateFormat),
            $this->subject->render($event),
        );
    }
}
