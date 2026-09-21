<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administrative_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('short_name', 32)->nullable();
            $table->string('primary_color', 7)->default('#f59e0b');
            $table->string('secondary_color', 7)->default('#0f172a');
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 16)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'last_name', 'first_name']);
        });

        Schema::create('season_team_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['season_id', 'team_id']);
            $table->index(['division_id', 'status']);
        });

        Schema::create('player_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_team_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('jersey_number');
            $table->string('position', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['season_team_registration_id', 'player_id'], 'roster_unique_player');
            $table->unique(['season_team_registration_id', 'jersey_number'], 'roster_unique_jersey');
            $table->index(['season_team_registration_id', 'status'], 'roster_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_registrations');
        Schema::dropIfExists('season_team_registrations');
        Schema::dropIfExists('players');
        Schema::dropIfExists('teams');
    }
};
