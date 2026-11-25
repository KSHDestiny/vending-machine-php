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
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            return $this->errors->render($exception, $request);
        }
    }
}
