<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\AuthenticationException;
use App\Exceptions\EmailTakenException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Validator;

/*
 * Registration and credential checks, shared by the web (sessions) and the API (JWT).
 * Registration always creates a customer; the Admin comes only from the seeder.
 */
final class AuthService
{
    /** @var string Checked when the email is unknown, so both failures take as long. */
    private const DUMMY_HASH = '$2y$12$0Yj3rCbY1qRwietVRIxAHOlCjb5eoYdboGizDQFiOEWCdN7VZm.0G';

    /** @var array<string, array{label: string, rules: list<string>}> The registration fields. */
    public const REGISTER_RULES = [
        'name' => ['label' => 'Name', 'rules' => ['required', 'max:100']],
        'email' => ['label' => 'Email', 'rules' => ['required', 'email', 'max:255']],
        'password' => ['label' => 'Password', 'rules' => Validator::PASSWORD_RULES],
        'password_confirmation' => ['label' => 'Password confirmation', 'rules' => ['required', 'same:password']],
    ];

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * The new customer account; throws ValidationException for invalid input or a taken email.
     * @param array<string, string> $input name, email, password, password_confirmation
     * @return User
     */
    public function register(array $input): User
    {
        $input = ['name' => trim($input['name'] ?? ''), 'email' => mb_strtolower(trim($input['email'] ?? ''))] + $input;
        Validator::check($input, self::REGISTER_RULES);

        if ($this->users->findByEmail($input['email']) !== null) {
            throw new EmailTakenException();
        }

        // Role is fixed here, so a request can never ask for an admin account. A simultaneous
        // registration with the same email makes create() throw EmailTakenException too.
        return $this->users->create($input['name'], $input['email'], password_hash($input['password'], PASSWORD_DEFAULT), Role::User);
    }

    /**
     * The user with these credentials; ValidationException if a field is empty, AuthenticationException if wrong.
     * @return User
     */
    public function attempt(string $email, #[\SensitiveParameter] string $password): User
    {
        Validator::check(['email' => trim($email), 'password' => $password], [
            'email' => ['label' => 'Email', 'rules' => ['required', 'email']],
            'password' => ['label' => 'Password', 'rules' => ['required']],
        ]);

        $user = $this->users->findByEmail(mb_strtolower(trim($email)));
        $valid = password_verify($password, $user->passwordHash ?? self::DUMMY_HASH);
        if (!$valid || $user === null) {
            throw new AuthenticationException('Invalid email or password.');
        }

        return $user;
    }

    /**
     * The user with this id, or null (e.g. deleted since they signed in).
     * @return User|null
     */
    public function find(int $id): ?User
    {
        return $this->users->find($id);
    }
}
