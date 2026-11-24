<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Http\Routing\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Auth\TokenAuth;
use App\Enums\Role;
use Closure;

/*
 * The 'jwt', 'jwt.admin' and 'jwt.user' API middleware: a valid bearer token is
 * required (401), and with a role, a user with that role (403).
 */
final class ApiAuthMiddleware implements Middleware
{
    public function __construct(
        private readonly TokenAuth $auth,
        private readonly ?Role $role = null,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->user($request);
        if ($user === null) {
            throw new AuthenticationException('A valid bearer token is required.');
        }

        if ($this->role !== null && $user->role !== $this->role) {
            throw new AuthorizationException(RoleMiddleware::message($this->role));
        }

        return $next();
    }
}
