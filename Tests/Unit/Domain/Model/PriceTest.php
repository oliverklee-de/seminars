<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Domain\Model;

use OliverKlee\Seminars\Domain\Model\Price;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Domain\Model\Price
 */
final class PriceTest extends UnitTestCase
{
    /**
     * @test
     */
    public function getAmountReturnsAmountProvidedToConstructor(): void
    {
        $amount = 149.95;
        $price = new Price($amount, 'labelKey', 'price_regular');

        self::assertSame($amount, $price->getAmount());
    }

    /**
     * @test
     */
    public function getLabelKeyReturnsLabelKeyProvidedToConstructor(): void
    {
        $labelKey = 'some-key';
        $price = new Price(0.0, $labelKey, 'price_regular');

        self::assertSame($labelKey, $price->getLabelKey());
    }

    /**
     * @test
     */
    public function getPriceCodeKeyReturnsPriceCodeProvidedToConstructor(): void
    {
        $priceCode = 'price_regular_early';
        $price = new Price(0.0, 'labelKey', $priceCode);

        self::assertSame($priceCode, $price->getPriceCode());
    }

    /**
     * @return array<string, array{0: Price::PRICE_*}>
     */
    public function validPriceCodeDataProvider(): array
    {
        return [
            'standard' => [Price::PRICE_STANDARD],
            'early bird' => [Price::PRICE_EARLY_BIRD],
            'special' => [Price::PRICE_SPECIAL],
            'special early bird' => [Price::PRICE_SPECIAL_EARLY_BIRD],
        ];
    }

    /**
     * @test
     *
     * @param Price::PRICE_* $priceCode
     *
     * @dataProvider validPriceCodeDataProvider
     */
    public function isPriceCodeValidForValidPriceCodeReturnsTrue(string $priceCode): void
    {
        self::assertTrue(Price::isPriceCodeValid($priceCode));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public function invalidPriceCodeDataProvider(): array
    {
        return [
            'empty string' => [''],
            'some random string' => ['Wurstwasser'],
            'null' => [null],
        ];
    }

    /**
     * @test
     * @dataProvider invalidPriceCodeDataProvider
     */
    public function isPriceCodeValidForInvalidPriceCodeReturnsFalse(?string $priceCode): void
    {
        self::assertFalse(Price::isPriceCodeValid($priceCode));
    }
}
