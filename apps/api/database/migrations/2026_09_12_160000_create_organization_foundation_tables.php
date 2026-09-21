<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_platform_admin', 'last_login_at']);
        });
    }
};
