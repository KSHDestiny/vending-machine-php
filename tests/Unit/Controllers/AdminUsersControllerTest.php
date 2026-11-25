<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;
use Tests\Support\ImmediateTransactions;

/*
 * Admin user management through the real routes, middleware and views, with
 * mocked repositories: admins only, CSRF on every change, filters passed through.
 */
final class AdminUsersControllerTest extends TestCase
{
    /** @var UserRepositoryInterface&MockObject */
    private UserRepositoryInterface $users;

    private Application $app;

    /** @var array<string, object> The repositories given to the app; one test swaps the sessions for a mock. */
    private array $overrides;

    /** @var array<int, User> The users find() returns, by id. */
    private array $accounts;

    protected function setUp(): void
    {
        $this->accounts = [1 => self::user(1, Role::Admin), 2 => self::user(2, Role::User), 3 => self::user(3, Role::Admin), 4 => new User(4, 'Admin', 'admin@example.com', 'hash', Role::Admin)];
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->users->method('find')->willReturnCallback(fn (int $id): ?User => $this->accounts[$id] ?? null);
        $this->users->method('lockActiveAdminIds')->willReturn([1, 3]);
        $this->users->method('countActiveAdmins')->willReturn(2);
        $transactions = $this->createStub(TransactionRepositoryInterface::class);
        $transactions->method('summary')->willReturn(['count' => 2, 'items_sold' => 5, 'revenue' => '34.425']);
        $transactions->method('paginateForUser')->willReturn(new Page([], 0, 1, 5));

        $this->overrides = ['users' => $this->users, 'transactions' => $transactions, 'sessions' => $this->createStub(SessionRepositoryInterface::class), 'transactionManager' => new ImmediateTransactions()];
        $this->app = new Application($this->overrides);
        $this->app->actingAs($this->accounts[1]);
    }

    public function testAdminListsUsersWithSearchFiltersAndPaging(): void
    {
        $this->users->expects(self::once())
            ->method('paginateForAdmin')
            ->with(2, 10, 'ali', Role::User, UserStatus::Inactive)
            ->willReturn(new Page([self::user(2, Role::User, UserStatus::Inactive)], 11, 2, 10));

        $response = $this->app->send('GET', '/admin/users', query: ['q' => ' ali ', 'role' => 'user', 'status' => 'inactive', 'page' => '2']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('user2@example.com', $response->body);
        self::assertStringContainsString('Inactive', $response->body);
    }

    public function testUnknownFiltersAreIgnored(): void
    {
        $this->users->expects(self::once())->method('paginateForAdmin')->with(1, 10, '', null, null)->willReturn(new Page([], 0, 1, 10));

        self::assertSame(200, $this->app->send('GET', '/admin/users', query: ['role' => 'root', 'status' => 'deleted'])->status);
    }

    public function testUserPageShowsTheirPurchaseSummary(): void
    {
        $this->users->expects(self::never())->method('updateRole');
        $this->users->expects(self::never())->method('updateStatus');

        $response = $this->app->send('GET', '/admin/users/2');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('user2@example.com', $response->body);
        self::assertStringContainsString('$34.425', $response->body);
        self::assertSame(404, $this->app->send('GET', '/admin/users/99')->status);
    }

    public function testAdminChangesARoleThroughTheForm(): void
    {
        $this->users->expects(self::once())->method('updateRole')->with(2, Role::Admin);

        $response = $this->app->submit('PATCH', '/admin/users/2/role', ['role' => 'admin']);

        self::assertSame(303, $response->status);
        self::assertSame('/admin/users/2', $response->headers['Location']);
    }

    public function testAdminDeactivatesAnAccountThroughTheForm(): void
    {
        $this->users->expects(self::once())->method('updateStatus')->with(2, UserStatus::Inactive);
        $sessions = $this->createMock(SessionRepositoryInterface::class);
        $sessions->expects(self::once())->method('deleteForUser')->with(2);
        $this->app = new Application(['sessions' => $sessions] + $this->overrides);
        $this->app->actingAs($this->accounts[1]);

        self::assertSame(303, $this->app->submit('PATCH', '/admin/users/2/status', ['status' => 'inactive'])->status);
    }

    public function testSavingAnUnchangedRoleSaysSoInsteadOfClaimingAChange(): void
    {
        $this->users->expects(self::never())->method('updateRole');

        $this->app->submit('PATCH', '/admin/users/2/role', ['role' => 'user']);
        $page = $this->app->send('GET', '/admin/users/2')->body;

        self::assertStringContainsString('No change: User 2 is already Customer.', $page);
        self::assertStringNotContainsString('is now Customer', $page);
    }

    public function testThePrimaryAdminsRoleFormIsDisabled(): void
    {
        $this->users->expects(self::never())->method('updateRole');

        $page = $this->app->send('GET', '/admin/users/4')->body;

        self::assertMatchesRegularExpression('/<fieldset disabled>\\s*<legend class="sr-only">Role<\/legend>/', $page);
    }

    public function testThePrimaryAdminCannotBeDeactivatedAndThePageSaysWhy(): void
    {
        $this->users->expects(self::never())->method('updateStatus');

        $page = $this->app->send('GET', '/admin/users/4')->body;
        $response = $this->app->submit('PATCH', '/admin/users/4/status', ['status' => 'inactive']);

        self::assertMatchesRegularExpression('/<button type="submit"[^>]*disabled>Deactivate account<\/button>/', $page, 'the button is disabled on the page');
        self::assertStringContainsString('admin@example.com is the primary admin and cannot be deactivated.', $page);
        self::assertSame('/admin/users/4', $response->headers['Location'] ?? null, 'and the server refuses it anyway');
        self::assertStringContainsString('admin@example.com is the primary admin and cannot be deactivated.', $this->app->follow($response)->body);
    }

    public function testRejectedChangeShowsTheReasonOnThePage(): void
    {
        $this->users->expects(self::never())->method('updateStatus');

        $response = $this->app->submit('PATCH', '/admin/users/1/status', ['status' => 'inactive']);

        self::assertSame('/admin/users/1', $response->headers['Location'] ?? null, 'back to the page, so a reload never resends');
        self::assertStringContainsString('You cannot deactivate your own account.', $this->app->follow($response)->body);
    }

    public function testThePrimaryAdminCannotBeChangedToCustomerByAnotherAdmin(): void
    {
        $this->users->expects(self::never())->method('updateRole');

        $response = $this->app->submit('PATCH', '/admin/users/4/role', ['role' => 'user']);

        self::assertSame('/admin/users/4', $response->headers['Location'] ?? null);
        self::assertStringContainsString('admin@example.com is the primary admin and cannot be changed to Customer.', $this->app->follow($response)->body);
    }

    public function testChangesNeedTheCsrfToken(): void
    {
        $this->users->expects(self::never())->method('updateRole');

        self::assertSame(403, $this->app->send('PATCH', '/admin/users/2/role', ['role' => 'admin'])->status);
    }

    public function testCustomersCannotManageUsers(): void
    {
        $this->app->actingAs($this->accounts[2]);
        $this->users->expects(self::never())->method('paginateForAdmin');
        $this->users->expects(self::never())->method('updateRole');

        self::assertSame(403, $this->app->send('GET', '/admin/users')->status);
        self::assertSame(403, $this->app->submit('PATCH', '/admin/users/2/role', ['role' => 'admin'])->status);
    }

    /**
     * A user with this id, role and status.
     * @return User
     */
    private static function user(int $id, Role $role, UserStatus $status = UserStatus::Active): User
    {
        return new User($id, "User {$id}", "user{$id}@example.com", 'hash', $role, $status);
    }
}
