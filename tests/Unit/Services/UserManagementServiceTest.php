<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserManagementService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImmediateTransactions;

/*
 * Admin user management rules: no changing yourself, never lose the last active
 * admin, only valid roles and statuses, and every change inside a transaction.
 */
final class UserManagementServiceTest extends TestCase
{
    /** @var UserRepositoryInterface&MockObject */
    private UserRepositoryInterface $users;

    private ImmediateTransactions $database;

    private UserManagementService $service;

    protected function setUp(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->database = new ImmediateTransactions();
        $this->service = new UserManagementService($this->users, $this->createStub(TransactionRepositoryInterface::class), $this->createStub(SessionRepositoryInterface::class), $this->database);
    }

    public function testAdminPromotesACustomer(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1]);
        $this->users->method('find')->willReturnOnConsecutiveCalls(self::customer(), self::user(2, Role::Admin));
        $this->users->expects(self::once())->method('updateRole')->with(2, Role::Admin);

        $result = $this->service->changeRole(self::admin(), 2, 'admin');

        self::assertSame(Role::Admin, $result['user']->role);
        self::assertTrue($result['changed']);
        self::assertSame(1, $this->database->count);
    }

    public function testAdminCannotChangeTheirOwnRole(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1, 3]);
        $this->users->method('find')->willReturn(self::admin());
        $this->users->expects(self::never())->method('updateRole');

        $this->assertRejected('role', 'You cannot change your own role.', fn () => $this->service->changeRole(self::admin(), 1, 'user'));
    }

    public function testTheLastActiveAdminCannotBeDemoted(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([3]);
        $this->users->method('find')->willReturn(self::user(3, Role::Admin));
        $this->users->expects(self::never())->method('updateRole');

        $this->assertRejected('role', 'The last active admin cannot be demoted.', fn () => $this->service->changeRole(self::admin(), 3, 'user'));
    }

    public function testAnotherAdminCanBeDemotedWhileOneStaysActive(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1, 3]);
        $this->users->method('find')->willReturnOnConsecutiveCalls(self::user(3, Role::Admin), self::user(3, Role::User));
        $this->users->expects(self::once())->method('updateRole')->with(3, Role::User);

        self::assertSame(Role::User, $this->service->changeRole(self::admin(), 3, 'user')['user']->role);
    }

    public function testThePrimaryAdminCanNeverBeChangedToCustomer(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1, 3, 4]);
        $this->users->method('find')->willReturn(new User(4, 'Admin', 'admin@example.com', 'hash', Role::Admin));
        $this->users->expects(self::never())->method('updateRole');

        $this->assertRejected('role', 'admin@example.com is the primary admin and cannot be changed to Customer.', fn () => $this->service->changeRole(self::user(3, Role::Admin), 4, 'user'));
    }

    public function testSavingTheSameRoleOrStatusChangesNothing(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1]);
        $this->users->method('find')->willReturn(self::customer());
        $this->users->expects(self::never())->method('updateRole');
        $this->users->expects(self::never())->method('updateStatus');

        self::assertFalse($this->service->changeRole(self::admin(), 2, 'user')['changed']);
        self::assertFalse($this->service->changeStatus(self::admin(), 2, 'active')['changed']);
    }

    public function testUnknownRoleIsRejected(): void
    {
        $this->users->expects(self::never())->method('updateRole');

        $this->assertRejected('role', 'Choose Admin or Customer.', fn () => $this->service->changeRole(self::admin(), 2, 'superuser'));
    }

    public function testAdminDeactivatesACustomer(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1]);
        $this->users->method('find')->willReturnOnConsecutiveCalls(self::customer(), self::user(2, Role::User, UserStatus::Inactive));
        $this->users->expects(self::once())->method('updateStatus')->with(2, UserStatus::Inactive);

        self::assertSame(UserStatus::Inactive, $this->service->changeStatus(self::admin(), 2, 'inactive')['user']->status);
    }

    public function testDeactivationEndsTheUsersSessionsButReactivationDoesNot(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1]);
        $this->users->method('find')->willReturnOnConsecutiveCalls(
            self::customer(),
            self::user(2, Role::User, UserStatus::Inactive),
            self::user(2, Role::User, UserStatus::Inactive),
            self::customer(),
        );
        $this->users->expects(self::exactly(2))->method('updateStatus');
        $sessions = $this->createMock(SessionRepositoryInterface::class);
        $sessions->expects(self::once())->method('deleteForUser')->with(2);
        $service = new UserManagementService($this->users, $this->createStub(TransactionRepositoryInterface::class), $sessions, $this->database);

        $service->changeStatus(self::admin(), 2, 'inactive');
        $service->changeStatus(self::admin(), 2, 'active');
    }

    public function testAdminCannotDeactivateThemselves(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1, 3]);
        $this->users->method('find')->willReturn(self::admin());
        $this->users->expects(self::never())->method('updateStatus');

        $this->assertRejected('status', 'You cannot deactivate your own account.', fn () => $this->service->changeStatus(self::admin(), 1, 'inactive'));
    }

    public function testTheLastActiveAdminCannotBeDeactivated(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([3]);
        $this->users->method('find')->willReturn(self::user(3, Role::Admin));
        $this->users->expects(self::never())->method('updateStatus');

        $this->assertRejected('status', 'The last active admin cannot be deactivated.', fn () => $this->service->changeStatus(self::admin(), 3, 'inactive'));
    }

    public function testThePrimaryAdminCanNeverBeDeactivated(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1, 3, 4]);
        $this->users->method('find')->willReturn(new User(4, 'Admin', 'admin@example.com', 'hash', Role::Admin));
        $this->users->expects(self::never())->method('updateStatus');

        $this->assertRejected('status', 'admin@example.com is the primary admin and cannot be deactivated.', fn () => $this->service->changeStatus(self::user(3, Role::Admin), 4, 'inactive'));
    }

    public function testRestrictionsGiveThePagesTheSameReasonsTheServerEnforces(): void
    {
        $this->users->method('countActiveAdmins')->willReturn(2);
        $this->users->expects(self::never())->method('updateRole');
        $primary = new User(4, 'Admin', 'admin@example.com', 'hash', Role::Admin);
        $lastAdmin = self::user(3, Role::Admin);

        self::assertSame(['role' => 'You cannot change your own role.', 'status' => 'You cannot deactivate your own account.'], $this->service->restrictions(self::admin(), self::admin()));
        self::assertSame(['role' => 'admin@example.com is the primary admin and cannot be changed to Customer.', 'status' => 'admin@example.com is the primary admin and cannot be deactivated.'], $this->service->restrictions(self::admin(), $primary));
        self::assertSame(['role' => null, 'status' => null], $this->service->restrictions(self::admin(), $lastAdmin), 'two active admins: either can go');
        self::assertSame(['role' => null, 'status' => null], $this->service->restrictions(self::admin(), self::customer()));
        self::assertSame(['role' => null, 'status' => null], $this->service->restrictions(self::admin(), self::user(2, Role::User, UserStatus::Inactive)), 'reactivating is always allowed');
    }

    public function testRestrictionsProtectTheLastActiveAdmin(): void
    {
        $this->users->method('countActiveAdmins')->willReturn(1);
        $this->users->expects(self::never())->method('updateRole');

        self::assertSame(['role' => 'The last active admin cannot be demoted.', 'status' => 'The last active admin cannot be deactivated.'], $this->service->restrictions(self::admin(), self::user(3, Role::Admin)));
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->users->expects(self::never())->method('updateStatus');

        $this->assertRejected('status', 'Choose Active or Inactive.', fn () => $this->service->changeStatus(self::admin(), 2, 'deleted'));
    }

    public function testChangingAMissingUserIsNotFound(): void
    {
        $this->users->method('lockActiveAdminIds')->willReturn([1]);
        $this->users->method('find')->willReturn(null);
        $this->users->expects(self::never())->method('updateStatus');

        $this->expectException(NotFoundException::class);

        $this->service->changeStatus(self::admin(), 99, 'inactive');
    }

    /**
     * Asserts the action throws a ValidationException with this message for the field.
     * @param callable(): mixed $action
     * @return void
     */
    private function assertRejected(string $field, string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame([$field => $message], $e->errors);
        }
    }

    /**
     * The admin making the changes (id 1).
     * @return User
     */
    private static function admin(): User
    {
        return self::user(1, Role::Admin);
    }

    /**
     * A customer (id 2).
     * @return User
     */
    private static function customer(): User
    {
        return self::user(2, Role::User);
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
