<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Page;
use InvalidArgumentException;

/*
 * A small Eloquent-style query builder for the repositories:
 * $db->table('products')->where('id', $id)->whereNull('deleted_at')->first().
 *
 * Values are always bound parameters; table and column names must be plain
 * identifiers and operators come from a whitelist, so no input reaches the SQL text.
 */
final class QueryBuilder
{
    /** @var list<string> Operators where() accepts. */
    private const OPERATORS = ['=', '!=', '<', '<=', '>', '>='];

    /** @var list<string> Selected columns or expressions, e.g. 'products.name AS product_name'. */
    private array $columns = ['*'];

    /** @var list<string> JOIN clauses. */
    private array $joins = [];

    /** @var list<string> WHERE conditions, joined with AND. */
    private array $wheres = [];

    /** @var list<mixed> Values for the WHERE placeholders, in order. */
    private array $bindings = [];

    /** @var list<string> ORDER BY terms. */
    private array $orders = [];

    /** @var bool Whether SELECT locks the rows until the transaction ends. */
    private bool $lock = false;

    public function __construct(
        private readonly Database $database,
        private readonly string $table,
    ) {
        self::identifier($table);
    }

    /**
     * Selects these columns instead of *; 'table.column' and 'column AS alias' are allowed.
     * @return self
     */
    public function select(string ...$columns): self
    {
        $this->columns = array_values(array_map(self::column(...), $columns));

        return $this;
    }

    /**
     * Adds an INNER JOIN on $first = $second.
     * @return self
     */
    public function join(string $table, string $first, string $second): self
    {
        $this->joins[] = 'JOIN ' . self::identifier($table) . ' ON ' . self::column($first) . ' = ' . self::column($second);

        return $this;
    }

    /**
     * Adds a LEFT JOIN on $first = $second, keeping rows without a match.
     * @return self
     */
    public function leftJoin(string $table, string $first, string $second): self
    {
        $this->joins[] = 'LEFT JOIN ' . self::identifier($table) . ' ON ' . self::column($first) . ' = ' . self::column($second);

        return $this;
    }

    /**
     * Adds "column operator ?" with a bound value.
     * @return self
     */
    public function where(string $column, mixed $value, string $operator = '='): self
    {
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported operator: {$operator}");
        }

        $this->wheres[] = self::column($column) . " {$operator} ?";
        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Adds "column IS NULL".
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = self::column($column) . ' IS NULL';

        return $this;
    }

    /**
     * Adds "column LIKE %search%" with wildcards escaped; an empty search adds nothing.
     * @return self
     */
    public function whereContains(string $column, string $search): self
    {
        if ($search === '') {
            return $this;
        }

        $this->wheres[] = self::column($column) . ' LIKE ?';
        $this->bindings[] = '%' . addcslashes($search, '%_\\') . '%';

        return $this;
    }

    /**
     * Adds "(a LIKE %search% OR b LIKE %search% ...)" with wildcards escaped; an empty search adds nothing.
     * @param list<string> $columns
     * @return self
     */
    public function whereContainsAny(array $columns, string $search): self
    {
        if ($search === '' || $columns === []) {
            return $this;
        }

        $pattern = '%' . addcslashes($search, '%_\\') . '%';
        $this->wheres[] = '(' . implode(' OR ', array_map(static fn (string $column): string => self::column($column) . ' LIKE ?', $columns)) . ')';
        array_push($this->bindings, ...array_fill(0, count($columns), $pattern));

        return $this;
    }

    /**
     * Adds an ORDER BY term; any direction other than 'desc' sorts ascending.
     * @return self
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = self::column($column) . (strtolower($direction) === 'desc' ? ' DESC' : ' ASC');

        return $this;
    }

    /**
     * Makes the SELECT lock the matched rows until the transaction ends (SELECT ... FOR UPDATE).
     * @return self
     */
    public function lockForUpdate(): self
    {
        $this->lock = true;

        return $this;
    }

    /**
     * The first matching row, or null.
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $rows = $this->run($this->selectSql(' LIMIT 1'), $this->bindings);

        return $rows[0] ?? null;
    }

    /**
     * Every matching row.
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        return $this->run($this->selectSql(), $this->bindings);
    }

    /**
     * Up to $count matching rows (in the current order).
     * @return list<array<string, mixed>>
     */
    public function take(int $count): array
    {
        return $this->run($this->selectSql(' LIMIT ' . max(1, $count)), $this->bindings);
    }

    /**
     * The number of matching rows and the exact sum of a column (null when nothing matches).
     * @return array{count: int, sum: ?string}
     */
    public function countAndSum(string $column): array
    {
        $rows = $this->run('SELECT COUNT(*) AS aggregate, SUM(' . self::column($column) . ') AS total FROM ' . $this->table . $this->joinSql() . $this->whereSql(), $this->bindings);

        return ['count' => (int) ($rows[0]['aggregate'] ?? 0), 'sum' => isset($rows[0]['total']) ? (string) $rows[0]['total'] : null];
    }

    /**
     * The number of matching rows.
     * @return int
     */
    public function count(): int
    {
        $rows = $this->run('SELECT COUNT(*) AS aggregate FROM ' . $this->table . $this->joinSql() . $this->whereSql(), $this->bindings);

        return (int) ($rows[0]['aggregate'] ?? 0);
    }

    /**
     * One page of matching rows plus the total; a page below 1 or past the end of int range is clamped.
     * @return Page<array<string, mixed>>
     */
    public function paginate(int $page, int $perPage): Page
    {
        $limit = max(1, $perPage);
        $page = min(max(1, $page), intdiv(PHP_INT_MAX, $limit));
        $offset = ($page - 1) * $limit;

        $rows = $this->run($this->selectSql(" LIMIT {$limit} OFFSET {$offset}"), $this->bindings);

        return new Page($rows, $this->count(), $page, $limit);
    }

    /**
     * Inserts one row and returns its new id.
     * @param array<string, mixed> $values column => value
     * @return int
     */
    public function insert(array $values): int
    {
        $columns = array_map(self::identifier(...), array_keys($values));
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')';
        $this->database->pdo()->prepare($sql)->execute(array_values($values));

        return (int) $this->database->pdo()->lastInsertId();
    }

    /**
     * Updates the matching rows and returns how many matched; refuses to run without a WHERE.
     * @param array<string, mixed> $values column => value
     * @return int
     */
    public function update(array $values): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('update() needs a where() condition');
        }

        $sets = array_map(static fn (string $column): string => self::identifier($column) . ' = ?', array_keys($values));
        $statement = $this->database->pdo()->prepare('UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->whereSql());
        $statement->execute([...array_values($values), ...$this->bindings]);

        return $statement->rowCount();
    }

    /**
     * The SELECT statement; MySQL requires LIMIT before FOR UPDATE.
     * @return string
     */
    private function selectSql(string $limit = ''): string
    {
        $order = $this->orders === [] ? '' : ' ORDER BY ' . implode(', ', $this->orders);

        return 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table . $this->joinSql() . $this->whereSql() . $order . $limit . ($this->lock ? ' FOR UPDATE' : '');
    }

    /**
     * The JOIN clauses.
     * @return string
     */
    private function joinSql(): string
    {
        return $this->joins === [] ? '' : ' ' . implode(' ', $this->joins);
    }

    /**
     * The WHERE clause, or ''.
     * @return string
     */
    private function whereSql(): string
    {
        return $this->wheres === [] ? '' : ' WHERE ' . implode(' AND ', $this->wheres);
    }

    /**
     * The rows a prepared statement returns.
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    private function run(string $sql, array $bindings): array
    {
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($bindings);

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll();
    }

    /**
     * The name, if it is a plain identifier such as 'products'.
     * @return string
     */
    private static function identifier(string $name): string
    {
        return preg_match('/^[a-z_][a-z0-9_]*$/', $name) === 1 ? $name : throw new InvalidArgumentException("Invalid identifier: {$name}");
    }

    /**
     * The column, if it is 'column', 'table.column', 'table.*' or one of those with ' AS alias'.
     * @return string
     */
    private static function column(string $column): string
    {
        $valid = preg_match('/^(\*|[a-z_][a-z0-9_]*(\.([a-z_][a-z0-9_]*|\*))?)( AS [a-z_][a-z0-9_]*)?$/', $column) === 1;

        return $valid ? $column : throw new InvalidArgumentException("Invalid column: {$column}");
    }
}
