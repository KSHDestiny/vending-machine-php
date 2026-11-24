<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param list<string> $allowed the methods the path does accept, for the Allow header
     */
    public function __construct(public readonly array $allowed)
    {
        parent::__construct('Method not allowed.');
    }
}
