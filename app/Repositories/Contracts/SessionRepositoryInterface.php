<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/*
 * Stored web sessions, keyed by the SHA-256 of the session ID. Times are Unix seconds.
 */
interface SessionRepositoryInterface
{
    /**
     * The session's data if it was active at or after $activeSince and began at or after $createdSince, or null.
     * @return string|null
     */
    public function find(string $idHash, int $activeSince, int $createdSince): ?string;

    /**
     * Saves the data and marks the session active now; a new session also records when it began.
     * @return void
     */
    public function save(string $idHash, ?int $userId, string $payload, int $now): void;

    /**
     * Marks the session active now without rewriting its data.
     * @return void
     */
    public function touch(string $idHash, int $now): void;

    /**
     * Deletes the session (logout).
     * @return void
     */
    public function delete(string $idHash): void;

    /**
     * Deletes sessions idle since before $activeSince or begun before $createdSince; returns how many.
     * @return int
     */
    public function deleteExpired(int $activeSince, int $createdSince): int;

    /**
     * Deletes every session of the user, signing them out everywhere; returns how many.
     * @return int
     */
    public function deleteForUser(int $userId): int;
}
