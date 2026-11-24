<?php

declare(strict_types=1);

/*
 * Web routes. Every route is a #[Route] attribute on its controller method; this file
 * names the middleware those attributes use and registers the controllers.
 */

use App\Http\Routing\Router;
use App\Http\Session\Session;
use App\Auth\SessionAuth;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\VerifyCsrfToken;
use App\Enums\Role;

return static function (Router $router, SessionAuth $auth, Session $session, object $health, object ...$controllers): void {
    $router->alias('csrf', new VerifyCsrfToken($session));
    $router->alias('auth', new AuthMiddleware($auth));
    $router->alias('guest', new GuestMiddleware($auth));
    $router->alias('admin', new RoleMiddleware($auth, Role::Admin));
    $router->alias('user', new RoleMiddleware($auth, Role::User));

    $router->registerController($health);

    // Browser pages use the session cookie, so every form post must carry the CSRF token.
    $router->group(['csrf'], static function (Router $router) use ($controllers): void {
        foreach ($controllers as $controller) {
            $router->registerController($controller);
        }
    });
};
