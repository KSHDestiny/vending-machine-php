<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Enums\Role;
use App\Exceptions\EmailTakenException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
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

    /**
     * The user for a `users` row.
     * @param array<string, mixed> $row
     * @return User
     */
    private static function toUser(array $row): User
    {
        return new User((int) $row['id'], (string) $row['name'], (string) $row['email'], (string) $row['password'], Role::from((string) $row['role']));
    }
}
