<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testRegistrationCreatesACustomerWithAHashedPasswordEvenIfAdminIsRequested(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects(self::once())
            ->method('create')
            ->with('Alice', 'alice@example.com', self::callback(static fn (string $hash): bool => password_verify('StrongPass#123', $hash)), Role::User)
            ->willReturn(new User(3, 'Alice', 'alice@example.com', 'hash', Role::User));

        (new AuthService($users))->register(['name' => ' Alice ', 'email' => 'Alice@Example.com', 'password' => 'StrongPass#123', 'password_confirmation' => 'StrongPass#123', 'role' => 'admin']);
    }

    public function testEightCharacterStrongPasswordIsAccepted(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects(self::once())
            ->method('create')
            ->with('Alice', 'alice@example.com', self::callback(static fn (string $hash): bool => password_verify('Abcdef1!', $hash)), Role::User)
            ->willReturn(new User(3, 'Alice', 'alice@example.com', 'hash', Role::User));

        (new AuthService($users))->register(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'Abcdef1!', 'password_confirmation' => 'Abcdef1!']);
    }

    public function testRegistrationRejectsWeakAndBcryptTruncatedPasswords(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('create');
        $service = new AuthService($users);
        foreach (['secret123', 'Password12345', str_repeat('é', 37) . 'Aa1!'] as $password) {
            try {
                $service->register([
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                    'password' => $password,
                    'password_confirmation' => $password,
                ]);
                self::fail('Password should have been rejected');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('password', $e->errors);
            }
        }
    }

    public function testRegistrationRejectsTakenEmailShortPasswordAndMismatch(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturnCallback(static fn (string $email): ?User => $email === 'admin@example.com'
            ? new User(1, 'Admin', 'admin@example.com', 'hash', Role::Admin)
            : null);
        $service = new AuthService($users);

        $errors = static function (array $input) use ($service): array {
            try {
                $service->register($input);
            } catch (ValidationException $e) {
                return $e->errors;
            }

            return [];
        };

        self::assertSame(['email' => 'This email is already registered.'], $errors(['name' => 'A', 'email' => 'Admin@example.com', 'password' => 'StrongPass#123', 'password_confirmation' => 'StrongPass#123']));
        self::assertArrayHasKey('password', $errors(['name' => 'A', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short']));
        self::assertArrayHasKey('password_confirmation', $errors(['name' => 'A', 'email' => 'a@example.com', 'password' => 'StrongPass#123', 'password_confirmation' => 'other123']));
        self::assertSame(['name', 'email', 'password', 'password_confirmation'], array_keys($errors([])));
    }

    public function testWrongPasswordAndUnknownEmailFailTheSameWay(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturnCallback(static fn (string $email): ?User => $email === 'buyer@example.com'
            ? new User(2, 'Buyer', 'buyer@example.com', password_hash('secret123', PASSWORD_DEFAULT), Role::User)
            : null);
        $service = new AuthService($users);

        self::assertSame(2, $service->attempt('Buyer@example.com', 'secret123')->id);
        foreach ([['buyer@example.com', 'wrong-password'], ['nobody@example.com', 'secret123']] as [$email, $password]) {
            try {
                $service->attempt($email, $password);
                self::fail('Login should have failed');
            } catch (AuthenticationException $e) {
                self::assertSame('Invalid email or password.', $e->getMessage());
            }
        }
    }

    public function testDeactivatedAccountCannotLogInAndFailsLikeAWrongPassword(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(new User(2, 'Buyer', 'buyer@example.com', password_hash('secret123', PASSWORD_DEFAULT), Role::User, UserStatus::Inactive));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid email or password.');

        (new AuthService($users))->attempt('buyer@example.com', 'secret123');
    }

    public function testDeactivatedAccountIsNotSignedInAnyMore(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturnCallback(static fn (int $id): User => new User($id, 'U', "u{$id}@example.com", 'hash', Role::User, $id === 2 ? UserStatus::Inactive : UserStatus::Active));
        $service = new AuthService($users);

        self::assertNull($service->find(2), 'an existing session or token stops working');
        self::assertSame(3, $service->find(3)?->id);
    }
}
