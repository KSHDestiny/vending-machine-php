<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Session\SessionStore;

/*
 * An in-memory session for tests: PHP's native session can't start once PHPUnit
 * has printed output. Kept between requests, like a browser's session cookie.
 */
final class ArraySessionStore implements SessionStore
{
    /** @var array<string, mixed> The session values. */
    public array $values = [];

    /** @var int How many times the session id was regenerated. */
    public int $regenerated = 0;

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerated++;
    }

    public function destroy(): void
    {
        $this->values = [];
    }
}
