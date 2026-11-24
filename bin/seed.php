<?php

declare(strict_types=1);

/*
 * composer seed: adds the products and the demo accounts. Reads SEED_ADMIN_PASSWORD
 * (required) and SEED_USER_PASSWORD (optional) from the environment.
 */

use App\Database\Database;
use App\Repositories\UserRepository;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/app.php';
$database = Database::fromConfig($config['db']);

$products = (new ProductSeeder($database))->run();
$users = (new UserSeeder(new UserRepository($database)))->run((string) getenv('SEED_ADMIN_PASSWORD'), (string) getenv('SEED_USER_PASSWORD'));

echo 'Products added: ' . ($products === [] ? 'none' : implode(', ', $products)) . "\n";
echo 'Accounts added: ' . ($users === [] ? 'none' : implode(', ', $users)) . "\n";
