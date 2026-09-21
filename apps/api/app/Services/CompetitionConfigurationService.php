<?php

namespace App\Services;

use App\Enums\DivisionCategory;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompetitionConfigurationService
{
    public function createVenue(Organization $organization, array $attributes): Venue
    {
        return $organization->venues()->create([
            ...$attributes,
            'slug' => $this->uniqueSlug($organization->venues(), $attributes['name']),
        ]);
    }

    public function createCompetition(Organization $organization, array $attributes): Competition
    {
        return $organization->competitions()->create([
            ...$attributes,
            'slug' => $this->uniqueSlug($organization->competitions(), $attributes['name']),
        ]);
    }

    public function createSeason(Organization $organization, Competition $competition, array $attributes): Season
    {
        $this->ensureCompetitionBelongsTo($organization, $competition);
        $this->ensureVenueBelongsTo($organization, $attributes['primary_venue_id'] ?? null);

        return DB::transaction(function () use ($competition, $attributes): Season {
            $season = $competition->seasons()->create([
                ...$attributes,
                'slug' => $this->uniqueSlug($competition->seasons(), $attributes['name']),
            ]);
            $season->divisions()->create([
                'name' => 'Open Division',
                'slug' => 'open-division',
                'category' => DivisionCategory::Open,
                'gender' => 'open',
                'is_active' => true,
            ]);

            return $season;
        });
    }

    public function updateSeason(Organization $organization, Competition $competition, Season $season, array $attributes): Season
    {
        $this->ensureSeasonBelongsTo($organization, $competition, $season);
        $this->ensureVenueBelongsTo($organization, $attributes['primary_venue_id'] ?? $season->primary_venue_id);
        $season->update($attributes);

        return $season;
    }

    public function createDivision(Organization $organization, Competition $competition, Season $season, array $attributes): Division
    {
        $this->ensureSeasonBelongsTo($organization, $competition, $season);

        return $season->divisions()->create([
            ...$attributes,
            'slug' => $this->uniqueSlug($season->divisions(), $attributes['name']),
        ]);
    }

    public function ensureCompetitionBelongsTo(Organization $organization, Competition $competition): void
    {
        abort_unless($competition->organization_id === $organization->id, 404);
    }

    public function ensureSeasonBelongsTo(Organization $organization, Competition $competition, Season $season): void
    {
        $this->ensureCompetitionBelongsTo($organization, $competition);
        abort_unless($season->competition_id === $competition->id, 404);
    }

    public function ensureDivisionBelongsTo(Season $season, Division $division): void
    {
        abort_unless($division->season_id === $season->id, 404);
    }

    private function ensureVenueBelongsTo(Organization $organization, ?int $venueId): void
    {
        if ($venueId !== null && ! $organization->venues()->whereKey($venueId)->exists()) {
            throw ValidationException::withMessages(['primary_venue_id' => 'Select a venue from this organization.']);
        }
    }

    private function uniqueSlug(mixed $relation, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($relation->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
