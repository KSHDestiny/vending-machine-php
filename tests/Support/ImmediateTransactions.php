<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\TransactionManager;

/*
 * Runs the work straight away and counts how often a transaction was used, for
 * unit tests that have no database.
 */
final class ImmediateTransactions implements TransactionManager
{
    /** @var int How many transactions were run. */
    public int $count = 0;

    public function transaction(callable $work): mixed
    {
        $this->count++;

        return $work();
    }
}
