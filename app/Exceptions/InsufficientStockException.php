<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
 * The requested quantity is more than the current stock; reported on the
 * quantity field like any other validation error.
 */
final class InsufficientStockException extends ValidationException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct(['quantity' => "Only {$available} left in stock."]);
    }
}
