<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ValidationException;
use App\Support\Validator;
use LogicException;

/*
 * A product for sale, with the rules about its own stock and price: whether it
 * can supply a quantity, and the exact total for buying it.
 */
final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        /** @var string DECIMAL(10,3) as an exact string, e.g. "6.885". */
        public readonly string $price,
        public readonly int $quantityAvailable,
    ) {
    }

    /**
     * Whether nothing is left in stock.
     * @return bool
     */
    public function isSoldOut(): bool
    {
        return $this->quantityAvailable === 0;
    }

    /**
     * Whether the stock covers the quantity.
     * @return bool
     */
    public function hasStockFor(int $quantity): bool
    {
        return $quantity <= $this->quantityAvailable;
    }

    /**
     * Throws if the quantity is below 1 or more than the stock; nothing is sold then.
     * @return void
     */
    public function ensureCanSupply(int $quantity): void
    {
        if ($quantity < 1) {
            throw new ValidationException(['quantity' => 'Quantity must be at least 1.']);
        }

        if (!$this->hasStockFor($quantity)) {
            throw new InsufficientStockException($this->quantityAvailable);
        }
    }

    /**
     * The exact total for buying the quantity, e.g. 2 × 6.885 = "13.770"; never a float.
     * @return string
     */
    public function totalFor(int $quantity): string
    {
        if (!is_numeric($this->price)) {
            throw new LogicException("Product {$this->id} has an invalid price");
        }

        $total = bcmul($this->price, (string) $quantity, 3);
        if (bccomp($total, Validator::MAX_AMOUNT, 3) > 0) {
            throw new ValidationException(['quantity' => 'The order total is too large; buy fewer at a time.']);
        }

        return $total;
    }

    /**
     * The product as API fields; the price stays a string so no precision is lost.
     * @return array{id: int, name: string, price: string, quantity_available: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'price' => $this->price, 'quantity_available' => $this->quantityAvailable];
    }
}
