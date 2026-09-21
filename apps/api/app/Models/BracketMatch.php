<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketMatch extends Model
{
    protected $fillable = [
        'bracket_id', 'round_number', 'match_number', 'round_label', 'home_team_registration_id',
        'away_team_registration_id', 'winner_team_registration_id', 'game_id',
    ];

    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    public function homeTeamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class, 'home_team_registration_id');
    }

    public function awayTeamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class, 'away_team_registration_id');
    }

    public function winnerTeamRegistration(): BelongsTo
    {
        return $this->belongsTo(SeasonTeamRegistration::class, 'winner_team_registration_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
