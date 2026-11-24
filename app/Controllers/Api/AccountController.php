<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Auth\TokenAuth;
use App\Exceptions\AuthenticationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Models\User;
use App\Services\AccountService;

/*
 * The token user's own account as JSON: read it, change the name, change the password.
 * Validation errors (422) come from the ErrorHandler.
 */
final class AccountController
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly TokenAuth $auth,
    ) {
    }

    /**
     * The signed-in user.
     * @return Response
     */
    #[Route('/api/me', ['GET'], ['jwt'])]
    public function show(Request $request): Response
    {
        return Response::json(['data' => $this->user($request)->toArray()]);
    }

    /**
     * Changes the name: {"name": "..."}.
     * @return Response
     */
    #[Route('/api/me', ['PUT'], ['jwt'])]
    public function updateName(Request $request): Response
    {
        return Response::json(['data' => $this->accounts->updateName($this->user($request), $request->only('name'))->toArray()]);
    }

    /**
     * Changes the password: {"current_password", "password", "password_confirmation"}; 204 when done.
     * @return Response
     */
    #[Route('/api/me/password', ['PUT'], ['jwt'])]
    public function changePassword(Request $request): Response
    {
        $this->accounts->changePassword($this->user($request), $request->only('current_password', 'password', 'password_confirmation'));

        return Response::noContent();
    }

    /**
     * The token's user; the route middleware guarantees one.
     * @return User
     */
    private function user(Request $request): User
    {
        return $this->auth->user($request) ?? throw new AuthenticationException();
    }
}
