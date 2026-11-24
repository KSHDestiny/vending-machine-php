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
use App\Services\AccountService;

/*
 * The signed-in user's account page: change the name, change the password.
 */
final class AccountController
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly SessionAuth $auth,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /**
     * The account page with both forms.
     * @return Response
     */
    #[Route('/account', ['GET'], ['auth'])]
    public function show(Request $request): Response
    {
        return $this->page($this->user()->name, [], []);
    }

    /**
     * Saves the new name, or shows the page again with the error.
     * @return Response
     */
    #[Route('/account', ['PUT'], ['auth'])]
    public function updateName(Request $request): Response
    {
        try {
            $this->accounts->updateName($this->user(), $request->only('name'));
        } catch (ValidationException $e) {
            return $this->page($request->input('name'), $e->errors, [], 422);
        }

        $this->session->flash('Your name was updated.');

        return Response::redirect('/account');
    }

    /**
     * Sets the new password and renews the session, or shows the page again with the errors.
     * @return Response
     */
    #[Route('/account/password', ['PUT'], ['auth'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->user();
        try {
            $this->accounts->changePassword($user, $request->only('current_password', 'password', 'password_confirmation'));
        } catch (ValidationException $e) {
            return $this->page($user->name, [], $e->errors, 422);
        }

        // A new session id after a password change, against session fixation.
        $this->auth->login($user);
        $this->session->flash('Your password was changed.');

        return Response::redirect('/account');
    }

    /**
     * The account page with the name field and any errors for each form.
     * @param array<string, string> $nameErrors
     * @param array<string, string> $passwordErrors
     * @return Response
     */
    private function page(string $name, array $nameErrors, array $passwordErrors, int $status = 200): Response
    {
        return $this->view->render('account/show', [
            'title' => 'My account',
            'user' => $this->user(),
            'name' => $name,
            'nameErrors' => $nameErrors,
            'passwordErrors' => $passwordErrors,
        ], $status);
    }

    /**
     * The signed-in user; the route middleware guarantees one.
     * @return User
     */
    private function user(): User
    {
        return $this->auth->user() ?? throw new AuthenticationException();
    }
}
