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
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_period')->default(1)->after('away_score');
            $table->unsignedSmallInteger('clock_seconds_remaining')->default(0)->after('current_period');
            $table->boolean('clock_running')->default(false)->after('clock_seconds_remaining');
            $table->timestamp('clock_started_at')->nullable()->after('clock_running');
            $table->timestamp('started_at')->nullable()->after('clock_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'current_period', 'clock_seconds_remaining', 'clock_running',
                'clock_started_at', 'started_at',
            ]);
        });
    }
};
