<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Request;
use App\Http\Response;
use Closure;

/*
 * Runs before a route's action: call $next() to continue, return a Response to
 * stop, or throw an exception the ErrorHandler turns into a response.
 */
interface Middleware
{
    /**
     * The response from $next() or one that stops the request.
     * @param Closure(): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response;
}
