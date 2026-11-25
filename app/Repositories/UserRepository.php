<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\EmailTakenException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use LogicException;
use PDOException;

final class UserRepository implements UserRepositoryInterface
{
    /** @var int MySQL's error for a duplicate value in a unique key. */
    private const DUPLICATE_KEY = 1062;

    public function __construct(private readonly Database $database)
    {
    }

    public function find(int $id): ?User
    {
        $row = $this->database->table('users')->where('id', $id)->first();

        return $row === null ? null : self::toUser($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->database->table('users')->where('email', mb_strtolower($email))->first();

        return $row === null ? null : self::toUser($row);
    }

    public function create(string $name, string $email, string $passwordHash, Role $role): User
    {
        try {
            $id = $this->database->table('users')->insert([
                'name' => $name,
                'email' => mb_strtolower($email),
                'password' => $passwordHash,
                'role' => $role->value,
            ]);
        } catch (PDOException $e) {
            // The unique key catches a second registration that passed the email check at the same time.
            throw ($e->errorInfo[1] ?? null) === self::DUPLICATE_KEY ? new EmailTakenException() : $e;
        }

        return $this->find($id) ?? throw new LogicException('Created user not found');
    }

    public function updateName(int $id, string $name): void
    {
        $this->database->table('users')->where('id', $id)->update(['name' => $name]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->database->table('users')->where('id', $id)->update(['password' => $passwordHash]);
    }

    public function paginateForAdmin(int $page, int $perPage, string $search, ?Role $role, ?UserStatus $status): Page
    {
        $query = $this->database->table('users')->whereContainsAny(['name', 'email'], $search);
        if ($role !== null) {
            $query->where('role', $role->value);
        }
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->orderBy('id', 'desc')->paginate($page, $perPage)->map(self::toUser(...));
    }

    public function updateRole(int $id, Role $role): void
    {
        $this->database->table('users')->where('id', $id)->update(['role' => $role->value]);
    }

    public function updateStatus(int $id, UserStatus $status): void
    {
        $this->database->table('users')->where('id', $id)->update([
            'status' => $status->value,
            'deactivated_at' => $status === UserStatus::Inactive ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }

    public function lockActiveAdminIds(): array
    {
        $rows = $this->database->table('users')
            ->select('id')
            ->where('role', Role::Admin->value)
            ->where('status', UserStatus::Active->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public function countActiveAdmins(): int
    {
        return $this->database->table('users')->where('role', Role::Admin->value)->where('status', UserStatus::Active->value)->count();
    }

    /**
     * The user for a `users` row.
     * @param array<string, mixed> $row
     * @return User
     */
    private static function toUser(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password'],
            Role::from((string) $row['role']),
            UserStatus::from((string) ($row['status'] ?? UserStatus::Active->value)),
        );
    }
}
