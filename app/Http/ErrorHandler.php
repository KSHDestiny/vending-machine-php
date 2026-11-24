<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Support\Logger;
use Throwable;

/*
 * Turns every exception into a response in one place: JSON for /api, an error
 * page for the web. Unexpected errors are logged and shown only as a generic 500,
 * never with stack traces, SQL or secrets.
 */
final class ErrorHandler
{
    /** @var array<int, string> Page titles for the web error page. */
    private const TITLES = [403 => 'Forbidden', 404 => 'Not found', 405 => 'Method not allowed', 422 => 'Invalid input', 500 => 'Something went wrong'];

    public function __construct(
        private readonly View $view,
        private readonly Logger $logger,
    ) {
    }

    /**
     * The response for an exception thrown while handling the request.
     * @return Response
     */
    public function render(Throwable $exception, Request $request): Response
    {
        [$status, $message, $headers] = match (true) {
            $exception instanceof ValidationException => [422, $exception->getMessage(), []],
            $exception instanceof NotFoundException => [404, $exception->getMessage(), []],
            $exception instanceof MethodNotAllowedException => [405, $exception->getMessage(), ['Allow' => implode(', ', $exception->allowed)]],
            $exception instanceof AuthenticationException => [401, $exception->getMessage(), ['WWW-Authenticate' => 'Bearer']],
            $exception instanceof AuthorizationException => [403, $exception->getMessage(), []],
            default => [500, 'Something went wrong. Please try again later.', []],
        };

        if ($status === 500) {
            $this->logger->error("Unhandled exception for {$request->method} {$request->path}", $exception);
        }

        if ($request->isApi()) {
            $errors = $exception instanceof ValidationException ? ['errors' => $exception->errors] : [];

            return Response::json(['message' => $message] + $errors, $status, $headers);
        }

        // Guests reaching a protected page are sent to log in.
        if ($exception instanceof AuthenticationException) {
            return Response::redirect('/login');
        }

        try {
            $page = $this->view->render('errors/error', ['title' => self::TITLES[$status] ?? 'Error', 'message' => $message, 'status' => $status], $status);

            return new Response($page->status, $page->body, $page->headers + $headers);
        } catch (Throwable $viewError) {
            $this->logger->error('The error page could not be rendered', $viewError);

            return new Response($status, $message, ['Content-Type' => 'text/plain; charset=utf-8'] + $headers);
        }
    }
}
