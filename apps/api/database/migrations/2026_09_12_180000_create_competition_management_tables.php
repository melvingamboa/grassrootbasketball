<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('administrative_areas')->restrictOnDelete();
            $table->string('type', 24);
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->timestamps();
            $table->unique(['parent_id', 'type', 'name']);
            $table->index(['type', 'name']);
        });

        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administrative_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administrative_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type', 24);
            $table->string('status', 24)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('primary_venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->string('format', 32)->default('round_robin');
            $table->string('status', 24)->default('registration');
            $table->unsignedTinyInteger('max_roster_size')->default(20);
            $table->unsignedTinyInteger('period_count')->default(4);
            $table->unsignedTinyInteger('period_minutes')->default(10);
            $table->unsignedTinyInteger('overtime_minutes')->default(5);
            $table->text('rules_notes')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'slug']);
            $table->index(['competition_id', 'status']);
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('category', 24)->default('open');
            $table->string('gender', 16)->default('open');
            $table->unsignedTinyInteger('minimum_age')->nullable();
            $table->unsignedTinyInteger('maximum_age')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['season_id', 'slug']);
            $table->index(['season_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('administrative_areas');
    }
};
