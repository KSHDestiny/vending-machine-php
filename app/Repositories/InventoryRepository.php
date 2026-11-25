<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Support\Page;

final class InventoryRepository implements InventoryRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function record(int $productId, MovementType $type, int $quantityChange, int $quantityBefore, ?string $reason, ?int $userId, ?int $transactionId = null): void
    {
        $this->database->table('inventory_movements')->insert([
            'product_id' => $productId,
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'movement_type' => $type->value,
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore + $quantityChange,
            'reason' => $reason,
        ]);
    }

    public function paginateForProduct(int $productId, int $page, int $perPage, ?MovementType $type): Page
    {
        $query = $this->database->table('inventory_movements')
            ->select('inventory_movements.*', 'users.name AS user_name')
            ->leftJoin('users', 'users.id', 'inventory_movements.user_id')
            ->where('inventory_movements.product_id', $productId);
        if ($type !== null) {
            $query->where('inventory_movements.movement_type', $type->value);
        }

        return $query->orderBy('inventory_movements.id', 'desc')->paginate($page, $perPage)->map(self::toMovement(...));
    }

    /**
     * The movement for an `inventory_movements` row joined with user_name.
     * @param array<string, mixed> $row
     * @return InventoryMovement
     */
    private static function toMovement(array $row): InventoryMovement
    {
        return new InventoryMovement(
            (int) $row['id'],
            (int) $row['product_id'],
            MovementType::from((string) $row['movement_type']),
            (int) $row['quantity_change'],
            (int) $row['quantity_before'],
            (int) $row['quantity_after'],
            isset($row['reason']) ? (string) $row['reason'] : null,
            (string) $row['created_at'],
            isset($row['user_id']) ? (int) $row['user_id'] : null,
            isset($row['user_name']) ? (string) $row['user_name'] : null,
            isset($row['transaction_id']) ? (int) $row['transaction_id'] : null,
        );
    }
}
