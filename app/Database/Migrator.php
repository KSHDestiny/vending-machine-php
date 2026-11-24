<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/*
 * Applies database/migrations/*.sql in filename order, once each, recorded in
 * schema_migrations. MySQL commits DDL at once, so every file must be safe to run twice.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $database,
        private readonly string $directory,
    ) {
    }

    /**
     * The migration names applied by this run.
     * @return list<string>
     */
    public function migrate(): array
    {
        $files = glob($this->directory . '/*.sql') ?: throw new RuntimeException("No .sql migrations found in {$this->directory}");
        sort($files, SORT_STRING);

        $pdo = $this->database->pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB',
        );
        $versions = $pdo->prepare('SELECT version FROM schema_migrations');
        $versions->execute();
        $done = $versions->fetchAll(PDO::FETCH_COLUMN);
        $record = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');

        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException("Migration {$version} is empty or unreadable");
            }

            $pdo->exec($sql);
            $record->execute([$version]);
            $applied[] = $version;
        }

        return $applied;
    }
}
