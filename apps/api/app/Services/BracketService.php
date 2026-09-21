<?php

namespace App\Services;

use App\Enums\BracketStatus;
use App\Enums\RegistrationStatus;
use App\Models\Bracket;
use App\Models\BracketMatch;
use App\Models\Game;
use App\Models\Season;
use App\Models\SeasonTeamRegistration;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BracketService
{
    public function list(Season $season, bool $publishedOnly = false): Collection
    {
        return Bracket::query()
            ->whereBelongsTo($season)
            ->when($publishedOnly, fn ($query) => $query->where('status', BracketStatus::Published))
            ->with($this->relations())
            ->orderBy('division_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function create(Season $season, array $attributes, User $user): Bracket
    {
        $season->divisions()->findOrFail($attributes['division_id']);
        $template = Arr::pull($attributes, 'template', 'empty');
        $bracket = DB::transaction(function () use ($season, $attributes, $user, $template): Bracket {
            $bracket = $season->brackets()->create([
                ...$attributes,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($template !== 'empty') {
                $this->createMissingTemplateSlots($bracket, $template);
            }

            return $bracket;
        });

        return $this->load($bracket);
    }

    public function initialize(Season $season, Bracket $bracket, string $template): Bracket
    {
        $this->ensureBracketBelongsTo($season, $bracket);
        DB::transaction(fn () => $this->createMissingTemplateSlots($bracket, $template));

        return $this->load($bracket->refresh());
    }

    public function update(Season $season, Bracket $bracket, array $attributes, User $user): Bracket
    {
        $this->ensureBracketBelongsTo($season, $bracket);
        $bracket->update([...$attributes, 'updated_by' => $user->id]);

        return $this->load($bracket->refresh());
    }

    public function delete(Season $season, Bracket $bracket): void
    {
        $this->ensureBracketBelongsTo($season, $bracket);
        $bracket->delete();
    }

    public function createMatch(Season $season, Bracket $bracket, array $attributes): BracketMatch
    {
        $this->ensureBracketBelongsTo($season, $bracket);
        $normalized = $this->normalizedMatchAttributes($season, $bracket, $attributes);
        $this->ensurePositionAvailable($bracket, $normalized);

        return $this->loadMatch($bracket->matches()->create($normalized));
    }

    public function updateMatch(Season $season, Bracket $bracket, BracketMatch $match, array $attributes): BracketMatch
    {
        $this->ensureMatchBelongsTo($season, $bracket, $match);
        $candidate = [
            'round_number' => $attributes['round_number'] ?? $match->round_number,
            'match_number' => $attributes['match_number'] ?? $match->match_number,
            'round_label' => $attributes['round_label'] ?? $match->round_label,
            'home_team_registration_id' => array_key_exists('home_team_registration_id', $attributes) ? $attributes['home_team_registration_id'] : $match->home_team_registration_id,
            'away_team_registration_id' => array_key_exists('away_team_registration_id', $attributes) ? $attributes['away_team_registration_id'] : $match->away_team_registration_id,
            'winner_team_registration_id' => array_key_exists('winner_team_registration_id', $attributes) ? $attributes['winner_team_registration_id'] : $match->winner_team_registration_id,
            'game_id' => array_key_exists('game_id', $attributes) ? $attributes['game_id'] : $match->game_id,
        ];
        $normalized = $this->normalizedMatchAttributes($season, $bracket, $candidate);
        $this->ensurePositionAvailable($bracket, $normalized, $match);
        $match->update($normalized);

        return $this->loadMatch($match->refresh());
    }

    public function deleteMatch(Season $season, Bracket $bracket, BracketMatch $match): void
    {
        $this->ensureMatchBelongsTo($season, $bracket, $match);
        $match->delete();
    }

    public function ensureBracketBelongsTo(Season $season, Bracket $bracket): void
    {
        abort_unless($bracket->season_id === $season->id, 404);
    }

    private function ensureMatchBelongsTo(Season $season, Bracket $bracket, BracketMatch $match): void
    {
        $this->ensureBracketBelongsTo($season, $bracket);
        abort_unless($match->bracket_id === $bracket->id, 404);
    }

    private function normalizedMatchAttributes(Season $season, Bracket $bracket, array $attributes): array
    {
        $home = $this->registration($season, $bracket, $attributes['home_team_registration_id'] ?? null, 'home_team_registration_id');
        $away = $this->registration($season, $bracket, $attributes['away_team_registration_id'] ?? null, 'away_team_registration_id');
        $winner = $this->registration($season, $bracket, $attributes['winner_team_registration_id'] ?? null, 'winner_team_registration_id');

        if ($home?->is($away)) {
            throw ValidationException::withMessages(['away_team_registration_id' => 'Choose two different teams.']);
        }

        if ($winner !== null && ! $winner->is($home) && ! $winner->is($away)) {
            throw ValidationException::withMessages(['winner_team_registration_id' => 'The winner must be one of the teams in this matchup.']);
        }

        $gameId = $attributes['game_id'] ?? null;
        if ($gameId !== null && ! Game::query()->whereBelongsTo($season)->where('division_id', $bracket->division_id)->whereKey($gameId)->exists()) {
            throw ValidationException::withMessages(['game_id' => 'Select a game from this season and division.']);
        }

        return [
            ...$attributes,
            'round_number' => (int) $attributes['round_number'],
            'match_number' => (int) $attributes['match_number'],
            'home_team_registration_id' => $home?->id,
            'away_team_registration_id' => $away?->id,
            'winner_team_registration_id' => $winner?->id,
            'game_id' => $gameId,
        ];
    }

    private function registration(Season $season, Bracket $bracket, mixed $registrationId, string $field): ?SeasonTeamRegistration
    {
        if ($registrationId === null) {
            return null;
        }

        $registration = $season->teamRegistrations()
            ->where('division_id', $bracket->division_id)
            ->where('status', RegistrationStatus::Approved)
            ->find($registrationId);
        if ($registration === null) {
            throw ValidationException::withMessages([$field => 'Select an approved team from this season and division.']);
        }

        return $registration;
    }

    private function ensurePositionAvailable(Bracket $bracket, array $attributes, ?BracketMatch $except = null): void
    {
        $exists = $bracket->matches()
            ->where('round_number', $attributes['round_number'])
            ->where('match_number', $attributes['match_number'])
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['match_number' => 'This match number is already used in the selected round.']);
        }
    }

    private function createMissingTemplateSlots(Bracket $bracket, string $template): void
    {
        $slots = match ($template) {
            'single_elimination_4' => [
                ['round_number' => 1, 'match_number' => 1, 'round_label' => 'Semifinals'],
                ['round_number' => 1, 'match_number' => 2, 'round_label' => 'Semifinals'],
                ['round_number' => 2, 'match_number' => 1, 'round_label' => 'Finals'],
            ],
            'single_elimination_8' => [
                ['round_number' => 1, 'match_number' => 1, 'round_label' => 'Quarterfinals'],
                ['round_number' => 1, 'match_number' => 2, 'round_label' => 'Quarterfinals'],
                ['round_number' => 1, 'match_number' => 3, 'round_label' => 'Quarterfinals'],
                ['round_number' => 1, 'match_number' => 4, 'round_label' => 'Quarterfinals'],
                ['round_number' => 2, 'match_number' => 1, 'round_label' => 'Semifinals'],
                ['round_number' => 2, 'match_number' => 2, 'round_label' => 'Semifinals'],
                ['round_number' => 3, 'match_number' => 1, 'round_label' => 'Finals'],
            ],
        };

        collect($slots)->each(fn (array $slot) => $bracket->matches()->firstOrCreate(
            Arr::only($slot, ['round_number', 'match_number']),
            ['round_label' => $slot['round_label']],
        ));
    }

    private function load(Bracket $bracket): Bracket
    {
        return $bracket->load($this->relations());
    }

    private function loadMatch(BracketMatch $match): BracketMatch
    {
        return $match->load(['homeTeamRegistration.team', 'awayTeamRegistration.team', 'winnerTeamRegistration.team', 'game']);
    }

    private function relations(): array
    {
        return [
            'division', 'createdBy', 'updatedBy', 'matches.homeTeamRegistration.team',
            'matches.awayTeamRegistration.team', 'matches.winnerTeamRegistration.team', 'matches.game',
        ];
    }
}
