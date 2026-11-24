<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use UnexpectedValueException;

/*
 * API tokens: HS256 JWTs signed with JWT_SECRET. A token names the user (sub) and
 * expires; the role is never trusted from the token but reloaded from the database.
 */
final class JwtService
{
    /** @var string The only accepted signing algorithm. */
    private const ALGORITHM = 'HS256';

    /** @var string The iss claim of every token this app issues. */
    private const ISSUER = 'vending-machine';

    public function __construct(
        #[\SensitiveParameter] private readonly string $secret,
        private readonly int $ttl = 3600,
    ) {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT_SECRET must be at least 32 bytes');
        }
    }

    /**
     * A signed token for the user, valid for ttl() seconds.
     * @return string
     */
    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode(['iss' => self::ISSUER, 'sub' => (string) $user->id, 'iat' => $now, 'exp' => $now + $this->ttl], $this->secret, self::ALGORITHM);
    }

    /**
     * The user id in a valid, unexpired token from this app, or null.
     * @return int|null
     */
    public function userId(string $token): ?int
    {
        try {
            // Checks the signature, the HS256 algorithm, and exp/iat/nbf when present.
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (UnexpectedValueException | DomainException | InvalidArgumentException) {
            return null;
        }

        $sub = $claims->sub ?? null;
        $valid = ($claims->iss ?? null) === self::ISSUER && is_numeric($claims->exp ?? null) && is_string($sub) && ctype_digit($sub);

        return $valid ? (int) $sub : null;
    }

    /**
     * Seconds a token stays valid.
     * @return int
     */
    public function ttl(): int
    {
        return $this->ttl;
    }
}
