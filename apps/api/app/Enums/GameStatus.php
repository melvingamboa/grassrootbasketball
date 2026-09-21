<?php

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case Delayed = 'delayed';
    case Live = 'live';
    case Suspended = 'suspended';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Delayed => 'Delayed',
            self::Live => 'Live',
            self::Suspended => 'Suspended',
            self::Postponed => 'Postponed',
            self::Cancelled => 'Cancelled',
            self::Final => 'Final',
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Delayed, self::Suspended, self::Postponed, self::Cancelled], true);
    }

    public function occupiesSchedule(): bool
    {
        return in_array($this, [self::Scheduled, self::Delayed, self::Live, self::Suspended], true);
    }
}
