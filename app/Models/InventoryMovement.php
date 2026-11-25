<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;

/*
 * One recorded change to a product's stock: how much, from what to what, why, and by whom.
 */
final class InventoryMovement
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly MovementType $type,
        public readonly int $quantityChange,
        public readonly int $quantityBefore,
        public readonly int $quantityAfter,
        public readonly ?string $reason,
        public readonly string $createdAt,
        public readonly ?int $userId = null,
        public readonly ?string $userName = null,
        public readonly ?int $transactionId = null,
    ) {
    }
}
