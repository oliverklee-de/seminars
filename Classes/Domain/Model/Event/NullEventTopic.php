<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Event;

use OliverKlee\Seminars\Domain\Model\Category;
use OliverKlee\Seminars\Domain\Model\EventType;
use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Dummy event topic to be used as empty option in selects.
 */
class NullEventTopic extends AbstractDomainObject implements EventTopicInterface
{
    /**
     * @return int<1, max>|null
     */
    public function getUid(): ?int
    {
        return null;
    }

    public function getTitle(): string
    {
        return '';
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage
    {
        /** @var ObjectStorage<Category> $categories */
        $categories = new ObjectStorage();

        return $categories;
    }

    public function isHidden(): bool
    {
        return false;
    }

    /**
     * @return never
     */
    public function setHidden(bool $hidden): void
    {
        throw new \BadMethodCallException('Cannot set hidden on NullEventTopic', 1757950691);
    }

    public function isSingleEvent(): bool
    {
        return false;
    }

    public function isEventDate(): bool
    {
        return false;
    }

    public function isEventTopic(): bool
    {
        return true;
    }

    public function getInternalTitle(): string
    {
        return '';
    }

    public function getDisplayTitle(): string
    {
        return '';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function getStandardPrice(): float
    {
        return 0.0;
    }

    public function getEarlyBirdPrice(): float
    {
        return 0.0;
    }

    public function getSpecialPrice(): float
    {
        return 0.0;
    }

    public function getSpecialEarlyBirdPrice(): float
    {
        return 0.0;
    }

    public function getEventType(): ?EventType
    {
        return null;
    }

    /**
     * @return int<0, max>
     */
    public function getOwnerUid(): int
    {
        return 0;
    }

    public function hasAdditionalTerms(): bool
    {
        return false;
    }

    public function isMultipleRegistrationPossible(): bool
    {
        return false;
    }

    /**
     * @return ObjectStorage<PaymentMethod>
     */
    public function getPaymentMethods(): ObjectStorage
    {
        /** @var ObjectStorage<PaymentMethod> $paymentMethods */
        $paymentMethods = new ObjectStorage();

        return $paymentMethods;
    }

    public function isFreeOfCharge(): bool
    {
        return true;
    }

    /**
     * @return array<Price::PRICE_*, Price>
     */
    public function getAllPrices(): array
    {
        return [];
    }

    /**
     * @return never
     */
    public function getPriceByPriceCode(string $priceCode): Price
    {
        throw new \BadMethodCallException('NullEventTopic does not have any prices.', 1757951370);
    }

    public function getRawData(): ?array
    {
        return null;
    }

    public function getStatistics(): ?EventStatistics
    {
        return null;
    }

    public function getSlug(): string
    {
        return '';
    }

    /**
     * @return never
     */
    public function setSlug(string $slug): void
    {
        throw new \BadMethodCallException('NullEventTopic cannot have a slug.', 1757951419);
    }

    public function hasDownloadableCertificate(): bool
    {
        return false;
    }
}
