<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;

/*
 * An account, with what its role allows: admins manage products and stock,
 * customers buy them.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        /** @var string The password_hash() value, never the password. */
        public readonly string $passwordHash,
        public readonly Role $role,
    ) {
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
