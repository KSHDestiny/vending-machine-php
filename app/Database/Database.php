<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Pdo\Mysql;
use Throwable;

/*
 * The PDO connection, opened on first use, with exceptions on errors and real
 * (not emulated) prepared statements. Injected into repositories; never global.
 */
final class Database implements TransactionManager
{
    /** @var PDO|null The open connection, or null until first use. */
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $username,
        #[\SensitiveParameter] private readonly string $password,
    ) {
    }

    /**
     * A connection for the 'db' settings in config/app.php.
     * @param array{host: string, port: int, name: string, user: string, password: string} $config
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']);

        return new self($dsn, $config['user'], $config['password']);
    }

    /**
     * The PDO connection.
     * @return PDO
     */
    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO($this->dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            Mysql::ATTR_FOUND_ROWS => true,
        ]);
    }

    /**
     * A new query on the table, e.g. table('products')->where('id', 1)->first().
     * @return QueryBuilder
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * The work's result, run inside BEGIN ... COMMIT, rolled back if it throws.
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
