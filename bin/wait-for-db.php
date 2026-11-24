<?php

declare(strict_types=1);

/*
 * Waits up to 60 seconds for MySQL to accept connections, e.g. while a sleeping
 * Railway database wakes. Only "server unreachable" errors are retried; wrong
 * credentials or an unknown database fail at once.
 */

use App\Database\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var list<int> MySQL client errors for a server that isn't reachable yet. */
const UNREACHABLE = [2002, 2003, 2006, 2013];

$config = require dirname(__DIR__) . '/config/app.php';
$deadline = time() + 60;

for ($attempt = 1; ; $attempt++) {
    try {
        Database::fromConfig($config['db'])->pdo()->query('SELECT 1');
        echo "Database ready.\n";
        break;
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), UNREACHABLE, true) || time() >= $deadline) {
            throw $e;
        }

        fwrite(STDERR, "Database not reachable yet (attempt {$attempt}); retrying in 2 seconds.\n");
        sleep(2);
    }
}
