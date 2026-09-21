<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->text('livestream_url')->nullable()->after('status_reason');
            $table->string('livestream_provider', 24)->nullable()->after('livestream_url');
            $table->string('livestream_status', 24)->default('unavailable')->after('livestream_provider');
            $table->index(['livestream_status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropIndex(['livestream_status', 'scheduled_at']);
            $table->dropColumn(['livestream_url', 'livestream_provider', 'livestream_status']);
        });
    }
};
