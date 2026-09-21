<?php

namespace App\Enums;

enum DivisionCategory: string
{
    case Open = 'open';
    case Seniors = 'seniors';
    case Juniors = 'juniors';
    case Custom = 'custom';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
