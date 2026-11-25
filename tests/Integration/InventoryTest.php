<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\MovementType;
use App\Enums\Role;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ValidationException;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use PDOException;
use Tests\Support\DatabaseTestCase;

/*
 * Stock history against the real test database: every way stock changes records a
 * movement whose before + change = after matches the product, failed purchases record
 * nothing, and products off sale are hidden from customers and cannot be bought.
 */
final class InventoryTest extends DatabaseTestCase
{
    public function testEveryStockChangeIsRecordedAndAddsUpToTheCurrentStock(): void
    {
        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        $buyer = $this->createUser();
        $product = $this->products()->create($admin, ['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => '10']);

        $this->inventory()->restock($admin, $product->id, ['quantity' => '5', 'reason' => 'Delivery']);
        $this->products()->update($admin, $product->id, ['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => '12']);
        $purchase = $this->purchases()->purchase($buyer, $product->id, '2');

        $rows = $this->database->table('inventory_movements')->orderBy('id')->get();
        $summary = array_map(static fn (array $row): array => [$row['movement_type'], (int) $row['quantity_change'], (int) $row['quantity_before'], (int) $row['quantity_after']], $rows);
        self::assertSame([
            ['INITIAL_STOCK', 10, 0, 10],
            ['RESTOCK', 5, 10, 15],
            ['ADJUSTMENT', -3, 15, 12],
            ['PURCHASE', -2, 12, 10],
        ], $summary);
        self::assertSame(10, (new ProductRepository($this->database))->find($product->id)?->quantityAvailable, 'the last movement matches the stock');
        self::assertSame(['Delivery', 'Changed on the product form'], [$rows[1]['reason'], $rows[2]['reason']]);
        self::assertSame([$admin->id, $admin->id, $admin->id, $buyer->id], array_map(static fn (array $row): int => (int) $row['user_id'], $rows));
        self::assertSame($purchase->id, (int) $rows[3]['transaction_id']);
    }

    public function testEditsThatKeepTheStockAndRefusedPurchasesRecordNothing(): void
    {
        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        $product = $this->products()->create($admin, ['name' => 'Water', 'price' => '0.500', 'quantity_available' => '3']);

        $this->products()->update($admin, $product->id, ['name' => 'Still water', 'price' => '0.600', 'quantity_available' => '3']);
        try {
            $this->purchases()->purchase($this->createUser(), $product->id, '4');
            self::fail('Buying more than the stock should be refused');
        } catch (InsufficientStockException) {
        }

        self::assertSame(1, $this->database->table('inventory_movements')->count(), 'only the initial stock');
    }

    public function testRestockNeedsAPositiveWholeQuantity(): void
    {
        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        $product = $this->products()->create($admin, ['name' => 'Coke', 'price' => '3.990', 'quantity_available' => '1']);

        foreach (['0', '-5', '1.5', 'abc', ''] as $quantity) {
            try {
                $this->inventory()->restock($admin, $product->id, ['quantity' => $quantity]);
                self::fail("Restocking {$quantity} should be refused");
            } catch (ValidationException $e) {
                self::assertArrayHasKey('quantity', $e->errors);
            }
        }
        self::assertSame(1, $this->database->table('inventory_movements')->count());
    }

    public function testHistoryIsNewestFirstAndFiltersByType(): void
    {
        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        $product = $this->products()->create($admin, ['name' => 'Coke', 'price' => '3.990', 'quantity_available' => '1']);
        $this->inventory()->restock($admin, $product->id, ['quantity' => '2']);
        $this->inventory()->restock($admin, $product->id, ['quantity' => '3']);

        $all = $this->inventory()->history($product->id, 1, '');
        $restocks = $this->inventory()->history($product->id, 1, 'RESTOCK');

        self::assertSame([3, 2, 1], array_map(static fn ($movement): int => $movement->quantityChange, $all->items));
        self::assertSame([MovementType::Restock, MovementType::Restock], array_map(static fn ($movement) => $movement->type, $restocks->items));
        self::assertSame($admin->name, $all->items[0]->userName, 'who made the change is shown');
    }

    public function testTheDatabaseRejectsAMovementThatDoesNotAddUp(): void
    {
        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        $product = $this->products()->create($admin, ['name' => 'Coke', 'price' => '3.990', 'quantity_available' => '1']);

        $this->expectException(PDOException::class);

        $this->database->pdo()->exec("INSERT INTO inventory_movements (product_id, movement_type, quantity_change, quantity_before, quantity_after) VALUES ({$product->id}, 'RESTOCK', 5, 1, 99)");
    }

    public function testProductsOffSaleAreHiddenFromCustomersAndCannotBeBought(): void
    {
        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        $products = $this->products();
        $coke = $products->create($admin, ['name' => 'Coke', 'price' => '3.990', 'quantity_available' => '5']);
        $water = $products->create($admin, ['name' => 'Water', 'price' => '0.500', 'quantity_available' => '5']);
        $products->update($admin, $water->id, ['name' => 'Water', 'price' => '0.500', 'quantity_available' => '5', 'status' => 'inactive']);

        $names = static fn ($page): array => array_map(static fn ($product): string => $product->name, $page->items);
        self::assertSame(['Coke'], $names($products->list(1, onSaleOnly: true)), 'customers');
        self::assertSame(['Coke', 'Water'], $names($products->list(1)), 'admins');
        self::assertSame($coke->id, $products->findOnSale($coke->id)->id);

        try {
            $this->purchases()->purchase($this->createUser(), $water->id, '1');
            self::fail('Buying a product off sale should be refused');
        } catch (ValidationException $e) {
            self::assertSame(['quantity' => 'This product is not on sale.'], $e->errors);
        }
        self::assertSame(5, (new ProductRepository($this->database))->find($water->id)?->quantityAvailable);
        self::assertSame(0, $this->database->table('transactions')->count());
    }

    /**
     * The inventory service on the test database.
     * @return InventoryService
     */
    private function inventory(): InventoryService
    {
        return new InventoryService(new ProductRepository($this->database), new InventoryRepository($this->database), $this->database);
    }

    /**
     * The product service on the test database.
     * @return ProductService
     */
    private function products(): ProductService
    {
        return new ProductService(new ProductRepository($this->database), $this->inventory(), $this->database);
    }

    /**
     * The purchase service on the test database.
     * @return PurchaseService
     */
    private function purchases(): PurchaseService
    {
        return new PurchaseService(new ProductRepository($this->database), new TransactionRepository($this->database), $this->inventory(), $this->database);
    }
}
