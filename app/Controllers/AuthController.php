<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\LoginCooldown;
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
        private readonly LoginCooldown $cooldown,
    ) {
    }

    /**
     * The registration form.
     * @return Response
     */
    #[Route('/register', ['GET'], ['guest'])]
    public function showRegister(Request $request): Response
    {
        $kept = $this->session->takeForm('register');

        return $this->view->render('auth/register', ['title' => 'Register', 'input' => $kept['input'] + ['name' => '', 'email' => ''], 'errors' => $kept['errors']]);
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
            // Never keep the passwords.
            $this->session->flashForm('register', ['name' => $input['name'], 'email' => $input['email']], $e->errors);

            return Response::redirect('/register');
        }

        $this->auth->login($user);
        $this->session->flash("Welcome, {$user->name}!");

        return Response::redirect('/products');
    }

    /**
     * The login form, with the message and email from a refused try (shown once) and, during the
     * cooldown, the seconds left: the page shows them in its message and app.js counts them down.
     * @return Response
     */
    #[Route('/login', ['GET'], ['guest'])]
    public function showLogin(Request $request): Response
    {
        $kept = $this->session->takeForm('login');
        $email = $kept['input']['email'] ?? '';
        $errors = $kept['errors'];
        $seconds = $this->cooldown->secondsLeft();

        return $this->view->render('auth/login', [
            'title' => 'Log in',
            'email' => $email,
            'errors' => $errors,
            'retryAfter' => $seconds,
        ]);
    }

    /**
     * Signs in and goes to the dashboard or the catalogue. A wrong password, or any try during the
     * cooldown, redirects back to the form (post, then redirect), so reloading the page never
     * resends the password; the cooldown is checked before the password.
     * @return Response
     */
    #[Route('/login', ['POST'], ['guest'])]
    public function login(Request $request): Response
    {
        $email = trim($request->input('email'));
        if ($this->cooldown->secondsLeft() > 0) {
            return $this->backToForm($email, '');
        }
        try {
            $user = $this->users->attempt($email, $request->input('password'));
        } catch (ValidationException $e) {
            $this->session->flashForm('login', ['email' => $email], $e->errors);

            return Response::redirect('/login');
        } catch (AuthenticationException $e) {
            $this->cooldown->failed();

            return $this->backToForm($email, $e->getMessage());
        }

        $this->cooldown->succeeded();
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

    /**
     * Redirects to the login form, which shows the message (or the cooldown) and keeps the email.
     * @return Response
     */
    private function backToForm(string $email, string $message): Response
    {
        $this->session->flashForm('login', ['email' => $email], $message === '' ? [] : ['form' => $message]);

        return Response::redirect('/login');
    }
}
