<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Database\QueryBuilder;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\Page;
use DateTimeImmutable;
use LogicException;

final class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function create(int $userId, int $productId, int $quantity, string $unitPrice, string $totalPrice): Transaction
    {
        $id = $this->database->table('transactions')->insert([
            'user_id' => $userId,
            'product_id' => $productId,
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

    public function paginate(int $page, int $perPage): Page
    {
        return $this->withNames()->orderBy('transactions.id', 'desc')->paginate($page, $perPage)->map(self::toTransaction(...));
    }

    public function summary(): array
    {
        // Raw SQL on purpose: aggregates with exact DECIMAL totals.
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) AS count, COALESCE(SUM(quantity), 0) AS items_sold, COALESCE(SUM(total_price), 0.000) AS revenue FROM transactions',
        );
        $statement->execute();
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
     * A query on transactions with the product name and the buyer's name and email.
     * @return QueryBuilder
     */
    private function withNames(): QueryBuilder
    {
        return $this->database->table('transactions')
            ->select('transactions.*', 'products.name AS product_name', 'users.name AS user_name', 'users.email AS user_email')
            ->join('products', 'products.id', 'transactions.product_id')
            ->join('users', 'users.id', 'transactions.user_id');
    }
}
