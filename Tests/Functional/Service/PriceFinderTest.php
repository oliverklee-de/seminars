<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Service;

use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Service\PriceFinder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\PriceFinder
 */
final class PriceFinderTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private PriceFinder $subject;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $now = $this->get(Context::class)->getPropertyFromAspect('date', 'full');
        self::assertInstanceOf(\DateTimeImmutable::class, $now);
        $this->now = $now;

        $this->subject = $this->get(PriceFinder::class);
    }

    /**
     * @test
     */
    public function isAvailableViaContainer(): void
    {
        self::assertInstanceOf(PriceFinder::class, $this->get(PriceFinder::class));
    }

    /**
     * @test
     */
    public function findApplicablePricesForFreeEventWithoutEarlyBirdDeadlineReturnsFreeStandardPrice(): void
    {
        $event = new SingleEvent();
        $event->setStandardPrice(0.0);

        $result = $this->subject->findApplicablePrices($event);

        $expectedPrice = new Price(0.0, 'price.standard', Price::PRICE_STANDARD);
        self::assertEquals([Price::PRICE_STANDARD => $expectedPrice], $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForFreeEventWithEarlyBirdDeadlineInThePastReturnsFreeStandardPrice(): void
    {
        $event = new SingleEvent();
        $event->setStandardPrice(0.0);
        $earlyBirdDeadline = $this->now->modify('-1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $result = $this->subject->findApplicablePrices($event);

        $expectedPrice = new Price(0.0, 'price.standard', Price::PRICE_STANDARD);
        self::assertEquals([Price::PRICE_STANDARD => $expectedPrice], $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForFreeEventWithEarlyBirdDeadlineInTheFutureReturnsFreeStandardPrice(): void
    {
        $event = new SingleEvent();
        $event->setStandardPrice(0.0);
        $earlyBirdDeadline = $this->now->modify('+1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $result = $this->subject->findApplicablePrices($event);

        $expectedPrice = new Price(0.0, 'price.standard', Price::PRICE_STANDARD);
        self::assertEquals([Price::PRICE_STANDARD => $expectedPrice], $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForFreeEventWithEarlyBirdAndDeadlineInTheFutureReturnsFreeStandardPrice(): void
    {
        $event = new SingleEvent();
        $event->setStandardPrice(0.0);
        $earlyBirdDeadline = $this->now->modify('+1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));
        $event->setEarlyBirdPrice(14.5);

        $result = $this->subject->findApplicablePrices($event);

        $expectedPrice = new Price(0.0, 'price.standard', Price::PRICE_STANDARD);
        self::assertEquals([Price::PRICE_STANDARD => $expectedPrice], $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForAllPricesAndNoEarlyBirdDeadlineReturnsNonEarlyBirdPrices(): void
    {
        $event = new SingleEvent();

        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $specialPriceAmount = 3.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setEarlyBirdPrice($earlyBirdPriceAmount);
        $event->setSpecialPrice($specialPriceAmount);
        $event->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_STANDARD => new Price($standardPriceAmount, 'price.standard', Price::PRICE_STANDARD),
            Price::PRICE_SPECIAL => new Price($specialPriceAmount, 'price.special', Price::PRICE_SPECIAL),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForAllPricesAndEarlyBirdDeadlineInThePastReturnsNonEarlyBirdPrices(): void
    {
        $event = new SingleEvent();
        $earlyBirdDeadline = $this->now->modify('-1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $specialPriceAmount = 3.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setEarlyBirdPrice($earlyBirdPriceAmount);
        $event->setSpecialPrice($specialPriceAmount);
        $event->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_STANDARD => new Price($standardPriceAmount, 'price.standard', Price::PRICE_STANDARD),
            Price::PRICE_SPECIAL => new Price($specialPriceAmount, 'price.special', Price::PRICE_SPECIAL),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForAllPricesAndEarlyBirdDeadlineNowReturnsNonEarlyBirdPrices(): void
    {
        $event = new SingleEvent();
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($this->now));

        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $specialPriceAmount = 3.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setEarlyBirdPrice($earlyBirdPriceAmount);
        $event->setSpecialPrice($specialPriceAmount);
        $event->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_STANDARD => new Price($standardPriceAmount, 'price.standard', Price::PRICE_STANDARD),
            Price::PRICE_SPECIAL => new Price($specialPriceAmount, 'price.special', Price::PRICE_SPECIAL),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesForAllPricesAndEarlyBirdDeadlineInTheFutureReturnsEarlyBirdPrices(): void
    {
        $event = new SingleEvent();
        $earlyBirdDeadline = $this->now->modify('+1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $specialPriceAmount = 3.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setEarlyBirdPrice($earlyBirdPriceAmount);
        $event->setSpecialPrice($specialPriceAmount);
        $event->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_EARLY_BIRD => new Price($earlyBirdPriceAmount, 'price.earlyBird', Price::PRICE_EARLY_BIRD),
            Price::PRICE_SPECIAL_EARLY_BIRD => new Price(
                $specialEarlyBirdPriceAmount,
                'price.specialEarlyBird',
                Price::PRICE_SPECIAL_EARLY_BIRD,
            ),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesWithEarlyBirdApplicableReplacesStandardPriceWithEarlyBirdVersion(): void
    {
        $event = new SingleEvent();
        $earlyBirdDeadline = $this->now->modify('+1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setEarlyBirdPrice($earlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_EARLY_BIRD => new Price($earlyBirdPriceAmount, 'price.earlyBird', Price::PRICE_EARLY_BIRD),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesWithEarlyBirdApplicableReplacesSpecialPriceWithEarlyBirdVersion(): void
    {
        $event = new SingleEvent();
        $earlyBirdDeadline = $this->now->modify('+1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $standardPriceAmount = 1.0;
        $specialPriceAmount = 3.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setSpecialPrice($specialPriceAmount);
        $event->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_STANDARD => new Price($standardPriceAmount, 'price.standard', Price::PRICE_STANDARD),
            Price::PRICE_SPECIAL_EARLY_BIRD => new Price(
                $specialEarlyBirdPriceAmount,
                'price.specialEarlyBird',
                Price::PRICE_SPECIAL_EARLY_BIRD,
            ),
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function findApplicablePricesWithEarlyBirdApplicableAndNoSpecialPriceKeepsEarlyBirdSpecialPrice(): void
    {
        $event = new SingleEvent();
        $earlyBirdDeadline = $this->now->modify('+1 day');
        $event->setEarlyBirdDeadline(\DateTime::createFromImmutable($earlyBirdDeadline));

        $standardPriceAmount = 1.0;
        $earlyBirdPriceAmount = 2.0;
        $specialEarlyBirdPriceAmount = 4.0;
        $event->setStandardPrice($standardPriceAmount);
        $event->setEarlyBirdPrice($earlyBirdPriceAmount);
        $event->setSpecialEarlyBirdPrice($specialEarlyBirdPriceAmount);

        $result = $this->subject->findApplicablePrices($event);

        $expected = [
            Price::PRICE_EARLY_BIRD => new Price($earlyBirdPriceAmount, 'price.earlyBird', Price::PRICE_EARLY_BIRD),
            Price::PRICE_SPECIAL_EARLY_BIRD => new Price(
                $specialEarlyBirdPriceAmount,
                'price.specialEarlyBird',
                Price::PRICE_SPECIAL_EARLY_BIRD,
            ),
        ];

        self::assertEquals($expected, $result);
    }
}
