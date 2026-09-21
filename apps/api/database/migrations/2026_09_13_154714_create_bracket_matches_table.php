<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bracket_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->unsignedSmallInteger('match_number');
            $table->string('round_label', 64);
            $table->foreignId('home_team_registration_id')->nullable()->constrained('season_team_registrations')->nullOnDelete();
            $table->foreignId('away_team_registration_id')->nullable()->constrained('season_team_registrations')->nullOnDelete();
            $table->foreignId('winner_team_registration_id')->nullable()->constrained('season_team_registrations')->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['bracket_id', 'round_number', 'match_number'], 'bracket_round_match_unique');
            $table->index(['bracket_id', 'round_number', 'match_number'], 'bracket_match_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bracket_matches');
    }
};
