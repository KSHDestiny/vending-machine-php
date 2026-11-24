<?php

declare(strict_types=1);

/*
 * API routes (/api/...), also #[Route] attributes on the controllers. They use JWT
 * bearer tokens, never the session, so they stay outside the web 'csrf' group.
 */

use App\Http\Routing\Router;
use App\Auth\TokenAuth;
use App\Middleware\ApiAuthMiddleware;
use App\Enums\Role;

return static function (Router $router, TokenAuth $auth, object ...$controllers): void {
    $router->alias('jwt', new ApiAuthMiddleware($auth));
    $router->alias('jwt.admin', new ApiAuthMiddleware($auth, Role::Admin));
    $router->alias('jwt.user', new ApiAuthMiddleware($auth, Role::User));

    foreach ($controllers as $controller) {
        $router->registerController($controller);
    }
};
