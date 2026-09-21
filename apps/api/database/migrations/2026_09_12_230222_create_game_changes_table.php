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
        Schema::create('game_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_type', 24);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->dateTime('old_scheduled_at')->nullable();
            $table->dateTime('new_scheduled_at')->nullable();
            $table->foreignId('old_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('new_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->unsignedSmallInteger('old_home_score')->nullable();
            $table->unsignedSmallInteger('old_away_score')->nullable();
            $table->unsignedSmallInteger('new_home_score')->nullable();
            $table->unsignedSmallInteger('new_away_score')->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['game_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_changes');
    }
};
