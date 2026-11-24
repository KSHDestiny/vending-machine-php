<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ValidationException;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testTotalIsExactDecimalArithmetic(): void
    {
        $pepsi = new Product(2, 'Pepsi', '6.885', 10);

        self::assertSame('13.770', $pepsi->totalFor(2));
        self::assertSame('20.655', $pepsi->totalFor(3));
        self::assertSame('3.990', (new Product(1, 'Coke', '3.990', 10))->totalFor(1));
    }

    public function testTotalLargerThanTheColumnIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new Product(1, 'Gold', '9999999.999', 999_999_999))->totalFor(2);
    }

    public function testCanSupplyUpToTheStock(): void
    {
        $product = new Product(2, 'Pepsi', '6.885', 3);
        $product->ensureCanSupply(3);

        self::assertTrue($product->hasStockFor(3));
        self::assertFalse($product->hasStockFor(4));
        self::assertFalse($product->isSoldOut());
        self::assertTrue((new Product(2, 'Pepsi', '6.885', 0))->isSoldOut());
    }

    public function testMoreThanTheStockIsRefusedWithTheAvailableQuantity(): void
    {
        try {
            (new Product(2, 'Pepsi', '6.885', 3))->ensureCanSupply(4);
            self::fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            self::assertSame(3, $e->available);
            self::assertSame(['quantity' => 'Only 3 left in stock.'], $e->errors);
        }
    }

    public function testZeroQuantityIsRefused(): void
    {
        $this->expectException(ValidationException::class);

        (new Product(2, 'Pepsi', '6.885', 3))->ensureCanSupply(0);
    }
}
