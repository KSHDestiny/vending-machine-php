<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Session\Session;
use Closure;

/*
 * Web login cooldown kept in the visitor's session (no table or file): the 5th wrong
 * password in a row makes the server refuse logins from that session for 30 seconds.
 * A browser that drops its session cookie starts afresh, so this stops repeated guessing
 * in a normal browser, not a determined script. API logins use tokens, not sessions.
 */
final class LoginCooldown
{
    /** @var string The session key holding {failures, until}. */
    private const KEY = '_login_cooldown';

    /** @var Closure(): int The current Unix time; replaceable in tests. */
    private readonly Closure $clock;

    /**
     * @param Closure(): int|null $clock
     */
    public function __construct(
        private readonly Session $session,
        private readonly int $maxFailures = 5,
        private readonly int $waitSeconds = 30,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? time(...);
    }

    /**
     * Seconds until this session may try to log in again; 0 when it may now.
     * @return int
     */
    public function secondsLeft(): int
    {
        return max(0, $this->state()['until'] - ($this->clock)());
    }

    /**
     * Counts a wrong password; the last allowed one starts the wait.
     * @return void
     */
    public function failed(): void
    {
        $failures = $this->state()['failures'] + 1;
        $this->session->set(self::KEY, $failures >= $this->maxFailures
            ? ['failures' => 0, 'until' => ($this->clock)() + $this->waitSeconds]
            : ['failures' => $failures, 'until' => 0]);
    }

    /**
     * Clears the count after a successful login.
     * @return void
     */
    public function succeeded(): void
    {
        $this->session->set(self::KEY, null);
    }

    /**
     * The stored count and wait end, or zeros.
     * @return array{failures: int, until: int}
     */
    private function state(): array
    {
        $state = $this->session->get(self::KEY);

        return is_array($state) ? ['failures' => (int) ($state['failures'] ?? 0), 'until' => (int) ($state['until'] ?? 0)] : ['failures' => 0, 'until' => 0];
    }
}
