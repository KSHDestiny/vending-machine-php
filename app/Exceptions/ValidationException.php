<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/*
 * Invalid input, with one message per field; shown beside the fields on the web
 * and returned as 422 by the API.
 */
class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors message per field name
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }
}
