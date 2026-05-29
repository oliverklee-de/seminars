<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Event\TimeSlot;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Event\TimeSlot
 */
final class TimeSlotTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TimeSlot $subject;

    private ConjunctionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/AdminBackendUser.csv');
        $GLOBALS['LANG'] = $this
            ->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($this->setUpBackendUser(1));

        $this->validator = $this->get(ValidatorResolver::class)->getBaseValidatorConjunction(TimeSlot::class);

        $this->subject = new TimeSlot();
    }

    /**
     * @test
     */
    public function roomWithMaximumLengthPassesValidation(): void
    {
        $this->subject->setRoom(str_repeat('p', 255));

        $result = $this->validator->validate($this->subject);

        self::assertFalse($result->forProperty('room')->hasErrors());
    }

    /**
     * @test
     */
    public function roomInputLongerThanMaximumLengthDoesNotPassValidation(): void
    {
        $this->subject->setRoom(str_repeat('p', 256));

        $result = $this->validator->validate($this->subject);

        self::assertTrue($result->forProperty('room')->hasErrors());
    }
}
