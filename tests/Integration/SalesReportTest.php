<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use DateTimeImmutable;
use Tests\Support\DatabaseTestCase;

/*
 * The dashboard's daily sales against the real test database: grouped by day in SQL,
 * exact decimal totals, and the range end excluded.
 */
final class SalesReportTest extends DatabaseTestCase
{
    public function testDailySalesAreGroupedByDayWithinTheRange(): void
    {
        $buyer = $this->createUser();
        $pepsi = (new ProductRepository($this->database))->create('Pepsi', '6.885', 100);
        $sell = function (string $at, int $quantity) use ($buyer, $pepsi): void {
            $this->database->pdo()->prepare(
                'INSERT INTO transactions (user_id, product_id, product_name, quantity, unit_price, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            )->execute([$buyer->id, $pepsi->id, 'Pepsi', $quantity, '6.885', bcmul('6.885', (string) $quantity, 3), $at]);
        };
        $sell('2026-01-01 23:59:59', 1); // before the range
        $sell('2026-01-02 00:00:00', 2);
        $sell('2026-01-02 18:30:00', 1);
        $sell('2026-01-04 09:00:00', 3);
        $sell('2026-01-05 00:00:00', 1); // the range end is excluded

        $days = (new TransactionRepository($this->database))->dailySales(new DateTimeImmutable('2026-01-02'), new DateTimeImmutable('2026-01-05'));

        self::assertSame([
            '2026-01-02' => ['count' => 2, 'revenue' => '20.655'],
            '2026-01-04' => ['count' => 1, 'revenue' => '20.655'],
        ], $days);
    }
}
