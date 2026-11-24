<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Role;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AccountService;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    public function testNameChangeKeepsEmailAndRole(): void
    {
        $user = self::user();
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('updateName')->with(2, 'New Name');
        $users->method('find')->willReturn(new User(2, 'New Name', 'buyer@example.com', $user->passwordHash, Role::User));

        self::assertSame('New Name', (new AccountService($users))->updateName($user, ['name' => '  New Name ', 'role' => 'admin', 'email' => 'x@example.com'])->name);
    }

    public function testEmptyNameIsRejected(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('updateName');

        $this->expectException(ValidationException::class);

        (new AccountService($users))->updateName(self::user(), ['name' => '   ']);
    }

    public function testPasswordChangesOnlyWithTheCurrentPassword(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())
            ->method('updatePassword')
            ->with(2, self::callback(static fn (string $hash): bool => password_verify('Abcdef1!', $hash)));

        (new AccountService($users))->changePassword(self::user(), ['current_password' => 'secret123', 'password' => 'Abcdef1!', 'password_confirmation' => 'Abcdef1!']);
    }

    public function testWrongCurrentPasswordShortOrMismatchedNewPasswordIsRejected(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('updatePassword');
        $service = new AccountService($users);

        $errors = static function (array $input) use ($service): array {
            try {
                $service->changePassword(self::user(), $input);
            } catch (ValidationException $e) {
                return $e->errors;
            }

            return [];
        };

        self::assertSame(['current_password' => 'The current password is incorrect.'], $errors(['current_password' => 'wrong', 'password' => 'NewStrong#123', 'password_confirmation' => 'NewStrong#123']));
        self::assertArrayHasKey('password', $errors(['current_password' => 'secret123', 'password' => 'short', 'password_confirmation' => 'short']));
        self::assertArrayHasKey('password', $errors(['current_password' => 'secret123', 'password' => 'Password12345', 'password_confirmation' => 'Password12345']));
        self::assertArrayHasKey('password_confirmation', $errors(['current_password' => 'secret123', 'password' => 'NewStrong#123', 'password_confirmation' => 'other-pass']));
        $overlong = str_repeat('é', 37) . 'Aa1!';
        self::assertArrayHasKey('password', $errors(['current_password' => 'secret123', 'password' => $overlong, 'password_confirmation' => $overlong]));
    }

    /**
     * A customer whose password is "secret123".
     * @return User
     */
    private static function user(): User
    {
        static $hash = null;
        $hash ??= password_hash('secret123', PASSWORD_DEFAULT);

        return new User(2, 'Buyer', 'buyer@example.com', $hash, Role::User);
    }
}
