<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case LeagueManager = 'league_manager';
    case Scorer = 'scorer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Administrator',
            self::LeagueManager => 'League manager',
            self::Scorer => 'Scorer',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageLeagues(): bool
    {
        return $this !== self::Scorer;
    }

    public function canScoreGames(): bool
    {
        return true;
    }
}
