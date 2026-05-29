<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Domain\Model;

use OliverKlee\Seminars\Domain\Model\Event\TimeSlot;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Event\TimeSlot
 */
final class TimeSlotTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TimeSlot $subject;

    private ValidatorResolver $validatorResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validatorResolver = $this->get(ValidatorResolver::class);
        $this->subject = new TimeSlot();
    }

    /**
     * @test
     */
    public function roomWithMaximumLengthPassesValidation(): void
    {
        $this->subject->setRoom(str_repeat('p', 255));
        $validator = $this->validatorResolver->getBaseValidatorConjunction(TimeSlot::class);
        $result = $validator->validate($this->subject);
        self::assertFalse($result->forProperty('room')->hasErrors());
    }

    /**
     * @test
     */
    public function roomInputLongerThanMaximumLengthDoesNotPassValidation(): void
    {
        $this->subject->setRoom(str_repeat('p', 256));
        $validator = $this->validatorResolver->getBaseValidatorConjunction(TimeSlot::class);
        $result = $validator->validate($this->subject);
        self::assertTrue($result->forProperty('room')->hasErrors());
    }
}
