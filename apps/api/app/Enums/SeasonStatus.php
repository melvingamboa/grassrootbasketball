<?php

namespace App\Enums;

enum SeasonStatus: string
{
    case Registration = 'registration';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
