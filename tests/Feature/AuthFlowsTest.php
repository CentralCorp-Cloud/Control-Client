<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_required_to_verify_email(): void
    {
        $response = $this->post('/register', ['name' => 'Alice', 'email' => 'ALICE@example.com', 'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com', 'email_verified_at' => null]);
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_registration_setting_is_enforced_on_get_and_post(): void
    {
        Setting::create(['key' => 'registration_open', 'value' => 'false']);
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_and_password_reset_request_work(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'known-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'known-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_signed_email_verification_marks_user_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect('/dashboard?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
