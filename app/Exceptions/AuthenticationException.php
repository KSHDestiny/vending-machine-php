<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/*
 * No signed-in user, or wrong credentials: 401 for the API, the login page for the web.
 */
final class AuthenticationException extends RuntimeException
{
    public function __construct(string $message = 'Authentication required.')
    {
        parent::__construct($message);
    }
}
