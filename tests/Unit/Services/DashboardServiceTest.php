<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\DashboardService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DashboardServiceTest extends TestCase
{
    public function testSalesTrendCoversEveryDayEndingTodayWithExactTotals(): void
    {
        $transactions = $this->createMock(TransactionRepositoryInterface::class);
        $transactions->expects(self::once())
            ->method('dailySales')
            ->with(new DateTimeImmutable('2026-03-08'), new DateTimeImmutable('2026-03-11'))
            ->willReturn(['2026-03-08' => ['count' => 2, 'revenue' => '13.770'], '2026-03-10' => ['count' => 1, 'revenue' => '0.500']]);

        $trend = (new DashboardService($this->createStub(ProductRepositoryInterface::class), $transactions))
            ->salesTrend(3, new DateTimeImmutable('2026-03-10 15:45:00'));

        self::assertSame([
            ['date' => '2026-03-08', 'count' => 2, 'revenue' => '13.770'],
            ['date' => '2026-03-09', 'count' => 0, 'revenue' => '0.000'],
            ['date' => '2026-03-10', 'count' => 1, 'revenue' => '0.500'],
        ], $trend['days']);
        self::assertSame('14.270', $trend['revenue']);
        self::assertSame(3, $trend['count']);
    }
}
