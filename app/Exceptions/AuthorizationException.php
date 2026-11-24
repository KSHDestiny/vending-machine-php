<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/*
 * Signed in, but the role doesn't allow this action: 403.
 */
final class AuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'You are not allowed to do this.')
    {
        parent::__construct($message);
    }
}
