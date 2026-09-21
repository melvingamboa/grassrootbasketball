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
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_registration_id')->constrained('season_team_registrations')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->unsignedInteger('points_for')->default(0);
            $table->unsignedInteger('points_against')->default(0);
            $table->string('qualification_status', 24)->default('pending');
            $table->string('notes', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['season_id', 'team_registration_id']);
            $table->unique(['division_id', 'rank']);
            $table->index(['season_id', 'division_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
