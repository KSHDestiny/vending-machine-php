<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Role;
use App\Models\User;

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
}
