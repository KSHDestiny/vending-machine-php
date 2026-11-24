<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Support\Page;

/*
 * Product persistence. Deleted products are never returned.
 */
interface ProductRepositoryInterface
{
    /**
     * The product, or null if it doesn't exist or was deleted.
     * @return Product|null
     */
    public function find(int $id): ?Product;

    /**
     * Like find(), but locks the row until the current transaction ends (SELECT ... FOR UPDATE).
     * @return Product|null
     */
    public function findForUpdate(int $id): ?Product;

    /**
     * One page of products whose name contains $search, sorted by a whitelisted column.
     * @param 'name'|'price'|'quantity_available' $sort
     * @param 'asc'|'desc' $direction
     * @return Page<Product>
     */
    public function paginate(int $page, int $perPage, string $sort, string $direction, string $search): Page;

    /**
     * The new product.
     * @return Product
     */
    public function create(string $name, string $price, int $quantityAvailable): Product;

    /**
     * Replaces the product's name, price and stock.
     * @return void
     */
    public function update(int $id, string $name, string $price, int $quantityAvailable): void;

    /**
     * Soft-deletes the product, so its transactions keep pointing at it.
     * @return void
     */
    public function delete(int $id): void;

    /**
     * Takes $quantity out of stock; call inside the transaction that locked the row.
     * @return void
     */
    public function decrementStock(int $id, int $quantity): void;

    /**
     * Products with at most $threshold in stock, lowest first.
     * @return list<Product>
     */
    public function lowStock(int $threshold): array;

    /**
     * The number of products for sale.
     * @return int
     */
    public function count(): int;
}
