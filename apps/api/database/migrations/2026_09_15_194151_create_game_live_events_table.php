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
        Schema::create('game_live_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->foreignId('team_registration_id')->nullable()->constrained('season_team_registrations')->nullOnDelete();
            $table->unsignedTinyInteger('period_number');
            $table->smallInteger('points_delta')->nullable();
            $table->unsignedSmallInteger('home_score_after');
            $table->unsignedSmallInteger('away_score_after');
            $table->unsignedSmallInteger('clock_seconds_remaining');
            $table->json('details')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_live_events');
    }
};
