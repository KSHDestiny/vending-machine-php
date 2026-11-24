<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Routing\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Auth\SessionAuth;
use Closure;

/*
 * The 'guest' middleware for the login and registration pages: signed-in users are sent home.
 */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly SessionAuth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $this->auth->user() === null ? $next() : Response::redirect('/products');
    }
}
