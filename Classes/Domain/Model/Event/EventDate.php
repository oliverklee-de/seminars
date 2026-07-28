<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\EventType;
use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * This class represents a date for an event that has an association to a topic.
 */
class EventDate extends Event implements EventDateInterface
{
    use EventTrait;
    use EventDateTrait;

    protected ?EventTopic $topic = null;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->initializeEventDate();
    }

    public function isSingleEvent(): bool
    {
        return false;
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getEnsuredTopic(): EventTopicInterface
    {
        $topic = $this->getTopic();

        if (!$topic instanceof EventTopicInterface) {
            throw new \RuntimeException('This event date does not have a topic.', 1668096905);
        }

        return $topic;
    }

    public function isEventDate(): bool
    {
        return true;
    }

    public function isEventTopic(): bool
    {
        return false;
    }

    public function getTopic(): ?EventTopic
    {
        return $this->topic;
    }

    public function setTopic(EventTopic $topic): void
    {
        $this->topic = $topic;
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getDisplayTitle(): string
    {
        return $this->getEnsuredTopic()->getDisplayTitle();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getDescription(): string
    {
        return $this->getEnsuredTopic()->getDescription();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getTeaser(): string
    {
        return $this->getEnsuredTopic()->getTeaser();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getStandardPrice(): float
    {
        return $this->getEnsuredTopic()->getStandardPrice();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getEarlyBirdPrice(): float
    {
        return $this->getEnsuredTopic()->getEarlyBirdPrice();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getSpecialPrice(): float
    {
        return $this->getEnsuredTopic()->getSpecialPrice();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getSpecialEarlyBirdPrice(): float
    {
        return $this->getEnsuredTopic()->getSpecialEarlyBirdPrice();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getEventType(): ?EventType
    {
        return $this->getEnsuredTopic()->getEventType();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function hasAdditionalTerms(): bool
    {
        return $this->getEnsuredTopic()->hasAdditionalTerms();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function isMultipleRegistrationPossible(): bool
    {
        return $this->getEnsuredTopic()->isMultipleRegistrationPossible();
    }

    /**
     * @return ObjectStorage<PaymentMethod>
     *
     * @throws \RuntimeException if this event date is without topic
     */
    public function getPaymentMethods(): ObjectStorage
    {
        return $this->getEnsuredTopic()->getPaymentMethods();
    }

    /**
     * Returns true if the standard price is 0.0. (In this case, all other prices are irrelevant.)
     *
     * @throws \RuntimeException if this event date is without topic
     */
    public function isFreeOfCharge(): bool
    {
        return $this->getEnsuredTopic()->isFreeOfCharge();
    }

    /**
     * Returns all prices, event if they might not be applicable right now (e.g. also always the early bird prices if
     * they are non-zero).
     *
     * If this event is free of charge, the result will be only the standard price with a total amount of zero.
     *
     * @return array<Price::PRICE_*, Price>
     *
     * @throws \RuntimeException if this event date is without topic
     */
    public function getAllPrices(): array
    {
        return $this->getEnsuredTopic()->getAllPrices();
    }

    /**
     * @param Price::PRICE_* $priceCode
     *
     * @throws \RuntimeException if this event date is without topic
     */
    public function getPriceByPriceCode(string $priceCode): Price
    {
        return $this->getEnsuredTopic()->getPriceByPriceCode($priceCode);
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getCategories(): ObjectStorage
    {
        return $this->getEnsuredTopic()->getCategories();
    }

    /**
     * @throws \RuntimeException if this event date is without topic
     */
    public function getTargetGroups(): ObjectStorage
    {
        return $this->getEnsuredTopic()->getTargetGroups();
    }
}
