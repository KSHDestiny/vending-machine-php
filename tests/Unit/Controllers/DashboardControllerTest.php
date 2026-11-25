<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;

/*
 * The admin dashboard through the real routes with mocked repositories: the sales
 * trend uses the chosen range, unknown ranges fall back to 14 days, admins only.
 */
final class DashboardControllerTest extends TestCase
{
    /** @var TransactionRepositoryInterface&MockObject */
    private TransactionRepositoryInterface $transactions;

    private Application $app;

    protected function setUp(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('lowStock')->willReturn([]);
        $this->transactions = $this->createMock(TransactionRepositoryInterface::class);
        $this->transactions->method('summary')->willReturn(['count' => 0, 'items_sold' => 0, 'revenue' => '0.000']);
        $this->transactions->method('paginate')->willReturn(new Page([], 0, 1, 5));
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturnCallback(static fn (int $id): ?User => [1 => self::user(1, Role::Admin), 2 => self::user(2, Role::User)][$id] ?? null);

        $this->app = new Application(['products' => $products, 'transactions' => $this->transactions, 'users' => $users]);
    }

    public function testSalesTrendShowsTheChosenRangeEndingToday(): void
    {
        $tomorrow = new DateTimeImmutable('tomorrow');
        $this->transactions->expects(self::once())
            ->method('dailySales')
            ->with($tomorrow->modify('-7 days'), $tomorrow)
            ->willReturn([(new DateTimeImmutable('today'))->format('Y-m-d') => ['count' => 2, 'revenue' => '13.770']]);
        $this->app->actingAs(self::user(1, Role::Admin));

        $body = $this->app->send('GET', '/admin', query: ['days' => '7'])->body;

        self::assertStringContainsString('$13.77</span>', $body);
        self::assertStringContainsString('in the last 7 days', $body);
        self::assertSame(7, substr_count($body, '<g>'), 'one bar per day');
    }

    public function testUnknownRangeFallsBackToFourteenDays(): void
    {
        $tomorrow = new DateTimeImmutable('tomorrow');
        $this->transactions->expects(self::once())->method('dailySales')->with($tomorrow->modify('-14 days'), $tomorrow)->willReturn([]);
        $this->app->actingAs(self::user(1, Role::Admin));

        $body = $this->app->send('GET', '/admin', query: ['days' => '9999'])->body;

        self::assertStringContainsString('in the last 14 days', $body);
    }

    public function testCustomersCannotSeeTheDashboard(): void
    {
        $this->transactions->expects(self::never())->method('dailySales');
        $this->app->actingAs(self::user(2, Role::User));

        self::assertSame(403, $this->app->send('GET', '/admin')->status);
    }

    /**
     * A user with the role.
     * @return User
     */
    private static function user(int $id, Role $role): User
    {
        return new User($id, ucfirst($role->value), "user{$id}@example.com", 'hash', $role);
    }
}
