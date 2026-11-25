<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\Database;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\ValidationException;
use App\Repositories\SessionRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Services\UserManagementService;
use PDOException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ImmediateTransactions;

/*
 * User management against the real test database: search and filters in SQL,
 * deactivation timestamps, and the last active admin kept even when two admins
 * act at the same time (the admin rows are locked).
 */
final class UserManagementTest extends DatabaseTestCase
{
    public function testSearchMatchesNameOrEmailAndFiltersByRoleAndStatus(): void
    {
        $users = new UserRepository($this->database);
        $this->createUser(Role::Admin, 'boss@example.com');
        $alice = $this->createUser(Role::User, 'alice@example.com');
        $this->createUser(Role::User, 'bob@shop.test');
        $users->updateName($alice->id, 'Alice Shop');
        $users->updateStatus($alice->id, UserStatus::Inactive);

        $emails = static fn (array $found): array => array_map(static fn ($user): string => $user->email, $found);

        self::assertSame(['bob@shop.test', 'alice@example.com'], $emails($users->paginateForAdmin(1, 10, 'shop', null, null)->items), 'name or email, newest first');
        self::assertSame(['alice@example.com'], $emails($users->paginateForAdmin(1, 10, '', Role::User, UserStatus::Inactive)->items));
        self::assertSame(['boss@example.com'], $emails($users->paginateForAdmin(1, 10, '', Role::Admin, null)->items));
        self::assertSame(3, $users->paginateForAdmin(1, 2, '', null, null)->total);
    }

    public function testDeactivationIsTimestampedAndReactivationClearsIt(): void
    {
        $users = new UserRepository($this->database);
        $buyer = $this->createUser();

        $users->updateStatus($buyer->id, UserStatus::Inactive);
        self::assertSame(UserStatus::Inactive, $users->find($buyer->id)?->status);
        self::assertNotNull($this->deactivatedAt($buyer->id));

        $users->updateStatus($buyer->id, UserStatus::Active);
        self::assertSame(UserStatus::Active, $users->find($buyer->id)?->status);
        self::assertNull($this->deactivatedAt($buyer->id));

        $admin = $this->createUser(Role::Admin, 'boss@example.com');
        self::assertSame(1, $users->countActiveAdmins());
        $users->updateStatus($admin->id, UserStatus::Inactive);
        self::assertSame(0, $users->countActiveAdmins(), 'inactive admins do not count');
    }

    public function testTheLastActiveAdminSurvivesTwoAdminsDemotingEachOther(): void
    {
        $first = $this->createUser(Role::Admin, 'first@example.com');
        $second = $this->createUser(Role::Admin, 'second@example.com');
        $service = fn (Database $database): UserManagementService => new UserManagementService(new UserRepository($database), new TransactionRepository($database), new SessionRepository($database), $database);

        // Two connections: the first demotes the second inside an open transaction, so its
        // lock is held while the second tries to demote the first.
        $other = Database::fromConfig(self::config());
        $other->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 2');
        $this->database->pdo()->beginTransaction();
        (new UserManagementService(new UserRepository($this->database), new TransactionRepository($this->database), new SessionRepository($this->database), new ImmediateTransactions()))
            ->changeRole($first, $second->id, 'user');

        try {
            $service($other)->changeRole($second, $first->id, 'user');
            self::fail('The second demotion should have waited for the lock');
        } catch (PDOException $e) {
            self::assertSame(1205, $e->errorInfo[1] ?? null, 'lock wait timeout while the first change is open');
        }
        $this->database->pdo()->commit();

        // After the first change commits, the second is refused: only one active admin is left.
        try {
            $service($other)->changeRole($second, $first->id, 'user');
            self::fail('Demoting the last active admin should be refused');
        } catch (ValidationException $e) {
            self::assertSame(['role' => 'The last active admin cannot be demoted.'], $e->errors);
        }
        self::assertSame(Role::Admin, (new UserRepository($this->database))->find($first->id)?->role);
    }

    /**
     * The user's deactivated_at as stored, or null.
     * @return string|null
     */
    private function deactivatedAt(int $id): ?string
    {
        $row = $this->database->table('users')->where('id', $id)->first();

        return isset($row['deactivated_at']) ? (string) $row['deactivated_at'] : null;
    }
}
