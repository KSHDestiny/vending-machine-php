<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Enums\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use App\Support\TransactionFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;

final class TransactionsControllerTest extends TestCase
{
    /** @var TransactionRepositoryInterface&MockObject */
    private TransactionRepositoryInterface $transactions;

    private Application $app;

    protected function setUp(): void
    {
        $this->transactions = $this->createMock(TransactionRepositoryInterface::class);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturnCallback(static fn (int $id): ?User => [1 => self::user(1, Role::Admin), 2 => self::user(2), 3 => self::user(3)][$id] ?? null);

        $this->app = new Application(['transactions' => $this->transactions, 'users' => $users]);
    }

    public function testUserOnlySeesTheirOwnPurchases(): void
    {
        $this->app->actingAs(self::user(2));
        $this->transactions->expects(self::once())
            ->method('paginateForUser')
            ->with(2, 1, 10)
            ->willReturn(new Page([self::purchase(userId: 2)], 1, 1, 10));

        $response = $this->app->send('GET', '/my-transactions');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Pepsi', $response->body);
    }

    public function testUserCannotOpenAnotherUsersPurchase(): void
    {
        $this->app->actingAs(self::user(3));
        $this->transactions->expects(self::once())->method('find')->willReturn(self::purchase(userId: 2));

        self::assertSame(404, $this->app->send('GET', '/transactions/7')->status);
    }

    public function testBuyerAndAdminCanOpenThePurchaseConfirmation(): void
    {
        $this->transactions->expects(self::exactly(2))->method('find')->willReturn(self::purchase(userId: 2));

        $this->app->actingAs(self::user(2));
        self::assertStringContainsString('$13.77', $this->app->send('GET', '/transactions/7')->body);

        $this->app->actingAs(self::user(1, Role::Admin));
        self::assertSame(200, $this->app->send('GET', '/transactions/7')->status);
    }

    public function testAdminsSeePurchaseDetailsAndCustomersSeeTheirConfirmation(): void
    {
        $this->transactions->expects(self::exactly(2))->method('find')->willReturn(self::purchase(userId: 2));

        $this->app->actingAs(self::user(2));
        $customer = $this->app->send('GET', '/transactions/7')->body;
        $this->app->actingAs(self::user(1, Role::Admin));
        $admin = $this->app->send('GET', '/transactions/7')->body;

        self::assertStringContainsString('Purchase confirmation', $customer);
        self::assertStringContainsString('Keep shopping', $customer);
        self::assertStringNotContainsString('Buyer', $customer);
        self::assertStringContainsString('Purchase details', $admin);
        self::assertStringNotContainsString('Keep shopping', $admin);
        self::assertStringContainsString('href="/admin/users/2"', $admin, 'buyer links to their user page');
        self::assertStringContainsString('href="/admin/transactions"', $admin);
        self::assertMatchesRegularExpression('#href="/admin/products/\d+/inventory"#', $admin, 'back to the product\'s stock history');
    }

    public function testAdminFiltersArePassedThroughAndTotalled(): void
    {
        $this->transactions->expects(self::once())
            ->method('paginate')
            ->with(1, 10, self::callback(static fn (TransactionFilter $f): bool => $f->customer === 'alice' && $f->product === 'coke' && $f->from?->format('Y-m-d') === '2026-03-01' && $f->to?->format('Y-m-d') === '2026-03-31'))
            ->willReturn(new Page([self::purchase(userId: 2)], 1, 1, 10));
        $this->transactions->method('totals')->willReturn(['count' => 1, 'revenue' => '13.770']);
        $this->app->actingAs(self::user(1, Role::Admin));

        $body = $this->app->send('GET', '/admin/transactions', query: ['customer' => 'alice', 'product' => 'coke', 'from' => '2026-03-31', 'to' => '2026-03-01'])->body;

        self::assertStringContainsString('for these filters', $body);
        self::assertStringContainsString('$13.77', $body);
        self::assertStringContainsString('href="/admin/transactions/export?customer=alice&amp;product=coke&amp;from=2026-03-01&amp;to=2026-03-31"', $body, 'export keeps the filters; the reversed range was swapped');
    }

    public function testExportIsACsvDownloadForAdminsOnly(): void
    {
        $this->transactions->expects(self::once())->method('each')->willReturn([
            new Transaction(7, 2, 2, 2, '6.885', '13.770', '2026-03-01 10:00:00', '=cmd', 'Buyer, Jr.', 'buyer@example.com'),
        ]);

        $this->app->actingAs(self::user(2));
        self::assertSame(403, $this->app->send('GET', '/admin/transactions/export')->status);

        $this->app->actingAs(self::user(1, Role::Admin));
        $response = $this->app->send('GET', '/admin/transactions/export', query: ['product' => 'cmd']);

        self::assertSame(200, $response->status);
        self::assertSame('text/csv; charset=utf-8', $response->headers['Content-Type']);
        self::assertMatchesRegularExpression('/^attachment; filename="transactions-\d{4}-\d{2}-\d{2}\.csv"$/', $response->headers['Content-Disposition']);
        self::assertSame(
            "\"Purchase\",\"Date (UTC)\",\"Buyer\",\"Email\",\"Product\",\"Quantity\",\"Unit price\",\"Total\"\r\n"
            . "\"7\",\"2026-03-01 10:00:00\",\"Buyer, Jr.\",\"buyer@example.com\",\"'=cmd\",\"2\",\"6.885\",\"13.770\"\r\n",
            $response->body,
        );
    }

    public function testOnlyAdminsSeeAllTransactions(): void
    {
        $this->transactions->expects(self::once())->method('paginate')->willReturn(new Page([self::purchase(userId: 2)], 1, 1, 10));
        $this->transactions->method('totals')->willReturn(['count' => 1, 'revenue' => '13.770']);

        $this->app->actingAs(self::user(2));
        self::assertSame(403, $this->app->send('GET', '/admin/transactions')->status);

        $this->app->actingAs(self::user(1, Role::Admin));
        self::assertSame(200, $this->app->send('GET', '/admin/transactions')->status);
    }

    /**
     * A user with the given id and role.
     * @return User
     */
    private static function user(int $id, Role $role = Role::User): User
    {
        return new User($id, "User {$id}", "user{$id}@example.com", 'hash', $role);
    }

    /**
     * A purchase of 2 Pepsi by the given user.
     * @return Transaction
     */
    private static function purchase(int $userId): Transaction
    {
        return new Transaction(7, $userId, 2, 2, '6.885', '13.770', '2026-01-01 10:00:00', 'Pepsi', 'Buyer', 'buyer@example.com');
    }
}
