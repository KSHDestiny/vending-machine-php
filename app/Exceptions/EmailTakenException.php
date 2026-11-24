<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
 * Another account already uses the email; reported on the email field like any
 * other validation error.
 */
final class EmailTakenException extends ValidationException
{
    public function __construct()
    {
        parent::__construct(['email' => 'This email is already registered.']);
    }
}
