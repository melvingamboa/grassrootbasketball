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
        Schema::create('game_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_registration_id')->constrained('season_team_registrations')->restrictOnDelete();
            $table->boolean('is_starter')->default(false);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('rebounds')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('steals')->default(0);
            $table->unsignedSmallInteger('blocks')->default(0);
            $table->unsignedSmallInteger('turnovers')->default(0);
            $table->unsignedSmallInteger('fouls')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'player_registration_id']);
            $table->index(['game_id', 'team_registration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_player_stats');
    }
};
