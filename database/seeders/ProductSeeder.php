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

            $insert = $this->database->pdo()->prepare(
                'INSERT INTO products (name, price, quantity_available) VALUES (?, ?, ?)',
            );

            $added = [];
            foreach (self::PRODUCTS as [$name, $price, $stock]) {
                $insert->execute([$name, $price, $stock]);
                $added[] = $name;
            }

            return $added;
        });
    }
}
