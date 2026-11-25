<?php

declare(strict_types=1);

namespace App\Http\Session;

use App\Repositories\Contracts\SessionRepositoryInterface;
use Closure;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/*
 * PHP's session storage in MySQL instead of files, so sign-ins survive restarts and
 * redeploys. Only the SHA-256 of the session ID is stored. A session ends after two
 * hours without a request, and twelve hours after it began however busy it is.
 * Expects session.serialize_handler=php_serialize (set by NativeSessionStore), so the
 * signed-in user's id can be read from the data and stored alongside it.
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** @var Closure(): int The current Unix time; replaceable in tests. */
    private readonly Closure $clock;

    /**
     * @param Closure(): int|null $clock
     */
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly int $idleSeconds = 7_200,
        private readonly int $absoluteSeconds = 43_200,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? time(...);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $now = ($this->clock)();

        return $this->sessions->find(self::hash($id), $now - $this->idleSeconds, $now - $this->absoluteSeconds) ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $this->sessions->save(self::hash($id), self::userId($data), $data, ($this->clock)());

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->sessions->delete(self::hash($id));

        return true;
    }

    public function gc(int $max_lifetime): int
    {
        $now = ($this->clock)();

        return $this->sessions->deleteExpired($now - $this->idleSeconds, $now - $this->absoluteSeconds);
    }

    public function validateId(string $id): bool
    {
        // With session.use_strict_mode, an unknown or expired ID from a cookie is replaced, not adopted.
        return $this->read($id) !== '';
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->sessions->touch(self::hash($id), ($this->clock)());

        return true;
    }

    /**
     * The stored key for a session ID.
     * @return string
     */
    private static function hash(string $id): string
    {
        return hash('sha256', $id);
    }

    /**
     * The signed-in user's id from php_serialize session data ($_SESSION['auth']['id']), or null for a guest.
     * @return int|null
     */
    private static function userId(string $data): ?int
    {
        if ($data === '') {
            return null;
        }
        $session = unserialize($data, ['allowed_classes' => false]);
        $id = is_array($session) && is_array($session['auth'] ?? null) ? ($session['auth']['id'] ?? null) : null;

        return is_int($id) ? $id : null;
    }
}
