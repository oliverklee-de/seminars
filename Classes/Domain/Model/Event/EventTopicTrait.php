<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Category;
use OliverKlee\Seminars\Domain\Model\EventType;
use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use OliverKlee\Seminars\Domain\Model\TargetGroup;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * This trait provides methods that are useful for `EventTopic`s, and usually also `SingleEvent`s.
 *
 * @phpstan-require-extends Event
 * @phpstan-require-implements EventTopicInterface
 */
trait EventTopicTrait
{
    /**
     * @Validate("StringLength", options={"maximum": 16383})
     */
    protected string $description = '';

    protected float $standardPrice = 0.0;

    protected float $earlyBirdPrice = 0.0;

    protected float $specialPrice = 0.0;

    protected float $specialEarlyBirdPrice = 0.0;

    /**
     * @var EventType|null
     * @phpstan-var EventType|LazyLoadingProxy|null
     * @Lazy
     */
    protected $eventType;

    protected bool $additionalTerms = false;

    protected bool $multipleRegistrationPossible = false;

    /**
     * @var ObjectStorage<PaymentMethod>
     * @Lazy
     */
    protected ObjectStorage $paymentMethods;

    /**
     * @var ObjectStorage<Category>
     * @Lazy
     */
    protected ObjectStorage $categories;

    /**
     * @var ObjectStorage<TargetGroup>
     * @Lazy
     */
    protected ObjectStorage $targetGroups;

    private function initializeEventTopic(): void
    {
        $this->paymentMethods = new ObjectStorage();
        $this->categories = new ObjectStorage();
        $this->targetGroups = new ObjectStorage();
    }

    public function getDisplayTitle(): string
    {
        return $this->getInternalTitle();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getStandardPrice(): float
    {
        return $this->standardPrice;
    }

    public function setStandardPrice(float $price): void
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('The price must be >= 0.0.', 1666112500);
        }

        $this->standardPrice = $price;
    }

    public function getEarlyBirdPrice(): float
    {
        return $this->earlyBirdPrice;
    }

    public function setEarlyBirdPrice(float $price): void
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('The price must be >= 0.0.', 1666112478);
        }

        $this->earlyBirdPrice = $price;
    }

    public function getSpecialPrice(): float
    {
        return $this->specialPrice;
    }

    public function setSpecialPrice(float $specialPrice): void
    {
        $this->specialPrice = $specialPrice;
    }

    public function getSpecialEarlyBirdPrice(): float
    {
        return $this->specialEarlyBirdPrice;
    }

    public function setSpecialEarlyBirdPrice(float $specialEarlyBirdPrice): void
    {
        $this->specialEarlyBirdPrice = $specialEarlyBirdPrice;
    }

    public function getEventType(): ?EventType
    {
        $eventType = $this->eventType;
        if ($eventType instanceof LazyLoadingProxy) {
            $eventType = $eventType->_loadRealInstance();
            $eventType = ($eventType instanceof EventType) ? $eventType : null;
            $this->eventType = $eventType;
        }

        return $eventType;
    }

    public function setEventType(?EventType $eventType): void
    {
        $this->eventType = $eventType;
    }

    public function hasAdditionalTerms(): bool
    {
        return $this->additionalTerms;
    }

    public function setAdditionalTerms(bool $additionalTerms): void
    {
        $this->additionalTerms = $additionalTerms;
    }

    public function isMultipleRegistrationPossible(): bool
    {
        return $this->multipleRegistrationPossible;
    }

    public function setMultipleRegistrationPossible(bool $multipleRegistrationPossible): void
    {
        $this->multipleRegistrationPossible = $multipleRegistrationPossible;
    }

    /**
     * @return ObjectStorage<PaymentMethod>
     */
    public function getPaymentMethods(): ObjectStorage
    {
        return $this->paymentMethods;
    }

    /**
     * @param ObjectStorage<PaymentMethod> $paymentMethods
     */
    public function setPaymentMethods(ObjectStorage $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * Returns true if the standard price is 0.0. (In this case, all other prices are irrelevant.)
     */
    public function isFreeOfCharge(): bool
    {
        return $this->getStandardPrice() === 0.0;
    }

    /**
     * Returns all prices, event if they might not be applicable right now (e.g. also always the early bird prices if
     * they are non-zero).
     *
     * If this event is free of charge, the result will be only the standard price with a total amount of zero.
     *
     * @return array<Price::PRICE_*, Price>
     */
    public function getAllPrices(): array
    {
        if ($this->isFreeOfCharge()) {
            return [Price::PRICE_STANDARD => new Price(0.0, 'price.standard', Price::PRICE_STANDARD)];
        }

        $prices = [
            Price::PRICE_STANDARD => new Price($this->getStandardPrice(), 'price.standard', Price::PRICE_STANDARD),
        ];
        if ($this->getEarlyBirdPrice() > 0.0) {
            $prices[Price::PRICE_EARLY_BIRD]
                = new Price($this->getEarlyBirdPrice(), 'price.earlyBird', Price::PRICE_EARLY_BIRD);
        }
        if ($this->getSpecialPrice() > 0.0) {
            $prices[Price::PRICE_SPECIAL] = new Price($this->getSpecialPrice(), 'price.special', Price::PRICE_SPECIAL);
        }
        if ($this->getSpecialEarlyBirdPrice() > 0.0) {
            $prices[Price::PRICE_SPECIAL_EARLY_BIRD] = new Price(
                $this->getSpecialEarlyBirdPrice(),
                'price.specialEarlyBird',
                Price::PRICE_SPECIAL_EARLY_BIRD,
            );
        }

        return $prices;
    }

    /**
     * @param Price::PRICE_* $priceCode
     *
     * @throws \UnexpectedValueException if there is no price with that code
     */
    public function getPriceByPriceCode(string $priceCode): Price
    {
        $allPrices = $this->getAllPrices();
        if (!isset($allPrices[$priceCode])) {
            throw new \UnexpectedValueException(
                'This event does not have a price with the code "' . $priceCode . '".',
                1668096769,
            );
        }

        return $allPrices[$priceCode];
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    /**
     * @param ObjectStorage<Category> $categories
     */
    public function setCategories(ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * @return ObjectStorage<TargetGroup>
     */
    public function getTargetGroups(): ObjectStorage
    {
        return $this->targetGroups;
    }

    /**
     * @param ObjectStorage<TargetGroup> $targetGroups
     */
    public function setTargetGroups(ObjectStorage $targetGroups): void
    {
        $this->targetGroups = $targetGroups;
    }

}
