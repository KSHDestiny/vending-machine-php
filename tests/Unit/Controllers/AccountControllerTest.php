<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;

/*
 * The account page's forms: errors redirect back to /account (so a reload never resends
 * the form) and show beside the right form; passwords are never kept or shown again.
 */
final class AccountControllerTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $buyer = new User(2, 'Buyer', 'buyer@example.com', password_hash('Right#Pass1', PASSWORD_DEFAULT), Role::User);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturn($buyer);
        $this->app = new Application(['users' => $users, 'sessions' => $this->createStub(SessionRepositoryInterface::class)]);
        $this->app->actingAs($buyer);
    }

    public function testAnEmptyNameRedirectsBackWithTheError(): void
    {
        $response = $this->app->submit('PUT', '/account', ['name' => '   ']);

        self::assertSame('/account', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Name is required.', $this->app->follow($response)->body);
    }

    public function testAWrongCurrentPasswordRedirectsBackWithoutKeepingAnyPassword(): void
    {
        $response = $this->app->submit('PUT', '/account/password', ['current_password' => 'Guess#Pass1', 'password' => 'Brand#New99', 'password_confirmation' => 'Brand#New99']);
        $page = $this->app->follow($response)->body;

        self::assertSame('/account', $response->headers['Location'] ?? null);
        self::assertStringContainsString('The current password is incorrect.', $page);
        self::assertStringNotContainsString('Brand#New99', $page);
        self::assertStringNotContainsString('Guess#Pass1', (string) json_encode($this->app->session->values));
    }
}
