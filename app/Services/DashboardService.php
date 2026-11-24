<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;

/*
 * The admin dashboard's figures: products, low stock, sales and the latest purchases.
 */
final class DashboardService
{
    /** @var int Stock at or below this counts as low. */
    public const LOW_STOCK = 5;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly TransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * Everything the dashboard shows.
     * @return array{products: int, low_stock: list<Product>, sales: array{count: int, items_sold: int, revenue: string}, latest: list<Transaction>}
     */
    public function overview(): array
    {
        return [
            'products' => $this->products->count(),
            'low_stock' => $this->products->lowStock(self::LOW_STOCK),
            'sales' => $this->transactions->summary(),
            'latest' => $this->transactions->paginate(1, 5)->items,
        ];
    }
}
