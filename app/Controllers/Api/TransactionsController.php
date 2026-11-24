<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\AuthenticationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Auth\TokenAuth;
use App\Models\Transaction;
use App\Services\PurchaseService;
use App\Support\Page;

final class TransactionsController
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly TokenAuth $auth,
    ) {
    }

    /**
     * Every purchase, newest first (admin token).
     * @return Response
     */
    #[Route('/api/transactions', ['GET'], ['jwt.admin'])]
    public function index(Request $request): Response
    {
        return self::page($this->purchases->all((int) $request->query('page')));
    }

    /**
     * The token user's own purchases, newest first (customer token).
     * @return Response
     */
    #[Route('/api/my-transactions', ['GET'], ['jwt.user'])]
    public function mine(Request $request): Response
    {
        $user = $this->auth->user($request) ?? throw new AuthenticationException();

        return self::page($this->purchases->historyFor($user, (int) $request->query('page')));
    }

    /**
     * A page of transactions as JSON with pagination details.
     * @param Page<Transaction> $page
     * @return Response
     */
    private static function page(Page $page): Response
    {
        return Response::json(['data' => array_map(static fn (Transaction $t): array => $t->toArray(), $page->items), 'meta' => $page->meta()]);
    }
}
