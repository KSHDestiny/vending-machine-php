<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Database;
use App\Database\Migrator;
use App\Enums\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

/*
 * Base class for tests against the separate test database (DB_TEST_NAME, never the
 * app's database). Migrates it once, then empties the tables before every test.
 * Fails, rather than skips, when no safe test database is configured.
 */
abstract class DatabaseTestCase extends TestCase
{
    /** @var bool Whether this PHP process already migrated the test database. */
    private static bool $migrated = false;

    protected Database $database;

    protected function setUp(): void
    {
        $this->database = Database::fromConfig(self::config());
        if (!self::$migrated) {
            (new Migrator($this->database, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
            self::$migrated = true;
        }

        $pdo = $this->database->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['inventory_movements', 'sessions', 'transactions', 'products', 'users'] as $table) {
            $pdo->exec("TRUNCATE TABLE {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * The test database settings from the environment; fails the test unless DB_TEST_NAME is a safe target.
     * @return array{host: string, port: int, name: string, user: string, password: string}
     */
    protected static function config(): array
    {
        // Fail closed: setUp() empties every table, so only a *_test database that isn't the app's is allowed.
        $name = (string) getenv('DB_TEST_NAME');
        $appName = (string) getenv('DB_NAME');
        if ($appName === '' || !str_ends_with($name, '_test') || $name === $appName) {
            self::fail("Set DB_NAME, and DB_TEST_NAME to a separate *_test database (got '{$name}'), because the tests empty its tables.");
        }

        return [
            'host' => (string) getenv('DB_HOST'),
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => $name,
            'user' => (string) getenv('DB_USER'),
            'password' => (string) getenv('DB_PASSWORD'),
        ];
    }

    /**
     * A new user in the test database (password "password123").
     * @return User
     */
    protected function createUser(Role $role = Role::User, string $email = 'buyer@example.com'): User
    {
        return (new UserRepository($this->database))->create(ucfirst($role->value), $email, password_hash('password123', PASSWORD_DEFAULT), $role);
    }
}
