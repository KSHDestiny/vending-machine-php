<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Session\Session;
use App\Models\User;
use App\Services\AuthService;

/*
 * Web authentication with the PHP session. Login stores the user id and role;
 * the user is reloaded from the database once per request, so a role change or
 * a deleted account applies at once.
 */
final class SessionAuth
{
    /** @var string Session key of the signed-in user. */
    private const KEY = 'auth';

    /** @var User|null The signed-in user, resolved once per request. */
    private ?User $current = null;

    /** @var bool Whether $current has been resolved for this request. */
    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly AuthService $auth,
    ) {
    }

    /**
     * Signs the user in with a new session id.
     * @return void
     */
    public function login(User $user): void
    {
        $this->session->regenerate();
        $this->session->set(self::KEY, ['id' => $user->id, 'role' => $user->role->value]);
        $this->current = $user;
        $this->resolved = true;
    }

    /**
     * The signed-in user, or null for a guest.
     * @return User|null
     */
    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->current;
        }

        $this->resolved = true;
        $id = $this->session->get(self::KEY)['id'] ?? null;
        $this->current = is_int($id) ? $this->auth->find($id) : null;

        return $this->current;
    }

    /**
     * Signs out and ends the session.
     * @return void
     */
    public function logout(): void
    {
        $this->session->destroy();
        $this->current = null;
        $this->resolved = true;
    }
}
