<?php

declare(strict_types=1);

/*
 * composer migrate: applies new database/migrations/*.sql files.
 */

use App\Database\Database;
use App\Database\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/app.php';
$applied = (new Migrator(Database::fromConfig($config['db']), $config['paths']['migrations']))->migrate();

echo $applied === [] ? "Nothing to migrate.\n" : 'Applied: ' . implode(', ', $applied) . "\n";
