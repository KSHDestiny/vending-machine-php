<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Page;

interface UserRepositoryInterface
{
    /**
     * The user, or null.
     * @return User|null
     */
    public function find(int $id): ?User;

    /**
     * The user with this email (case-insensitive), or null.
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * The new user; $passwordHash must already be a password_hash() value.
     * Throws EmailTakenException if another account has the email.
     * @return User
     */
    public function create(string $name, string $email, string $passwordHash, Role $role): User;

    /**
     * Replaces only the user's name; email and role cannot change here.
     * @return void
     */
    public function updateName(int $id, string $name): void;

    /**
     * Replaces the user's password; $passwordHash must already be a password_hash() value.
     * @return void
     */
    public function updatePassword(int $id, string $passwordHash): void;

    /**
     * One page of accounts, newest first, whose name or email contains $search, optionally with one role and status.
     * @return Page<User>
     */
    public function paginateForAdmin(int $page, int $perPage, string $search, ?Role $role, ?UserStatus $status): Page;

    /**
     * Replaces the user's role.
     * @return void
     */
    public function updateRole(int $id, Role $role): void;

    /**
     * Sets the status; deactivated_at records when the account was deactivated and is cleared on reactivation.
     * @return void
     */
    public function updateStatus(int $id, UserStatus $status): void;

    /**
     * The ids of active admins, locked until the current transaction ends (SELECT ... FOR UPDATE),
     * so two changes cannot both remove "another" admin at the same time.
     * @return list<int>
     */
    public function lockActiveAdminIds(): array;

    /**
     * How many admins are active (no lock; for showing what is allowed, not for enforcing it).
     * @return int
     */
    public function countActiveAdmins(): int;
}
