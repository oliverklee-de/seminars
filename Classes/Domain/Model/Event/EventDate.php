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

    public function getDisplayTitle(): string
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getDisplayTitle() : '';
    }

    public function getDescription(): string
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getDescription() : '';
    }

    public function getTeaser(): string
    {
        $topic = $this->getTopic();

        if (!$topic instanceof EventTopic) {
            throw new \UnexpectedValueException('This event date does not have a topic.', 1784760657);
        }

        return $topic->getTeaser();
    }

    public function getStandardPrice(): float
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getStandardPrice() : 0.0;
    }

    public function getEarlyBirdPrice(): float
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getEarlyBirdPrice() : 0.0;
    }

    public function getSpecialPrice(): float
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getSpecialPrice() : 0.0;
    }

    public function getSpecialEarlyBirdPrice(): float
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getSpecialEarlyBirdPrice() : 0.0;
    }

    public function getEventType(): ?EventType
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic ? $topic->getEventType() : null;
    }

    public function hasAdditionalTerms(): bool
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic && $topic->hasAdditionalTerms();
    }

    public function isMultipleRegistrationPossible(): bool
    {
        $topic = $this->getTopic();

        return $topic instanceof EventTopic && $topic->isMultipleRegistrationPossible();
    }

    /**
     * @return ObjectStorage<PaymentMethod>
     */
    public function getPaymentMethods(): ObjectStorage
    {
        $topic = $this->getTopic();
        if (!$topic instanceof EventTopic) {
            return new ObjectStorage();
        }

        return $topic->getPaymentMethods();
    }

    /**
     * Returns true if the standard price is 0.0. (In this case, all other prices are irrelevant.)
     */
    public function isFreeOfCharge(): bool
    {
        $topic = $this->getTopic();
        if (!$topic instanceof EventTopic) {
            return true;
        }

        return $topic->isFreeOfCharge();
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
        $topic = $this->getTopic();
        if (!$topic instanceof EventTopic) {
            return [];
        }

        return $topic->getAllPrices();
    }

    /**
     * @param Price::PRICE_* $priceCode
     *
     * @throws \UnexpectedValueException if this date has no topic, or if there is no price with that code
     */
    public function getPriceByPriceCode(string $priceCode): Price
    {
        $topic = $this->getTopic();
        if (!$topic instanceof EventTopic) {
            throw new \UnexpectedValueException('This event date does not have a topic.', 1668096905);
        }

        return $topic->getPriceByPriceCode($priceCode);
    }

    /**
     * @throws \UnexpectedValueException if this event date is without topic, no categories shown.
     */
    public function getCategories(): ObjectStorage
    {
        $topic = $this->getTopic();
        if (!$topic instanceof EventTopic) {
            throw new \UnexpectedValueException('This event date does not have a topic.', 1668096905);
        }
        return $topic->getCategories();
    }

    /**
     * @throws \UnexpectedValueException if this event date is without topic, no target groups shown.
     */
    public function getTargetGroups(): ObjectStorage
    {
        $topic = $this->getTopic();

        if (!$topic instanceof EventTopic) {
            throw new \UnexpectedValueException('This event date does not have a topic.', 1784805134);
        }

        return $topic->getTargetGroups();
    }
}
