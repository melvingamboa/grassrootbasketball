<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_account_registration_is_not_available(): void
    {
        $this->withHeader('Origin', 'http://localhost:5175')->postJson('/api/v1/auth/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'juan@example.test']);
    }

    public function test_a_user_can_log_in_fetch_their_profile_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->withHeader('Origin', 'http://localhost:5175')->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('data.id', $user->id);

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withHeader('Origin', 'http://localhost:5175')->postJson('/api/v1/auth/logout')->assertOk();
        $this->withHeader('Origin', 'http://localhost:5175')
            ->getJson('/api/v1/auth/user')
            ->assertUnauthorized();
    }

    public function test_password_reset_requests_do_not_disclose_if_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_valid_signed_link_verifies_an_email_address(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($url)->assertRedirect(config('app.frontend_url').'/app?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_verification_notification_generates_the_expected_api_link(): void
    {
        $user = User::factory()->unverified()->create();
        $message = (new VerifyEmail)->toMail($user);

        $this->assertStringContainsString(
            "http://localhost:8010/api/v1/auth/email/verify/{$user->id}/",
            $message->actionUrl,
        );
    }
}
