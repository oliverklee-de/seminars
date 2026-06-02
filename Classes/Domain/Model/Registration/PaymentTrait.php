<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Registration;

use OliverKlee\Seminars\Domain\Model\PaymentMethod;
use OliverKlee\Seminars\Domain\Model\Price;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

/**
 * Payment-related for the `Registration` model.
 *
 * @phpstan-require-extends Registration
 */
trait PaymentTrait
{
    /**
     * @var Price::PRICE_*
     * @Validate("StringLength", options={"maximum": 32})
     */
    protected string $priceCode = Price::PRICE_STANDARD;

    /**
     * @Validate("StringLength", options={"maximum": 255})
     */
    protected string $humanReadablePrice = '';

    protected float $totalPrice = 0.0;

    /**
     * @var PaymentMethod|null
     * @phpstan-var PaymentMethod|LazyLoadingProxy|null
     * @Lazy
     */
    protected $paymentMethod;

    /**
     * @Validate("StringLength", options={"maximum": 50})
     */
    protected string $orderReference = '';

    protected ?\DateTime $invoiceDate = null;

    /**
     * @phpstan-var int<0, 99999999>|null
     */
    protected ?int $invoiceNumber = null;

    /**
     * @phpstan-var int<0, 99999999>|null
     */
    protected ?int $customerNumber = null;

    protected ?\DateTime $paymentDate = null;

    /**
     * @return Price::PRICE_*
     */
    public function getPriceCode(): string
    {
        $priceCode = $this->priceCode;
        if (!Price::isPriceCodeValid($priceCode)) {
            $priceCode = Price::PRICE_STANDARD;
        }

        return $priceCode;
    }

    /**
     * @param Price::PRICE_* $priceCode
     */
    public function setPriceCode(string $priceCode): void
    {
        $this->priceCode = $priceCode;
    }

    public function getHumanReadablePrice(): string
    {
        return $this->humanReadablePrice;
    }

    public function setHumanReadablePrice(string $humanReadablePrice): void
    {
        $this->humanReadablePrice = $humanReadablePrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        $paymentMethod = $this->paymentMethod;
        if ($paymentMethod instanceof LazyLoadingProxy) {
            $paymentMethod = $paymentMethod->_loadRealInstance();
            $paymentMethod = ($paymentMethod instanceof PaymentMethod) ? $paymentMethod : null;
            $this->paymentMethod = $paymentMethod;
        }

        return $paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getOrderReference(): string
    {
        return $this->orderReference;
    }

    public function setOrderReference(string $orderReference): void
    {
        $this->orderReference = $orderReference;
    }

    public function getInvoiceDate(): ?\DateTime
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(\DateTime $invoiceDate): void
    {
        $this->invoiceDate = $invoiceDate;
    }

    /**
     * @return int<0, 99999999>|null
     */
    public function getInvoiceNumber(): ?int
    {
        return $this->invoiceNumber;
    }

    /**
     * @param int<0, 99999999> $invoiceNumber
     */
    public function setInvoiceNumber(int $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function hasInvoice(): bool
    {
        $invoiceNumber = $this->getInvoiceNumber();
        return \is_int($invoiceNumber) && $invoiceNumber > 0
            && ($this->getInvoiceDate() instanceof \DateTime);
    }

    /**
     * @return int<0, 99999999>|null
     */
    public function getCustomerNumber(): ?int
    {
        return $this->customerNumber;
    }

    /**
     * @param int<0, 99999999> $customerNumber
     */
    public function setCustomerNumber(int $customerNumber): void
    {
        $this->customerNumber = $customerNumber;
    }

    public function getPaymentDate(): ?\DateTime
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTime $paymentDate): void
    {
        $this->paymentDate = $paymentDate;
    }

    public function isPaid(): bool
    {
        return $this->getPaymentDate() instanceof \DateTime;
    }
}
