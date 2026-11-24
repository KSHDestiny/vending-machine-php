<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AuthenticationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Auth\SessionAuth;
use App\Http\View;
use App\Models\User;
use App\Services\PurchaseService;

/*
 * Purchase history: a customer's own purchases, the purchase confirmation, and
 * every purchase for admins.
 */
final class TransactionsController
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly SessionAuth $auth,
        private readonly View $view,
    ) {
    }

    /**
     * The signed-in customer's purchases.
     * @return Response
     */
    #[Route('/my-transactions', ['GET'], ['user'])]
    public function mine(Request $request): Response
    {
        $page = $this->purchases->historyFor($this->user(), (int) $request->query('page'));

        return $this->view->render('transactions/index', ['title' => 'My purchases', 'transactions' => $page]);
    }

    /**
     * One purchase (the confirmation after buying); only its buyer or an admin may see it.
     * @return Response
     */
    #[Route('/transactions/{id}', ['GET'], ['auth'])]
    public function show(Request $request, int $id): Response
    {
        $transaction = $this->purchases->findFor($this->user(), $id);

        return $this->view->render('transactions/show', ['title' => 'Purchase confirmation', 'transaction' => $transaction]);
    }

    /**
     * Every purchase, for admins.
     * @return Response
     */
    #[Route('/admin/transactions', ['GET'], ['admin'])]
    public function index(Request $request): Response
    {
        $page = $this->purchases->all((int) $request->query('page'));

        return $this->view->render('admin/transactions', ['title' => 'All transactions', 'transactions' => $page]);
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
