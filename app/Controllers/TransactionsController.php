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
use App\Support\TransactionFilter;

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
        $user = $this->user();
        $transaction = $this->purchases->findFor($user, $id);

        return $this->view->render('transactions/show', ['title' => $user->canManageProducts() ? "Purchase #{$transaction->id}" : 'Purchase confirmation', 'transaction' => $transaction]);
    }

    /**
     * Every purchase for admins, filtered by ?customer=, ?product=, ?from= and ?to= (Y-m-d).
     * @return Response
     */
    #[Route('/admin/transactions', ['GET'], ['admin'])]
    public function index(Request $request): Response
    {
        $filter = self::filter($request);

        return $this->view->render('admin/transactions', [
            'title' => 'All transactions',
            'transactions' => $this->purchases->all((int) $request->query('page'), $filter),
            'totals' => $this->purchases->totals($filter),
            'filter' => $filter,
        ]);
    }

    /**
     * The filtered purchases as a CSV download, with the same filters as the list.
     * @return Response
     */
    #[Route('/admin/transactions/export', ['GET'], ['admin'])]
    public function export(Request $request): Response
    {
        $filename = 'transactions-' . gmdate('Y-m-d') . '.csv';

        return new Response(200, $this->purchases->csv(self::filter($request)), [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The admin filters from the query string.
     * @return TransactionFilter
     */
    private static function filter(Request $request): TransactionFilter
    {
        return TransactionFilter::fromQuery([
            'customer' => $request->query('customer'),
            'product' => $request->query('product'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);
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
