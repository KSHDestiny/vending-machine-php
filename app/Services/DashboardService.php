<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use DateTimeImmutable;

/*
 * The admin dashboard's figures: products, low stock, sales, the daily sales trend
 * and the latest purchases.
 */
final class DashboardService
{
    /** @var int Stock at or below this counts as low. */
    public const LOW_STOCK = 5;

    /** @var list<int> The sales-trend ranges the dashboard offers, in days. */
    public const TREND_DAYS = [7, 14, 30];

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly TransactionRepositoryInterface $transactions,
    ) {
    }

    /**
     * Everything the dashboard shows, with the sales trend for the last $days days up to $today.
     * @return array{products: int, low_stock: list<Product>, sales: array{count: int, items_sold: int, revenue: string}, latest: list<Transaction>, trend: array{days: list<array{date: string, count: int, revenue: string}>, count: int, revenue: string}}
     */
    public function overview(int $days, DateTimeImmutable $today): array
    {
        return [
            'products' => $this->products->count(),
            'low_stock' => $this->products->lowStock(self::LOW_STOCK),
            'sales' => $this->transactions->summary(),
            'latest' => $this->transactions->paginate(1, 5)->items,
            'trend' => $this->salesTrend($days, $today),
        ];
    }

    /**
     * Purchases and revenue for each of the last $days days ending with $today (days without sales are zero), plus totals.
     * @return array{days: list<array{date: string, count: int, revenue: string}>, count: int, revenue: string}
     */
    public function salesTrend(int $days, DateTimeImmutable $today): array
    {
        $end = $today->setTime(0, 0)->modify('+1 day');
        $start = $end->modify('-' . max(1, $days) . ' days');
        $sales = $this->transactions->dailySales($start, $end);

        $series = [];
        $count = 0;
        $revenue = '0.000';
        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $sale = $sales[$date] ?? ['count' => 0, 'revenue' => '0.000'];
            $series[] = ['date' => $date] + $sale;
            $count += $sale['count'];
            $revenue = bcadd($revenue, $sale['revenue'], 3);
        }

        return ['days' => $series, 'count' => $count, 'revenue' => $revenue];
    }
}
