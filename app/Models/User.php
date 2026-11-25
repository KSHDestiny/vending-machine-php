<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;

/*
 * An account, with what its role allows: admins manage products and stock,
 * customers buy them. Inactive accounts cannot sign in.
 */
final class User
{
    /** @var string The seeded admin account; it always stays an admin. */
    public const PRIMARY_ADMIN_EMAIL = 'admin@example.com';

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        /** @var string The password_hash() value, never the password. */
        public readonly string $passwordHash,
        public readonly Role $role,
        public readonly UserStatus $status = UserStatus::Active,
    ) {
    }

    /**
     * Whether the account may sign in.
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Whether this is the seeded admin account, which can never become a customer.
     * @return bool
     */
    public function isPrimaryAdmin(): bool
    {
        return $this->email === self::PRIMARY_ADMIN_EMAIL;
    }

    /**
     * Whether the user is an admin.
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Whether the user may add, edit and delete products and see every purchase.
     * @return bool
     */
    public function canManageProducts(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Whether the user may buy products; only customer accounts can.
     * @return bool
     */
    public function canPurchase(): bool
    {
        return $this->role === Role::User;
    }

    /**
     * The fields safe to return from the API (no password hash).
     * @return array{id: int, name: string, email: string, role: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role->value];
    }
}
