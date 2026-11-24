<?php

declare(strict_types=1);

namespace App\Http\Session;

/*
 * Where session values live: PHP's native session in the app, an array in tests.
 */
interface SessionStore
{
    /**
     * The stored value, or null.
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * Stores a value.
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Removes a value.
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Issues a new session id and keeps the values (against session fixation).
     * @return void
     */
    public function regenerate(): void;

    /**
     * Ends the session: all values and the cookie.
     * @return void
     */
    public function destroy(): void;
}
