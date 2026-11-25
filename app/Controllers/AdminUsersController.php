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
use App\Services\UserManagementService;

/*
 * Admin pages for accounts: search and filter, view one, change its role or status.
 * The rules live in UserManagementService; this controller only handles HTTP.
 */
final class AdminUsersController
{
    public function __construct(
        private readonly UserManagementService $users,
        private readonly SessionAuth $auth,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /**
     * Accounts: ?q= (name or email), ?role=admin|user, ?status=active|inactive, ?page=.
     * @return Response
     */
    #[Route('/admin/users', ['GET'], ['admin'])]
    public function index(Request $request): Response
    {
        $filters = ['q' => trim($request->query('q')), 'role' => $request->query('role'), 'status' => $request->query('status')];
        $page = $this->users->list((int) $request->query('page'), $filters['q'], $filters['role'], $filters['status']);

        return $this->view->render('admin/users/index', ['title' => 'Users', 'users' => $page, 'filters' => $filters]);
    }

    /**
     * One account with its purchase totals and the role and status forms.
     * @return Response
     */
    #[Route('/admin/users/{id}', ['GET'], ['admin'])]
    public function show(Request $request, int $id): Response
    {
        return $this->page($id, []);
    }

    /**
     * Changes the role, or shows the page again with the reason it was refused.
     * @return Response
     */
    #[Route('/admin/users/{id}/role', ['PATCH'], ['admin'])]
    public function updateRole(Request $request, int $id): Response
    {
        try {
            ['user' => $user, 'changed' => $changed] = $this->users->changeRole($this->actor(), $id, $request->input('role'));
        } catch (ValidationException $e) {
            return $this->page($id, $e->errors, 422);
        }

        $this->session->flash($changed ? "{$user->name} is now {$user->role->label()}." : "No change: {$user->name} is already {$user->role->label()}.");

        return Response::redirect("/admin/users/{$id}");
    }

    /**
     * Activates or deactivates the account, or shows the page again with the reason it was refused.
     * @return Response
     */
    #[Route('/admin/users/{id}/status', ['PATCH'], ['admin'])]
    public function updateStatus(Request $request, int $id): Response
    {
        try {
            ['user' => $user, 'changed' => $changed] = $this->users->changeStatus($this->actor(), $id, $request->input('status'));
        } catch (ValidationException $e) {
            return $this->page($id, $e->errors, 422);
        }

        $this->session->flash(match (true) {
            !$changed => "No change: {$user->name} is already {$user->status->label()}.",
            $user->isActive() => "{$user->name} can sign in again.",
            default => "{$user->name} is deactivated and signed out.",
        });

        return Response::redirect("/admin/users/{$id}");
    }

    /**
     * The account page with any errors.
     * @param array<string, string> $errors
     * @return Response
     */
    private function page(int $id, array $errors, int $status = 200): Response
    {
        $profile = $this->users->profile($id, $this->actor());

        return $this->view->render('admin/users/show', ['title' => $profile['user']->name, 'errors' => $errors, 'isSelf' => $profile['user']->id === $this->actor()->id] + $profile, $status);
    }

    /**
     * The signed-in admin; the route middleware guarantees one.
     * @return User
     */
    private function actor(): User
    {
        return $this->auth->user() ?? throw new AuthenticationException();
    }
}
