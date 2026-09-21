<?php

namespace App\Enums;

enum SeasonFormat: string
{
    case RoundRobin = 'round_robin';
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::RoundRobin => 'Round robin',
            self::SingleElimination => 'Single elimination',
            self::DoubleElimination => 'Double elimination',
            self::Custom => 'Custom',
        };
    }
}
