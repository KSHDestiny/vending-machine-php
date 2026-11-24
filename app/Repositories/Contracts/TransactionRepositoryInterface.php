<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use App\Support\Page;

interface TransactionRepositoryInterface
{
    /**
     * The recorded purchase, with its product name.
     * @return Transaction
     */
    public function create(int $userId, int $productId, int $quantity, string $unitPrice, string $totalPrice): Transaction;

    /**
     * The purchase with its product and buyer names, or null.
     * @return Transaction|null
     */
    public function find(int $id): ?Transaction;

    /**
     * One page of the user's purchases, newest first.
     * @return Page<Transaction>
     */
    public function paginateForUser(int $userId, int $page, int $perPage): Page;

    /**
     * One page of everyone's purchases, newest first, with buyer names.
     * @return Page<Transaction>
     */
    public function paginate(int $page, int $perPage): Page;

    /**
     * Totals over all purchases; revenue is an exact decimal string.
     * @return array{count: int, items_sold: int, revenue: string}
     */
    public function summary(): array;
}
