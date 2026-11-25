<?php

declare(strict_types=1);

namespace App\Http\Session;

use SessionHandlerInterface;

/*
 * PHP's native session, started on first use with an HttpOnly, SameSite=Lax cookie
 * (Secure in production), so requests that never touch it don't create one. With a
 * handler (DatabaseSessionHandler in the app), sessions are stored there instead of
 * in files, in strict mode so a cookie with an unknown ID gets a fresh session.
 */
final class NativeSessionStore implements SessionStore
{
    public function __construct(
        private readonly bool $secureCookie,
        private readonly ?SessionHandlerInterface $handler = null,
    ) {
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        $cookie = session_get_cookie_params();
        setcookie(session_name() ?: 'vending_session', '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);
        session_destroy();
    }

    /**
     * Starts the session with secure cookie settings if it isn't running yet.
     * @return void
     */
    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if ($this->handler !== null) {
            ini_set('session.serialize_handler', 'php_serialize');
            ini_set('session.use_strict_mode', '1');
            session_set_save_handler($this->handler, true);
        }
        session_name('vending_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $this->secureCookie, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}
