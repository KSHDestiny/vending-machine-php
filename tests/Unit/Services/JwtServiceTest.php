<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Role;
use App\Models\User;
use App\Services\JwtService;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    /** @var string A secret long enough for HS256. */
    private const SECRET = 'test-secret-that-is-at-least-32-bytes-long';

    public function testIssuedTokenNamesTheUser(): void
    {
        $jwt = new JwtService(self::SECRET);

        self::assertSame(7, $jwt->userId($jwt->issue(new User(7, 'A', 'a@example.com', 'hash', Role::User))));
    }

    public function testForgedExpiredForeignAndNonExpiringTokensAreRejected(): void
    {
        $jwt = new JwtService(self::SECRET);
        $token = $jwt->issue(new User(7, 'A', 'a@example.com', 'hash', Role::User));
        [$header, , $signature] = explode('.', $token);
        $forgedPayload = rtrim(strtr(base64_encode('{"iss":"vending-machine","sub":"1","exp":9999999999}'), '+/', '-_'), '=');

        self::assertNull((new JwtService(str_repeat('x', 32)))->userId($token));
        self::assertNull($jwt->userId("{$header}.{$forgedPayload}.{$signature}"));
        self::assertNull($jwt->userId('not-a-token'));
        self::assertNull($jwt->userId(JWT::encode(['iss' => 'vending-machine', 'sub' => '7', 'exp' => time() - 1], self::SECRET, 'HS256')));
        self::assertNull($jwt->userId(JWT::encode(['iss' => 'someone-else', 'sub' => '7', 'exp' => time() + 60], self::SECRET, 'HS256')));
        self::assertNull($jwt->userId(JWT::encode(['iss' => 'vending-machine', 'sub' => '7'], self::SECRET, 'HS256')));
    }

    public function testShortSecretIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JwtService('too-short');
    }
}
