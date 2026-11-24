<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Enums\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
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
        $this->transactions->method('find')->willReturn(self::purchase(userId: 2));

        self::assertSame(404, $this->app->send('GET', '/transactions/7')->status);
    }

    public function testBuyerAndAdminCanOpenThePurchaseConfirmation(): void
    {
        $this->transactions->method('find')->willReturn(self::purchase(userId: 2));

        $this->app->actingAs(self::user(2));
        self::assertStringContainsString('$13.77', $this->app->send('GET', '/transactions/7')->body);

        $this->app->actingAs(self::user(1, Role::Admin));
        self::assertSame(200, $this->app->send('GET', '/transactions/7')->status);
    }

    public function testOnlyAdminsSeeAllTransactions(): void
    {
        $this->transactions->method('paginate')->willReturn(new Page([self::purchase(userId: 2)], 1, 1, 10));

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
