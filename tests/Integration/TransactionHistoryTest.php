<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Support\TransactionFilter;
use Tests\Support\DatabaseTestCase;

/*
 * Transaction history against the real test database: purchases keep the product name
 * they were bought under, and the admin filters (customer, product, dates) and their
 * totals and export all select the same purchases.
 */
final class TransactionHistoryTest extends DatabaseTestCase
{
    public function testAPurchaseKeepsTheProductNameItWasBoughtUnder(): void
    {
        $products = new ProductRepository($this->database);
        $pepsi = $products->create('Pepsi', '6.885', 10);
        $purchase = $this->purchases()->purchase($this->createUser(), $pepsi->id, '1');

        $products->update($pepsi->id, 'Pepsi Max', '7.000', $pepsi->status);
        $products->delete($pepsi->id);

        self::assertSame('Pepsi', (new TransactionRepository($this->database))->find($purchase->id)?->productName);
    }

    public function testFiltersByCustomerProductAndDatesAndTotalsTheMatches(): void
    {
        $alice = $this->createUser(Role::User, 'alice@example.com');
        $bob = $this->createUser(Role::User, 'bob@shop.test');
        $products = new ProductRepository($this->database);
        $coke = $products->create('Coke', '3.990', 50);
        $water = $products->create('Water', '0.500', 50);
        $sale = function (int $userId, int $productId, string $name, int $quantity, string $price, string $total, string $at): void {
            $this->database->pdo()->prepare(
                'INSERT INTO transactions (user_id, product_id, product_name, quantity, unit_price, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            )->execute([$userId, $productId, $name, $quantity, $price, $total, $at]);
        };
        $sale($alice->id, $coke->id, 'Coke', 2, '3.990', '7.980', '2026-03-01 10:00:00');
        $sale($alice->id, $water->id, 'Water', 3, '0.500', '1.500', '2026-03-02 23:59:59');
        $sale($bob->id, $coke->id, 'Coke', 1, '3.990', '3.990', '2026-03-03 00:00:00');
        $transactions = new TransactionRepository($this->database);
        $ids = fn (TransactionFilter $filter): array => array_map(static fn ($t): string => "{$t->userEmail} {$t->productName}", $transactions->paginate(1, 10, $filter)->items);

        self::assertSame(['bob@shop.test Coke', 'alice@example.com Water', 'alice@example.com Coke'], $ids(new TransactionFilter()), 'newest first');
        self::assertSame(['alice@example.com Water', 'alice@example.com Coke'], $ids(new TransactionFilter(customer: 'ALICE')), 'customer by name or email, any case');
        self::assertSame(['bob@shop.test Coke'], $ids(new TransactionFilter(customer: 'shop')));
        self::assertSame(['bob@shop.test Coke', 'alice@example.com Coke'], $ids(new TransactionFilter(product: 'cok')));
        self::assertSame(['alice@example.com Water'], $ids(TransactionFilter::fromQuery(['from' => '2026-03-02', 'to' => '2026-03-02'])), 'the end date includes its whole day');
        self::assertSame(['alice@example.com Coke'], $ids(TransactionFilter::fromQuery(['customer' => 'alice', 'product' => 'coke', 'to' => '2026-03-01'])));

        self::assertSame(['count' => 2, 'revenue' => '11.970'], $transactions->totals(new TransactionFilter(product: 'coke')), '7.980 + 3.990');
        self::assertSame(['count' => 0, 'revenue' => '0.000'], $transactions->totals(new TransactionFilter(customer: 'nobody')));
        $exported = iterator_to_array($transactions->each(new TransactionFilter(customer: 'alice')), false);
        self::assertSame(['Water', 'Coke'], array_map(static fn ($t): string => (string) $t->productName, $exported), 'export selects the same purchases');
    }

    /**
     * The purchase service on the test database.
     * @return PurchaseService
     */
    private function purchases(): PurchaseService
    {
        $products = new ProductRepository($this->database);

        return new PurchaseService($products, new TransactionRepository($this->database), new InventoryService($products, new InventoryRepository($this->database), $this->database), $this->database);
    }
}
