<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Repositories\Contracts\SessionRepositoryInterface;

final class SessionRepository implements SessionRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function find(string $idHash, int $activeSince, int $createdSince): ?string
    {
        $row = $this->database->table('sessions')
            ->select('payload')
            ->where('id', $idHash)
            ->where('last_activity', $activeSince, '>=')
            ->where('created_at', $createdSince, '>=')
            ->first();

        return $row === null ? null : (string) $row['payload'];
    }

    public function save(string $idHash, ?int $userId, string $payload, int $now): void
    {
        // Raw SQL on purpose: one upsert keeps created_at from the first write.
        $this->database->pdo()->prepare(
            'INSERT INTO sessions (id, user_id, payload, last_activity, created_at) VALUES (?, ?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload), last_activity = VALUES(last_activity)',
        )->execute([$idHash, $userId, $payload, $now, $now]);
    }

    public function touch(string $idHash, int $now): void
    {
        $this->database->table('sessions')->where('id', $idHash)->update(['last_activity' => $now]);
    }

    public function delete(string $idHash): void
    {
        $this->database->pdo()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$idHash]);
    }

    public function deleteExpired(int $activeSince, int $createdSince): int
    {
        $statement = $this->database->pdo()->prepare('DELETE FROM sessions WHERE last_activity < ? OR created_at < ?');
        $statement->execute([$activeSince, $createdSince]);

        return $statement->rowCount();
    }

    public function deleteForUser(int $userId): int
    {
        $statement = $this->database->pdo()->prepare('DELETE FROM sessions WHERE user_id = ?');
        $statement->execute([$userId]);

        return $statement->rowCount();
    }
}
