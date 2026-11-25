<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use App\Support\Page;
use App\Support\TransactionFilter;
use DateTimeImmutable;

interface TransactionRepositoryInterface
{
    /**
     * The recorded purchase, keeping the product's name at the time of purchase.
     * @return Transaction
     */
    public function create(int $userId, int $productId, string $productName, int $quantity, string $unitPrice, string $totalPrice): Transaction;

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
     * One page of everyone's purchases matching the filter, newest first, with buyer names.
     * @return Page<Transaction>
     */
    public function paginate(int $page, int $perPage, ?TransactionFilter $filter = null): Page;

    /**
     * How many purchases match the filter and their exact total.
     * @return array{count: int, revenue: string}
     */
    public function totals(TransactionFilter $filter): array;

    /**
     * Every purchase matching the filter, newest first, read in batches so exports never load them all at once.
     * @return iterable<Transaction>
     */
    public function each(TransactionFilter $filter): iterable;

    /**
     * Totals over all purchases, or one buyer's with $userId; revenue is an exact decimal string.
     * @return array{count: int, items_sold: int, revenue: string}
     */
    public function summary(?int $userId = null): array;

    /**
     * Purchases and exact revenue per day from $from up to (not including) $to; days without sales are left out.
     * @return array<string, array{count: int, revenue: numeric-string}> keyed by Y-m-d, oldest first
     */
    public function dailySales(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
