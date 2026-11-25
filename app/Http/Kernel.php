<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Routing\Router;
use Throwable;

/*
 * Handles one request: the router finds and runs the route, and any exception
 * becomes a response through the ErrorHandler.
 */
final class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errors,
        private readonly View $view,
        private readonly bool $production = false,
    ) {
    }

    /**
     * The response for the request; never throws.
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $this->view->setCurrentPath($request->path);
        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->errors->render($exception, $request);
        }

        return $this->withSecurityHeaders($response);
    }

    /**
     * The response with the security headers it didn't set itself: a Content-Security-Policy
     * that allows only this site's own scripts (no inline ones; inline style attributes are
     * allowed for progress bars), a Permissions-Policy, and HSTS in production (HTTPS only).
     * @return Response
     */
    private function withSecurityHeaders(Response $response): Response
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data:; "
                . "connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ] + ($this->production ? ['Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'] : []);

        return new Response($response->status, $response->body, $response->headers + $headers);
    }
}
