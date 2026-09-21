<?php

namespace App\Enums;

enum TeamStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
