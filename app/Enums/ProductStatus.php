<?php

declare(strict_types=1);

namespace App\Enums;

/*
 * The value of the products.status ENUM column. Inactive products are off sale:
 * hidden from customers and not purchasable. Sold out is derived from the stock.
 */
enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * The name shown to people.
     * @return string
     */
    public function label(): string
    {
        return $this === self::Active ? 'On sale' : 'Off sale';
    }
}
