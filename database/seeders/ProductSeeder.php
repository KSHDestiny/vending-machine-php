<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Database\Database;

/*
 * The assignment's three products. The PDF gives no starting stock, so each gets 100.
 * Seed only a new catalog: restarts must not undo admin renames or soft deletes.
 */
final class ProductSeeder
{
    /** @var list<array{string, string, int}> Name, price and starting stock. */
    private const PRODUCTS = [['Coke', '3.990', 100], ['Pepsi', '6.885', 100], ['Water', '0.500', 100]];

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * The names of the products added by this run.
     * @return list<string>
     */
    public function run(): array
    {
        return $this->database->transaction(function (): array {
            if ($this->database->table('products')->count() !== 0) {
                return [];
            }

            $pdo = $this->database->pdo();
            $insert = $pdo->prepare('INSERT INTO products (name, price, quantity_available) VALUES (?, ?, ?)');
            // The starting stock goes into the stock history too, so it always explains the stock.
            $record = $pdo->prepare(
                "INSERT INTO inventory_movements (product_id, movement_type, quantity_change, quantity_before, quantity_after, reason) VALUES (?, 'INITIAL_STOCK', ?, 0, ?, 'Seeded')",
            );

            $added = [];
            foreach (self::PRODUCTS as [$name, $price, $stock]) {
                $insert->execute([$name, $price, $stock]);
                $record->execute([(int) $pdo->lastInsertId(), $stock, $stock]);
                $added[] = $name;
            }

            return $added;
        });
    }
}
