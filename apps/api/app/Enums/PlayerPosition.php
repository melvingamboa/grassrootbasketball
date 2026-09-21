<?php

namespace App\Enums;

enum PlayerPosition: string
{
    case Guard = 'guard';
    case Forward = 'forward';
    case Center = 'center';
    case Utility = 'utility';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
