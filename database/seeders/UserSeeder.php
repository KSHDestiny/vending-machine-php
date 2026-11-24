<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Exceptions\ValidationException;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Validator;
use InvalidArgumentException;

/*
 * The initial Admin (required) and a demo customer (optional). Passwords come from
 * the environment, never from the repository; existing accounts are left alone.
 */
final class UserSeeder
{
    /** @var string The initial admin account's email. */
    public const ADMIN_EMAIL = 'admin@example.com';

    /** @var string The demo customer account's email. */
    public const USER_EMAIL = 'user@example.com';

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * The emails of the accounts created by this run.
     * @return list<string>
     */
    public function run(#[\SensitiveParameter] string $adminPassword, #[\SensitiveParameter] string $userPassword): array
    {
        $created = [];
        if ($this->users->findByEmail(self::ADMIN_EMAIL) === null) {
            $this->users->create('Admin', self::ADMIN_EMAIL, self::hash($adminPassword, 'SEED_ADMIN_PASSWORD'), Role::Admin);
            $created[] = self::ADMIN_EMAIL;
        }

        if ($userPassword !== '' && $this->users->findByEmail(self::USER_EMAIL) === null) {
            $this->users->create('Demo User', self::USER_EMAIL, self::hash($userPassword, 'SEED_USER_PASSWORD'), Role::User);
            $created[] = self::USER_EMAIL;
        }

        return $created;
    }

    /**
     * The password_hash() of a password meeting the same policy as registration.
     * @return string
     */
    private static function hash(#[\SensitiveParameter] string $password, string $variable): string
    {
        try {
            Validator::check(['password' => $password], [
                'password' => ['label' => $variable, 'rules' => Validator::PASSWORD_RULES],
            ]);
        } catch (ValidationException $e) {
            throw new InvalidArgumentException($e->errors['password'], previous: $e);
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }
}
