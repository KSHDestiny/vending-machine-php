<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\SessionAuth;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\Session\Session;
use App\Http\View;
use App\Models\User;
use App\Services\AuthService;

/*
 * Web registration, login and logout with the PHP session. Registration creates
 * customer accounts only; the admin comes from the seeder.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $users,
        private readonly SessionAuth $auth,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /**
     * The registration form.
     * @return Response
     */
    #[Route('/register', ['GET'], ['guest'])]
    public function showRegister(Request $request): Response
    {
        return $this->view->render('auth/register', ['title' => 'Register', 'input' => ['name' => '', 'email' => ''], 'errors' => []]);
    }

    /**
     * Creates a customer account and signs it in, or shows the form again with errors.
     * @return Response
     */
    #[Route('/register', ['POST'], ['guest'])]
    public function register(Request $request): Response
    {
        $input = $request->only('name', 'email', 'password', 'password_confirmation');
        try {
            $user = $this->users->register($input);
        } catch (ValidationException $e) {
            // Never send the passwords back into the form.
            $safe = ['name' => $input['name'], 'email' => $input['email']];

            return $this->view->render('auth/register', ['title' => 'Register', 'input' => $safe, 'errors' => $e->errors], 422);
        }

        $this->auth->login($user);
        $this->session->flash("Welcome, {$user->name}!");

        return Response::redirect('/products');
    }

    /**
     * The login form.
     * @return Response
     */
    #[Route('/login', ['GET'], ['guest'])]
    public function showLogin(Request $request): Response
    {
        return $this->view->render('auth/login', ['title' => 'Log in', 'email' => '', 'errors' => []]);
    }

    /**
     * Signs in and goes to the dashboard or the catalogue, or shows the form again.
     * @return Response
     */
    #[Route('/login', ['POST'], ['guest'])]
    public function login(Request $request): Response
    {
        $email = trim($request->input('email'));
        try {
            $user = $this->users->attempt($email, $request->input('password'));
        } catch (ValidationException $e) {
            return $this->view->render('auth/login', ['title' => 'Log in', 'email' => $email, 'errors' => $e->errors], 422);
        } catch (AuthenticationException $e) {
            return $this->view->render('auth/login', ['title' => 'Log in', 'email' => $email, 'errors' => ['form' => $e->getMessage()]], 422);
        }

        $this->auth->login($user);

        return Response::redirect(self::home($user));
    }

    /**
     * Signs out and ends the session.
     * @return Response
     */
    #[Route('/logout', ['POST'], ['auth'])]
    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return Response::redirect('/login');
    }

    /**
     * Where a user lands after signing in.
     * @return string
     */
    private static function home(User $user): string
    {
        return $user->canManageProducts() ? '/admin' : '/products';
    }
}
