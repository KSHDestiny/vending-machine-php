<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Exceptions\InsufficientStockException;
use App\Models\User;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use PDOException;
use Tests\Support\DatabaseTestCase;

/*
 * Purchases against the real test database: a failed purchase changes nothing, and
 * buyers racing for the last item can't oversell it.
 */
final class PurchaseTransactionTest extends DatabaseTestCase
{
    /** @var string Buys one item in a separate PHP process: argv = product id, buyer id. Connects, says "ready", then waits for "go" on stdin. */
    private const BUYER_PROCESS = <<<'PHP'
        require 'vendor/autoload.php';
        $database = App\Database\Database::fromConfig([
            'host' => (string) getenv('DB_HOST'),
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => (string) getenv('DB_TEST_NAME'),
            'user' => (string) getenv('DB_USER'),
            'password' => (string) getenv('DB_PASSWORD'),
        ]);
        $products = new App\Repositories\ProductRepository($database);
        $inventory = new App\Services\InventoryService($products, new App\Repositories\InventoryRepository($database), $database);
        $service = new App\Services\PurchaseService($products, new App\Repositories\TransactionRepository($database), $inventory, $database);
        $buyer = new App\Models\User((int) $argv[2], 'Buyer', 'buyer@example.com', 'hash', App\Enums\Role::User);
        $database->pdo();
        echo "ready\n";
        // Buy only when released; a closed pipe (the test gave up) must not trigger a purchase.
        if (fgets(STDIN) !== "go\n") {
            echo 'not released';
        } else {
            try {
                $service->purchase($buyer, (int) $argv[1], '1');
                echo 'ok';
            } catch (Throwable $e) {
                echo $e::class;
            }
        }
        PHP;

    public function testInsufficientStockChangesNothing(): void
    {
        $buyer = $this->createUser();
        $product = (new ProductRepository($this->database))->create('Coke', '3.990', 2);

        try {
            $this->purchases()->purchase($buyer, $product->id, '3');
            self::fail('The purchase should have been refused');
        } catch (InsufficientStockException $e) {
            self::assertSame(2, $e->available);
        }

        self::assertSame(2, $this->stockOf($product->id));
        self::assertSame(0, $this->database->table('transactions')->count());
    }

    public function testFailureWhileRecordingRollsBackTheStockChange(): void
    {
        $product = (new ProductRepository($this->database))->create('Pepsi', '6.885', 5);
        // No such user: stock is taken first, then recording the purchase hits the foreign key.
        $ghost = new User(999_999, 'Ghost', 'ghost@example.com', 'hash', Role::User);

        try {
            $this->purchases()->purchase($ghost, $product->id, '2');
            self::fail('Recording the purchase should have failed');
        } catch (PDOException) {
        }

        self::assertSame(5, $this->stockOf($product->id), 'the stock decrement was rolled back');
        self::assertSame(0, $this->database->table('transactions')->count());
    }

    public function testBuyersRacingForTheLastItemCannotOversell(): void
    {
        $buyer = $this->createUser();
        $product = (new ProductRepository($this->database))->create('Water', '0.500', 1);

        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $command = [PHP_BINARY, '-r', self::BUYER_PROCESS, '--', (string) $product->id, (string) $buyer->id];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            self::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        // Release the buyers together only once every one is connected and waiting.
        foreach ($processes as [, $pipes]) {
            if (fgets($pipes[1]) !== "ready\n") {
                self::fail('A buyer failed to start: ' . stream_get_contents($pipes[2]));
            }
        }
        foreach ($processes as [, $pipes]) {
            fwrite($pipes[0], "go\n");
            fclose($pipes[0]);
        }

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        sort($results);

        self::assertSame([...array_fill(0, 5, InsufficientStockException::class), 'ok'], $results);
        self::assertSame(0, $this->stockOf($product->id));
        self::assertSame(1, $this->database->table('transactions')->count());
        self::assertSame(1, $this->database->table('inventory_movements')->where('movement_type', 'PURCHASE')->count(), 'exactly one purchase movement');
    }

    /**
     * The purchase service on the real repositories and database transactions.
     * @return PurchaseService
     */
    private function purchases(): PurchaseService
    {
        $products = new ProductRepository($this->database);

        return new PurchaseService($products, new TransactionRepository($this->database), new InventoryService($products, new InventoryRepository($this->database), $this->database), $this->database);
    }

    /**
     * The product's stock as stored.
     * @return int
     */
    private function stockOf(int $productId): int
    {
        return (new ProductRepository($this->database))->find($productId)->quantityAvailable ?? -1;
    }
}
