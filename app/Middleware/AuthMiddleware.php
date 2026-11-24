<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthenticationException;
use App\Http\Routing\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Auth\SessionAuth;
use Closure;

/*
 * The 'auth' middleware: only signed-in users continue; guests go to the login page.
 */
final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly SessionAuth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->user() === null) {
            throw new AuthenticationException();
        }

        return $next();
    }
}
