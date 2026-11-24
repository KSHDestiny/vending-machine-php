<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthorizationException;
use App\Http\Routing\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session\Session;
use Closure;

/*
 * The 'csrf' middleware for browser routes: the browser sends the session cookie on
 * its own, so every state-changing request must also carry the session's _token.
 */
final class VerifyCsrfToken implements Middleware
{
    /** @var list<string> Methods that never change state. */
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method, self::SAFE, true) && !$this->session->isValidCsrfToken($request->input('_token'))) {
            throw new AuthorizationException('This form has expired. Go back, reload the page and try again.');
        }

        return $next();
    }
}
