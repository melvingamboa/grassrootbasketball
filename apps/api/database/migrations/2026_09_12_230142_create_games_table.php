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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_team_registration_id')->constrained('season_team_registrations')->restrictOnDelete();
            $table->foreignId('away_team_registration_id')->constrained('season_team_registrations')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(90);
            $table->string('round', 64)->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->unsignedSmallInteger('home_score')->default(0);
            $table->unsignedSmallInteger('away_score')->default(0);
            $table->text('status_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['season_id', 'scheduled_at']);
            $table->index(['season_id', 'status', 'scheduled_at']);
            $table->index(['home_team_registration_id', 'scheduled_at'], 'game_home_schedule_index');
            $table->index(['away_team_registration_id', 'scheduled_at'], 'game_away_schedule_index');
            $table->index(['venue_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
