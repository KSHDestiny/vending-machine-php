<?php

declare(strict_types=1);

namespace App\Database;

/*
 * Runs work in one database transaction: commits if it returns, rolls back if it
 * throws. Services depend on this, not on PDO, so they can be unit-tested.
 */
interface TransactionManager
{
    /**
     * The work's result, after committing.
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed;
}
