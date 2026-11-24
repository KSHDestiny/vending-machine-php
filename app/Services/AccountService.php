<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Validator;
use LogicException;

/*
 * A signed-in user's own account: changing their name and their password. The email
 * and role can't be changed here, so nobody can make themselves an admin.
 */
final class AccountService
{
    /** @var array<string, array{label: string, rules: list<string>}> The password form's fields. */
    public const PASSWORD_RULES = [
        'current_password' => ['label' => 'Current password', 'rules' => ['required']],
        'password' => ['label' => 'New password', 'rules' => Validator::PASSWORD_RULES],
        'password_confirmation' => ['label' => 'Password confirmation', 'rules' => ['required', 'same:password']],
    ];

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * The user with the new name; throws ValidationException if it's empty or too long.
     * @param array<string, string> $input name
     * @return User
     */
    public function updateName(User $user, array $input): User
    {
        $name = trim($input['name'] ?? '');
        Validator::check(['name' => $name], ['name' => ['label' => 'Name', 'rules' => ['required', 'max:100']]]);

        $this->users->updateName($user->id, $name);

        return $this->users->find($user->id) ?? throw new LogicException("User {$user->id} disappeared");
    }

    /**
     * Sets a new password after checking the current one; throws ValidationException otherwise.
     * @param array<string, string> $input current_password, password, password_confirmation
     * @return void
     */
    public function changePassword(User $user, array $input): void
    {
        Validator::check($input, self::PASSWORD_RULES);

        if (!password_verify($input['current_password'], $user->passwordHash)) {
            throw new ValidationException(['current_password' => 'The current password is incorrect.']);
        }

        $this->users->updatePassword($user->id, password_hash($input['password'], PASSWORD_DEFAULT));
    }
}
