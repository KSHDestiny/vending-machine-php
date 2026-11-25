<?php

declare(strict_types=1);

namespace App\Enums;

/*
 * The value of the users.status ENUM column. Inactive accounts cannot sign in.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * The name shown to people.
     * @return string
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
