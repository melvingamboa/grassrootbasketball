<?php

namespace App\Enums;

enum StandingQualificationStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Eliminated = 'eliminated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Qualified => 'Qualified',
            self::Eliminated => 'Eliminated',
        };
    }
}
