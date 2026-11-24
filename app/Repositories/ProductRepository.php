<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Database\QueryBuilder;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Page;
use LogicException;

final class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function find(int $id): ?Product
    {
        return self::product($this->forSale()->where('id', $id)->first());
    }

    public function findForUpdate(int $id): ?Product
    {
        return self::product($this->forSale()->where('id', $id)->lockForUpdate()->first());
    }

    public function paginate(int $page, int $perPage, string $sort, string $direction, string $search): Page
    {
        return $this->forSale()
            ->whereContains('name', $search)
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($page, $perPage)
            ->map(self::toProduct(...));
    }

    public function create(string $name, string $price, int $quantityAvailable): Product
    {
        $id = $this->database->table('products')->insert(['name' => $name, 'price' => $price, 'quantity_available' => $quantityAvailable]);

        return $this->find($id) ?? throw new LogicException('Created product not found');
    }

    public function update(int $id, string $name, string $price, int $quantityAvailable): void
    {
        $this->forSale()->where('id', $id)->update(['name' => $name, 'price' => $price, 'quantity_available' => $quantityAvailable]);
    }

    public function delete(int $id): void
    {
        $this->forSale()->where('id', $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function decrementStock(int $id, int $quantity): void
    {
        // Raw SQL on purpose: the >= guard keeps stock from going negative even if a caller skipped the lock.
        $statement = $this->database->pdo()->prepare(
            'UPDATE products SET quantity_available = quantity_available - ? WHERE id = ? AND deleted_at IS NULL AND quantity_available >= ?',
        );
        $statement->execute([$quantity, $id, $quantity]);
        if ($statement->rowCount() !== 1) {
            throw new LogicException("Could not take {$quantity} from the stock of product {$id}");
        }
    }

    public function lowStock(int $threshold): array
    {
        $rows = $this->forSale()->where('quantity_available', $threshold, '<=')->orderBy('quantity_available')->orderBy('name')->get();

        return array_map(self::toProduct(...), $rows);
    }

    public function count(): int
    {
        return $this->forSale()->count();
    }

    /**
     * A query on products still for sale (not soft-deleted).
     * @return QueryBuilder
     */
    private function forSale(): QueryBuilder
    {
        return $this->database->table('products')->whereNull('deleted_at');
    }

    /**
     * The product for a row, or null.
     * @param array<string, mixed>|null $row
     * @return Product|null
     */
    private static function product(?array $row): ?Product
    {
        return $row === null ? null : self::toProduct($row);
    }

    /**
     * The product for a `products` row.
     * @param array<string, mixed> $row
     * @return Product
     */
    private static function toProduct(array $row): Product
    {
        return new Product((int) $row['id'], (string) $row['name'], (string) $row['price'], (int) $row['quantity_available']);
    }
}
