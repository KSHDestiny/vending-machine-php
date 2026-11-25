<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;

/*
 * The web login form: wrong passwords and refused tries redirect back to GET /login (so a
 * reload never resends the password); the server counts failures in the session, refuses
 * the 6th try for 30 seconds, and passes the remaining seconds to app.js for its countdown.
 */
final class AuthControllerTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn(new User(2, 'Buyer', 'buyer@example.com', password_hash('Right#Pass1', PASSWORD_DEFAULT), Role::User));
        $this->app = new Application(['users' => $users]);
    }

    public function testAMissingFieldDoesNotCountAsAFailedLogin(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->app->submit('POST', '/login', ['email' => 'buyer@example.com', 'password' => '']);
            self::assertStringContainsString('Password is required.', $this->app->follow($response)->body);
        }

        self::assertSame(303, $this->app->submit('POST', '/login', ['email' => 'buyer@example.com', 'password' => 'Right#Pass1'])->status);
    }

    public function testAWrongPasswordRedirectsBackSoReloadingNeverResendsTheForm(): void
    {
        $response = $this->app->submit('POST', '/login', ['email' => 'buyer@example.com', 'password' => 'Wrong#Pass1']);
        $page = $this->app->send('GET', '/login')->body;

        self::assertSame(303, $response->status);
        self::assertSame('/login', $response->headers['Location']);
        self::assertStringContainsString('Invalid email or password.', $page);
        self::assertStringContainsString('value="buyer@example.com"', $page, 'the email is kept, never the password');
        self::assertStringNotContainsString('Invalid email or password.', $this->app->send('GET', '/login')->body, 'the message shows once');
    }

    public function testTheServerRefusesTheSixthTryForThirtySecondsEvenWithTheRightPassword(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            self::assertSame('/login', $this->app->submit('POST', '/login', ['email' => 'buyer@example.com', 'password' => 'Wrong#Pass1'])->headers['Location'] ?? null, "failure {$i}");
        }
        $cooling = $this->app->send('GET', '/login')->body;
        $sixth = $this->app->submit('POST', '/login', ['email' => 'buyer@example.com', 'password' => 'Right#Pass1']);

        self::assertSame('Too many failed logins. Try again in 30 seconds.', trim(strip_tags((string) preg_replace('#.*<p[^>]*data-login-cooldown>(.*?)</p>.*#s', '$1', $cooling))), 'one message, with the seconds');
        self::assertSame(1, substr_count($cooling, 'Try again in'), 'no second, separate countdown line');
        self::assertStringContainsString('data-login-retry-after="30"', $cooling, 'the browser countdown uses the server\'s time');
        self::assertSame('/login', $sixth->headers['Location'] ?? null, 'refused by the server: back to the form, not signed in');
        self::assertStringContainsString('data-login-retry-after=', $this->app->send('GET', '/login')->body);
        self::assertSame('/login', $this->app->send('GET', '/products')->headers['Location'] ?? null, 'still a guest');
    }

    public function testRegistrationErrorsRedirectBackKeepingNameAndEmailButNeverThePassword(): void
    {
        $response = $this->app->submit('POST', '/register', ['name' => 'Alice', 'email' => 'not-an-email', 'password' => 'Secret#Pass9', 'password_confirmation' => 'Secret#Pass9']);
        $page = $this->app->follow($response)->body;

        self::assertSame('/register', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Email must be a valid email address.', $page);
        self::assertStringContainsString('value="Alice"', $page);
        self::assertStringNotContainsString('Secret#Pass9', $page);
        self::assertStringNotContainsString('Secret#Pass9', (string) json_encode($this->app->session->values));
    }

    public function testTheRightPasswordSignsIn(): void
    {
        $response = $this->app->submit('POST', '/login', ['email' => 'buyer@example.com', 'password' => 'Right#Pass1']);

        self::assertSame(303, $response->status);
        self::assertSame('/products', $response->headers['Location']);
    }
}
