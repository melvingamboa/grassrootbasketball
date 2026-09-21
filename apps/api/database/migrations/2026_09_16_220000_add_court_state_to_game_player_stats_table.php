<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_stats', function (Blueprint $table) {
            $table->boolean('is_on_court')->default(false)->after('is_starter');
            $table->unsignedTinyInteger('court_slot')->nullable()->after('is_on_court');
            $table->unique(['game_id', 'team_registration_id', 'court_slot'], 'game_team_court_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_stats', function (Blueprint $table) {
            $table->dropUnique('game_team_court_slot_unique');
            $table->dropColumn(['is_on_court', 'court_slot']);
        });
    }
};
