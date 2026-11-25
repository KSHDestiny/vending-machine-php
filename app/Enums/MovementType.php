<?php

declare(strict_types=1);

namespace App\Enums;

/*
 * Why a product's stock changed; the value of inventory_movements.movement_type.
 */
enum MovementType: string
{
    case InitialStock = 'INITIAL_STOCK';
    case Restock = 'RESTOCK';
    case Purchase = 'PURCHASE';
    case Adjustment = 'ADJUSTMENT';

    /**
     * The name shown to people.
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'Initial stock',
            self::Restock => 'Restock',
            self::Purchase => 'Purchase',
            self::Adjustment => 'Adjustment',
        };
    }
}
