<?php

namespace App\Enums;

enum CompetitionType: string
{
    case League = 'league';
    case Tournament = 'tournament';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
