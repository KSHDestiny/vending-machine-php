<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Models\User;
use App\Services\AuthService;
use App\Services\JwtService;

/*
 * API registration and login; both return a JWT to send as
 * "Authorization: Bearer <token>". Errors (422, 401) come from the ErrorHandler.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $users,
        private readonly JwtService $jwt,
    ) {
    }

    /**
     * Creates a customer account: 201 with a token and the user.
     * @return Response
     */
    #[Route('/api/register', ['POST'])]
    public function register(Request $request): Response
    {
        $user = $this->users->register($request->only('name', 'email', 'password', 'password_confirmation'));

        return Response::json(['data' => $this->token($user)], 201);
    }

    /**
     * A token for valid credentials, with the user it belongs to.
     * @return Response
     */
    #[Route('/api/login', ['POST'])]
    public function login(Request $request): Response
    {
        $user = $this->users->attempt($request->input('email'), $request->input('password'));

        return Response::json(['data' => $this->token($user)]);
    }

    /**
     * The token response body for the user.
     * @return array{token: string, token_type: string, expires_in: int, user: array{id: int, name: string, email: string, role: string}}
     */
    private function token(User $user): array
    {
        return ['token' => $this->jwt->issue($user), 'token_type' => 'Bearer', 'expires_in' => $this->jwt->ttl(), 'user' => $user->toArray()];
    }
}
