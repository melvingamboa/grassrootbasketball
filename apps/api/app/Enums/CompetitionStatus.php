<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
