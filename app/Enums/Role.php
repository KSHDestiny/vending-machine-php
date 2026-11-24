<?php

declare(strict_types=1);

namespace App\Enums;

/*
 * The value of the users.role ENUM column; there is no roles table.
 */
enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}
