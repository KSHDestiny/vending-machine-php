<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Http\Routing\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Auth\SessionAuth;
use App\Enums\Role;
use Closure;

/*
 * The 'admin' and 'user' middleware: guests go to the login page, and signed-in
 * users with another role get 403. Checked on the server for every request.
 */
final class RoleMiddleware implements Middleware
{
    public function __construct(
        private readonly SessionAuth $auth,
        private readonly Role $role,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            throw new AuthenticationException();
        }

        if ($user->role !== $this->role) {
            throw new AuthorizationException(self::message($this->role));
        }

        return $next();
    }

    /**
     * The 403 message for a missing role.
     * @return string
     */
    public static function message(Role $role): string
    {
        return $role === Role::Admin ? 'Only admins can do this.' : 'Only customer accounts can buy products.';
    }
}
