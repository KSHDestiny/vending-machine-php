<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Support\Page;

/*
 * The history of stock changes. Rows are only ever added, never changed.
 */
interface InventoryRepositoryInterface
{
    /**
     * Records a stock change; quantityAfter must equal quantityBefore + quantityChange.
     * @return void
     */
    public function record(int $productId, MovementType $type, int $quantityChange, int $quantityBefore, ?string $reason, ?int $userId, ?int $transactionId = null): void;

    /**
     * One page of a product's movements, newest first, optionally of one type.
     * @return Page<InventoryMovement>
     */
    public function paginateForProduct(int $productId, int $page, int $perPage, ?MovementType $type): Page;
}
