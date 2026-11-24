<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Request;
use App\Models\User;
use App\Services\AuthService;
use App\Services\JwtService;

/*
 * API authentication: the user named by the request's bearer JWT, reloaded from
 * the database so a role change or a deleted account applies at once.
 */
final class TokenAuth
{
    /** @var User|null The token's user, resolved once per request. */
    private ?User $current = null;

    /** @var bool Whether $current has been resolved for this request. */
    private bool $resolved = false;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly AuthService $auth,
    ) {
    }

    /**
     * The user of a valid, unexpired bearer token, or null.
     * @return User|null
     */
    public function user(Request $request): ?User
    {
        if ($this->resolved) {
            return $this->current;
        }

        $this->resolved = true;
        $id = $this->jwt->userId($request->bearerToken());
        $this->current = $id === null ? null : $this->auth->find($id);

        return $this->current;
    }
}
