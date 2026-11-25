<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Database\QueryBuilder;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\Page;
use App\Support\TransactionFilter;
use DateTimeImmutable;
use LogicException;

final class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @var int Rows per batch when exporting. */
    private const BATCH = 500;

    public function create(int $userId, int $productId, string $productName, int $quantity, string $unitPrice, string $totalPrice): Transaction
    {
        $id = $this->database->table('transactions')->insert([
            'user_id' => $userId,
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ]);

        return $this->find($id) ?? throw new LogicException('Created transaction not found');
    }

    public function find(int $id): ?Transaction
    {
        $row = $this->withNames()->where('transactions.id', $id)->first();

        return $row === null ? null : self::toTransaction($row);
    }

    public function paginateForUser(int $userId, int $page, int $perPage): Page
    {
        return $this->withNames()->where('transactions.user_id', $userId)->orderBy('transactions.id', 'desc')->paginate($page, $perPage)->map(self::toTransaction(...));
    }

    public function paginate(int $page, int $perPage, ?TransactionFilter $filter = null): Page
    {
        return $this->filtered($filter ?? new TransactionFilter())->orderBy('transactions.id', 'desc')->paginate($page, $perPage)->map(self::toTransaction(...));
    }

    public function totals(TransactionFilter $filter): array
    {
        $totals = $this->filtered($filter)->countAndSum('transactions.total_price');

        return ['count' => $totals['count'], 'revenue' => $totals['sum'] ?? '0.000'];
    }

    public function each(TransactionFilter $filter): iterable
    {
        $before = PHP_INT_MAX;
        do {
            $rows = $this->filtered($filter)->where('transactions.id', $before, '<')->orderBy('transactions.id', 'desc')->take(self::BATCH);
            foreach ($rows as $row) {
                yield self::toTransaction($row);
                $before = (int) $row['id'];
            }
        } while (count($rows) === self::BATCH);
    }

    public function summary(?int $userId = null): array
    {
        // Raw SQL on purpose: aggregates with exact DECIMAL totals.
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) AS count, COALESCE(SUM(quantity), 0) AS items_sold, COALESCE(SUM(total_price), 0.000) AS revenue FROM transactions'
            . ($userId === null ? '' : ' WHERE user_id = ?'),
        );
        $statement->execute($userId === null ? [] : [$userId]);
        $row = $statement->fetch();
        $row = is_array($row) ? $row : [];

        return [
            'count' => (int) ($row['count'] ?? 0),
            'items_sold' => (int) ($row['items_sold'] ?? 0),
            'revenue' => (string) ($row['revenue'] ?? '0.000'),
        ];
    }

    public function dailySales(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // Raw SQL on purpose: grouped DECIMAL sums. A plain range on created_at can use its index.
        $statement = $this->database->pdo()->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS count, SUM(total_price) AS revenue FROM transactions'
            . ' WHERE created_at >= ? AND created_at < ? GROUP BY day ORDER BY day',
        );
        $statement->execute([$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);

        $days = [];
        foreach ($statement->fetchAll() as $row) {
            $revenue = (string) $row['revenue'];
            $days[(string) $row['day']] = ['count' => (int) $row['count'], 'revenue' => is_numeric($revenue) ? $revenue : '0.000'];
        }

        return $days;
    }

    /**
     * The transaction for a `transactions` row joined with product_name, user_name and user_email.
     * @param array<string, mixed> $row
     * @return Transaction
     */
    private static function toTransaction(array $row): Transaction
    {
        return new Transaction(
            (int) $row['id'],
            (int) $row['user_id'],
            (int) $row['product_id'],
            (int) $row['quantity'],
            (string) $row['unit_price'],
            (string) $row['total_price'],
            (string) $row['created_at'],
            isset($row['product_name']) ? (string) $row['product_name'] : null,
            isset($row['user_name']) ? (string) $row['user_name'] : null,
            isset($row['user_email']) ? (string) $row['user_email'] : null,
        );
    }

    /**
     * A query on transactions with the buyer's name and email; the product name is the copy stored at purchase.
     * @return QueryBuilder
     */
    private function withNames(): QueryBuilder
    {
        return $this->database->table('transactions')
            ->select('transactions.*', 'users.name AS user_name', 'users.email AS user_email')
            ->join('users', 'users.id', 'transactions.user_id');
    }

    /**
     * withNames() narrowed by the filter: customer name or email, product name, and whole days from/to.
     * @return QueryBuilder
     */
    private function filtered(TransactionFilter $filter): QueryBuilder
    {
        $query = $this->withNames()
            ->whereContainsAny(['users.name', 'users.email'], $filter->customer)
            ->whereContains('transactions.product_name', $filter->product);
        if ($filter->from !== null) {
            $query->where('transactions.created_at', $filter->from->format('Y-m-d 00:00:00'), '>=');
        }
        if ($filter->to !== null) {
            $query->where('transactions.created_at', $filter->to->modify('+1 day')->format('Y-m-d 00:00:00'), '<');
        }

        return $query;
    }
}
